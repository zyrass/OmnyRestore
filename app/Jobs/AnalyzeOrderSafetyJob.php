<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use App\Mail\AdminOrderFlagged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AnalyzeOrderSafetyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function handle(): void
    {
        // On ne vérifie que les commandes PENDING (juste après l'upload)
        if ($this->order->status !== 'PENDING') {
            return;
        }

        $apiKey = config('openai.api_key');
        if (!$apiKey) {
            Log::warning('AnalyzeOrderSafetyJob ignoré : Clé OpenAI manquante.');
            return;
        }

        $medias = $this->order->getMedia('originals');
        if ($medias->isEmpty()) {
            return;
        }

        $flagged = false;
        $nsfwCategories = [];

        foreach ($medias as $media) {
            $path = $media->getPath();
            if (!file_exists($path)) {
                continue;
            }

            // Convertir l'image en Base64
            $mime = mime_content_type($path);
            $base64 = base64_encode(file_get_contents($path));
            $dataUrl = "data:{$mime};base64,{$base64}";

            try {
                // Appel à la nouvelle API multimodale de modération
                $response = Http::withToken($apiKey)
                    ->timeout(30)
                    ->post('https://api.openai.com/v1/moderations', [
                        'model' => 'omni-moderation-latest',
                        'input' => [
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => $dataUrl
                                ]
                            ]
                        ]
                    ]);

                if ($response->successful()) {
                    $result = $response->json('results.0');
                    if ($result && $result['flagged']) {
                        $flagged = true;
                        
                        // Enregistrer que cette image est NSFW
                        $media->setCustomProperty('is_nsfw', true);
                        
                        // Récupérer les catégories actives
                        foreach ($result['categories'] as $cat => $isActive) {
                            if ($isActive) {
                                $nsfwCategories[] = $cat;
                                $media->setCustomProperty("nsfw_category_{$cat}", true);
                            }
                        }
                        $media->save();
                    }
                } else {
                    Log::error("Erreur API Moderation: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("Erreur lors de l'appel OpenAI Moderation: " . $e->getMessage());
            }
        }

        // Si au moins une image est signalée, on bloque la commande
        if ($flagged) {
            $nsfwCategories = array_unique($nsfwCategories);
            
            // On force le statut FLAGGED (évite les mutations accidentelles si on appelait cancel())
            $this->order->forceFill([
                'status' => 'FLAGGED',
                'admin_notes' => 'DÉTECTION IA : Contenu sensible/illégal. Catégories : ' . implode(', ', $nsfwCategories)
            ])->save();

            Log::alert("Commande {$this->order->reference} signalée par l'IA pour contenu sensible.", [
                'order_id' => $this->order->id,
                'categories' => $nsfwCategories
            ]);

            // Alerter tous les admins
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                Mail::to($admin->email)->queue(new AdminOrderFlagged($this->order, $nsfwCategories));
            }
        }
    }
}

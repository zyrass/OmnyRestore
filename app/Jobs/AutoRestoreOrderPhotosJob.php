<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\PhotoRestorationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: AutoRestoreOrderPhotosJob
 *
 * Traitement IA automatique de toutes les photos originales d'une commande.
 *
 * Pipeline :
 *   1. Récupère les photos de la collection `originals`
 *   2. Pour chaque photo → PhotoRestorationService::restore()
 *      (GPT-4o Vision → DALL-E 3 → upscale 8K)
 *   3. Ajoute le résultat dans la collection `retouched`
 *   4. Le listener GenerateWatermarkOnRetouchedUpload déclenche le watermark (Phase 7)
 *
 * Déclenchement :
 *   - Option B (manuel) : bouton admin dans la fiche commande
 *   - Manuel : php artisan photos:restore --order=REF --sync
 *
 * Queue : `default` (configurer une queue dédiée `restoration` en production)
 * Timeout : 600s (10min max — DALL-E 3 peut être lent)
 * Retries : 2
 */
class AutoRestoreOrderPhotosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 600;
    public int $backoff = 30;

    public function __construct(public readonly Order $order) {}

    /**
     * Execute the job.
     */
    public function handle(PhotoRestorationService $service): void
    {
        $originals = $this->order->getMedia('originals');

        if ($originals->isEmpty()) {
            Log::warning("[Restoration] Commande {$this->order->reference} : aucune photo originale.");
            return;
        }

        // Marquer la commande comme en cours de traitement IA
        // (si pas déjà IN_PROGRESS)
        if ($this->order->status === 'PENDING') {
            $this->order->startProcessing();
        }

        $successCount = 0;
        $errorCount   = 0;

        foreach ($originals as $media) {
            $tmpPath = null;
            try {
                Log::info("[Restoration] Traitement de {$media->file_name} ({$media->human_readable_size})...");

                // Restauration via GPT-4o + DALL-E 3 + upscale 8K
                $tmpPath = $service->restore($media, $this->order);

                // Ajouter à la collection retouched
                // (déclenche automatiquement GenerateWatermarkJob via le listener)
                $this->order
                    ->addMedia($tmpPath)
                    ->usingFileName('restored_' . pathinfo($media->file_name, PATHINFO_FILENAME) . '.jpg')
                    ->withCustomProperties([
                        'ai_restored'     => true,
                        'source_media_id' => $media->id,
                        'model_analysis'  => 'gpt-4o',
                        'model_generate'  => 'dall-e-3',
                        'enhancement'     => 'improved-quality',
                    ])
                    ->toMediaCollection('retouched');

                $successCount++;
                Log::info("[Restoration] ✅ {$media->file_name} restaurée avec succès.");

            } catch (\Throwable $e) {
                $errorCount++;
                Log::error("[Restoration] ❌ Erreur sur {$media->file_name} : " . $e->getMessage(), [
                    'order'   => $this->order->reference,
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                ]);

                // Nettoyage fichier tmp si présent
                if ($tmpPath && file_exists($tmpPath)) {
                    @unlink($tmpPath);
                }
            }
        }

        // Sauvegarder un résumé dans les notes admin
        $summary = "[IA] Restauration automatique : {$successCount} succès, {$errorCount} erreur(s). " . now()->format('d/m/Y H:i');
        $this->order->update([
            'admin_notes' => ($this->order->admin_notes ? $this->order->admin_notes . "\n" : '') . $summary,
        ]);

        Log::info("[Restoration] Commande {$this->order->reference} terminée — {$successCount}/{$originals->count()} photos.");
    }

    /**
     * Gestion de l'échec définitif (après tous les retries).
     */
    public function failed(\Throwable $e): void
    {
        Log::critical("[Restoration] Job définitivement échoué pour {$this->order->reference} : " . $e->getMessage());

        $this->order->update([
            'admin_notes' => ($this->order->admin_notes ? $this->order->admin_notes . "\n" : '')
                          . "[IA] ❌ Échec restauration automatique : " . $e->getMessage(),
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Job: GenerateWatermarkJob
 *
 * Génère des aperçus filigranés pour toutes les photos retouchées d'une commande.
 *
 * Pipeline pour chaque photo `retouched` :
 *   1. Lecture via Intervention Image v4 (GD driver) → `$manager->decode($path)`
 *   2. Redimensionnement à 1200px max (ratio conservé)
 *   3. Filigrane diagonal "OmnyRestore" répété (Inter Bold TTF, blanc 18%)
 *   4. Export JPEG 75% qualité
 *   5. Ajout dans la collection `watermarked` de la commande
 *
 * Déclenchement :
 *   - Automatique via `MediaHasBeenAddedEvent` (listener Spatie)
 *   - Manuel via `php artisan watermarks:regenerate [--order=REF] [--sync]`
 *
 * Queue : `default` · Retries : 3 · Backoff : 60s · Timeout : 120s
 */
class GenerateWatermarkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60;
    public int $timeout = 120;

    public function __construct(public readonly Order $order) {}

    public function handle(): void
    {
        $retouched = $this->order->getMedia('retouched');

        if ($retouched->isEmpty()) {
            Log::info("[Watermark] Commande {$this->order->reference} : aucune photo retouchée.");
            return;
        }

        // Effacer les anciens watermarks avant de régénérer
        $this->order->clearMediaCollection('watermarked');

        $manager  = new ImageManager(new Driver());
        $fontPath = storage_path('app/fonts/watermark.ttf');
        $useTtf   = file_exists($fontPath);

        foreach ($retouched as $media) {
            $tmpOutput = null;
            try {
                $tmpOutput = tempnam(sys_get_temp_dir(), 'wmk_') . '.jpg';

                // 1. Lire l'image (API v3)
                $image = $manager->read($media->getPath());

                // 2. Redimensionner à 1200px max (ratio conservé)
                $image->scaleDown(width: 1200, height: 1200);

                // 3. Appliquer le filigrane tuilé en diagonale
                $this->applyWatermark($image, $fontPath, $useTtf);

                // 4. Encoder en JPEG 75% et sauvegarder
                $image->toJpeg(75)->save($tmpOutput);

                // 5. Ajouter à la collection watermarked
                $this->order
                    ->addMedia($tmpOutput)
                    ->usingFileName('watermark_' . pathinfo($media->file_name, PATHINFO_FILENAME) . '.jpg')
                    ->toMediaCollection('watermarked');

            } catch (\Throwable $e) {
                Log::error("[Watermark] Erreur sur {$media->file_name} : " . $e->getMessage(), [
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                    'order' => $this->order->reference,
                ]);
                if ($tmpOutput && file_exists($tmpOutput)) {
                    @unlink($tmpOutput);
                }
            }
        }

        Log::info("[Watermark] Commande {$this->order->reference} : {$retouched->count()} watermark(s) générés.");
    }

    /**
     * Applique un filigrane "OmnyRestore" répété en diagonale.
     *
     * Grille de positionnement (xStep × yStep) décalée pour couvrir toute l'image,
     * y compris les coins. Le décalage horizontal alterne (offset = xStep/2)
     * pour simuler un pavage.
     */
    private function applyWatermark(
        \Intervention\Image\Interfaces\ImageInterface $image,
        string $fontPath,
        bool $useTtf
    ): void {
        $w     = $image->width();
        $h     = $image->height();
        $text  = 'OmnyRestore';
        $xStep = 200;
        $yStep = 140;

        for ($row = 0, $y = -$yStep; $y < $h + $yStep * 2; $y += $yStep, $row++) {
            $offset = ($row % 2 === 0) ? 0 : (int) ($xStep / 2);
            for ($x = -$xStep + $offset; $x < $w + $xStep; $x += $xStep) {
                $image->text(
                    $text,
                    $x,
                    $y,
                    function (\Intervention\Image\Typography\FontFactory $font) use ($fontPath, $useTtf) {
                        if ($useTtf) {
                            $font->filename($fontPath);
                        }
                        $font->size(26);
                        $font->color('rgba(255, 255, 255, 0.18)');
                        $font->align('center', 'middle');
                        $font->angle(-35);
                    }
                );
            }
        }
    }
}

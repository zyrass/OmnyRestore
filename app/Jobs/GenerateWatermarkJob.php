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

        foreach ($retouched as $media) {
            static::generateForMedia($this->order, $media);
        }

        Log::info("[Watermark] Commande {$this->order->reference} : {$retouched->count()} watermark(s) générés.");
    }

    /**
     * Génère un watermark pour un média spécifique et l'ajoute à la collection 'watermarked'.
     * Peut être appelé de manière synchrone par le controller si le watermark manque.
     */
    public static function generateForMedia(Order $order, \Spatie\MediaLibrary\MediaCollections\Models\Media $media): bool
    {
        $manager  = new ImageManager(new Driver());
        $fontPath = storage_path('app/fonts/watermark.ttf');
        $useTtf   = file_exists($fontPath);
        $tmpOutput = null;

        try {
            $tmpOutput = tempnam(sys_get_temp_dir(), 'wmk_') . '.jpg';

            // 1. Lire l'image (API v3)
            $image = $manager->read($media->getPath());

            // 2. Redimensionner à 1200px max (ratio conservé)
            $image->scaleDown(width: 1200, height: 1200);

            // 3. Appliquer le filigrane tuilé en diagonale
            // Note: On utilise une instance anonyme pour appeler la méthode privée si besoin, 
            // ou on rend la méthode statique/publique. Rendons-la statique.
            static::applyWatermarkToImage($image, $fontPath, $useTtf);

            // 4. Encoder en JPEG 75% et sauvegarder
            $image->toJpeg(75)->save($tmpOutput);

            // 5. Ajouter à la collection watermarked
            $order->addMedia($tmpOutput)
                ->usingFileName('watermark_' . pathinfo($media->file_name, PATHINFO_FILENAME) . '.jpg')
                ->toMediaCollection('watermarked');

            return true;
        } catch (\Throwable $e) {
            Log::error("[Watermark] Erreur sur {$media->file_name} : " . $e->getMessage(), [
                'order' => $order->reference,
            ]);
            if ($tmpOutput && file_exists($tmpOutput)) {
                @unlink($tmpOutput);
            }
            return false;
        }
    }

    /**
     * Applique un filigrane "OmnyRestore" répété en diagonale.
     */
    private static function applyWatermarkToImage(
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

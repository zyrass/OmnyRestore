<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Job: GenerateOrderZipJob
 *
 * Crée une archive ZIP des photos restaurées pour une commande payée.
 * Déclenché automatiquement par l'OrderObserver quand le statut passe à PAID.
 *
 * Flow :
 *   1. Récupère toutes les médias 'retouched' de la commande
 *   2. Télécharge chaque fichier depuis le disk configuré (local ou S3)
 *   3. Crée un ZIP en mémoire / disque temporaire
 *   4. Sauvegarde le ZIP dans Storage::disk('local') → storage/app/orders/zips/
 *   5. Met à jour la commande avec le chemin du ZIP et le statut DELIVERED
 *
 * Securité :
 *   - Le ZIP est stocké en dehors du dossier public (non accessible directement)
 *   - Le téléchargement est servi via OrderDownloadController avec une URL signée
 *   - Le ZIP est supprimé automatiquement après 90 jours (via cleanup job futur)
 *
 * Retry : 3 tentatives avec backoff de 60s entre chaque
 *
 * @see App\Http\Controllers\Client\OrderDownloadController
 * @see App\Observers\OrderObserver
 */
class GenerateOrderZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Nombre max de tentatives si le job échoue */
    public int $tries = 3;

    /** Délai entre les tentatives (secondes) */
    public int $backoff = 60;

    /** Timeout maximum pour ce job (secondes) — les ZIPs peuvent être lourds */
    public int $timeout = 300;

    public function __construct(public readonly Order $order) {}

    /**
     * Point d'entrée du job — exécuté par le worker de queue
     */
    public function handle(): void
    {
        $order = $this->order->fresh()->load('media');

        Log::info("GenerateOrderZipJob: starting for order {$order->reference}", [
            'order_id' => $order->id,
        ]);

        // Récupérer uniquement les photos actives (non rejetées par le client)
        $retouchedMedia = $order->getMedia('retouched')
            ->filter(fn($m) => ! $m->getCustomProperty('is_rejected', false));

        if ($retouchedMedia->isEmpty()) {
            Log::warning("GenerateOrderZipJob: no active retouched media for {$order->reference} (all rejected?)");
            return;
        }

        // ── Préparer le chemin du ZIP ──────────────────────────────────────
        $zipDir      = storage_path('app/orders/zips');
        $zipFilename = "omnyrestore_{$order->reference}_" . now()->format('Ymd_His') . '.zip';
        $zipPath     = "{$zipDir}/{$zipFilename}";

        // Créer le répertoire si nécessaire
        if (! is_dir($zipDir)) {
            mkdir($zipDir, 0755, true);
        }

        // ── Créer le ZIP ───────────────────────────────────────────────────
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Impossible de créer le ZIP : {$zipPath}");
        }

        // Ajouter un fichier README dans le ZIP
        $readmeContent = $this->buildReadme($order);
        $zip->addFromString('README.txt', $readmeContent);

        // Ajouter chaque photo restaurée
        $addedCount = 0;
        foreach ($retouchedMedia as $media) {
            try {
                // Récupérer le contenu depuis le disk Spatie (local ou S3)
                $contents = file_get_contents($media->getPath());
                if ($contents === false) {
                    Log::warning("GenerateOrderZipJob: cannot read {$media->file_name}");
                    continue;
                }

                // Nom propre sans le préfixe "restored_"
                $filename = ltrim(str_replace('restored_', '', $media->file_name), '_');

                $zip->addFromString("photos/{$filename}", $contents);
                $addedCount++;

            } catch (\Throwable $e) {
                Log::warning("GenerateOrderZipJob: error adding {$media->file_name}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $zip->close();

        if ($addedCount === 0) {
            unlink($zipPath);
            throw new \RuntimeException("Aucune photo n'a pu être ajoutée au ZIP pour {$order->reference}");
        }

        Log::info("GenerateOrderZipJob: ZIP created with {$addedCount} photos", [
            'zip_path' => $zipPath,
            'size'     => number_format(filesize($zipPath) / 1024 / 1024, 2) . ' MB',
        ]);

        // ── Mettre à jour la commande ──────────────────────────────────────
        // ⚠️ 'status' est EXCLU de $fillable → update() l'ignorerait silencieusement.
        // On utilise forceFill() pour les champs protégés, update() pour les autres.
        $order->update([
            'zip_path'       => "orders/zips/{$zipFilename}",
            'zip_expires_at' => now()->addDays(90),
            'delivered_at'   => now(),
        ]);

        // Créer l'entrée de livraison officielle pour le suivi (download_count)
        $order->delivery()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'zip_disk' => config('filesystems.default', 'local'),
                'zip_path' => "orders/zips/{$zipFilename}",
                'zip_size' => filesize($zipPath),
            ]
        );

        // Transition vers DELIVERED via forceFill (déclenche l'OrderObserver → email)
        $order->forceFill(['status' => 'DELIVERED'])->save();

        Log::info("GenerateOrderZipJob: order {$order->reference} marked DELIVERED");
    }

    /**
     * Gestion des échecs définitifs (après toutes les tentatives)
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("GenerateOrderZipJob: FAILED for order {$this->order->reference}", [
            'error'    => $exception->getMessage(),
            'order_id' => $this->order->id,
        ]);

        // TODO: Notification admin en cas d'échec critique
    }

    /**
     * Génère un fichier README.txt lisible pour le client
     */
    private function buildReadme(Order $order): string
    {
        return <<<TXT
╔══════════════════════════════════════════════════════════════╗
║              OmnyRestore — Restauration photographique       ║
╚══════════════════════════════════════════════════════════════╝

Commande   : {$order->reference}
Photos     : {$order->photo_count} photo(s) restaurée(s)
Niveau     : {$order->damage_level}
Date       : {$order->delivered_at?->format('d/m/Y à H:i')}

──────────────────────────────────────────────────────────────

Vos photos se trouvent dans le dossier "photos/" de cette archive.

Conseils de conservation :
  • Sauvegardez vos photos sur un support externe (disque dur, clé USB)
  • Faites une copie sur un service cloud personnel (iCloud, Google Photos...)
  • Ce lien de téléchargement expire dans 90 jours

Contact : contact@omnyrestore.fr

Merci pour votre confiance — OmnyRestore
TXT;
    }
}

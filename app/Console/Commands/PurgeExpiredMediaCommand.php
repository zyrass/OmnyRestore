<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Commande : PurgeExpiredMediaCommand
 *
 * Commande planifiée qui supprime automatiquement les photos restaurées depuis S3
 * après la période de rétention légale imposée par le RGPD (6 mois après livraison).
 *
 * RGPD Article 5(1)(e) — Limitation de la conservation :
 *   « Les données à caractère personnel doivent être conservées sous une forme permettant
 *    l'identification des personnes concernées pendant une durée n'excédant pas celle nécessaire
 *    au regard des finalités pour lesquelles elles sont traitées. »
 *
 *   Politique de rétention OmnyRestore (documentée dans la Politique de confidentialité) :
 *   - Photos (originales + restaurées) : supprimées 6 mois après delivered_at
 *   - Données de commande (méta) : conservées 10 ans (droit comptable français — L.123-22 C.com)
 *   - Logs d'audit : conservés 12 mois minimum (recommandation NIS2)
 *
 * Planification : Exécutée chaque jour à 02h00 (configurée dans routes/console.php)
 * Utilisation : php artisan media:purge-expired (déclenchement manuel pour tests)
 *
 * Ce qui est supprimé :
 *   - Fichiers S3 de la collection 'originals'
 *   - Fichiers S3 de la collection 'retouched'
 *   - Fichiers S3 de la collection 'watermarked'
 *   - L'archive ZIP (order_deliveries.zip_path)
 *   - Tous les enregistrements Spatie Media Library correspondants
 *
 * Ce qui n'est PAS supprimé :
 *   - L'enregistrement Order (rétention comptable)
 *   - Les logs d'audit (rétention réglementaire)
 *   - Le compte utilisateur (géré séparément via le flux d'effacement RGPD)
 *
 * @see routes/console.php pour la planification
 */
class PurgeExpiredMediaCommand extends Command
{
    /**
     * Signature de la commande (utilisée depuis le CLI ou le planificateur).
     * @var string
     */
    protected $signature = 'media:purge-expired
                            {--dry-run : Affiche ce qui serait supprimé sans effectuer de suppression réelle}';

    /**
     * Description affichée par : php artisan list
     * @var string
     */
    protected $description = 'Supprime les photos restaurées depuis S3 plus de 6 mois après livraison (conformité RGPD)';

    /**
     * Exécute la commande.
     *
     * @return int 0 si succès, 1 si échec (utilisé par la CI pour détecter les erreurs)
     */
    public function handle(): int
    {
        $dryRun     = $this->option('dry-run');
        $cutoffDate = now()->subMonths(6);

        $this->info("🗑️  Purge RGPD des médias — date de coupure : {$cutoffDate->toDateString()}");

        if ($dryRun) {
            $this->warn('  [DRY RUN] Aucun fichier ne sera réellement supprimé.');
        }

        // Recherche des commandes livrées depuis plus de 6 mois qui ont encore des médias attachés
        $orders = Order::whereNotNull('delivered_at')
                        ->where('delivered_at', '<', $cutoffDate)
                        ->whereHas('media') // Uniquement les commandes qui ont encore des médias
                        ->with(['media', 'delivery'])
                        ->get();

        if ($orders->isEmpty()) {
            $this->info('  ✅ Aucun média expiré trouvé. Rien à purger.');
            return self::SUCCESS;
        }

        $this->info("  {$orders->count()} commande(s) avec des médias expirés trouvée(s).");
        $this->newLine();

        $totalDeleted = 0;

        foreach ($orders as $order) {
            $this->line("  → Commande {$order->reference} (livrée le {$order->delivered_at->toDateString()})");

            if (! $dryRun) {
                // Suppression de tous les éléments Spatie Media Library (retire les fichiers S3 + enregistrements DB)
                $mediaCount = $order->media->count();
                $order->clearMediaCollection('originals');
                $order->clearMediaCollection('retouched');
                $order->clearMediaCollection('watermarked');

                // Suppression du fichier ZIP depuis S3 (s'il existe)
                $zipPathToPurge = $order->zip_path ?? ($order->delivery ? $order->delivery->zip_path : null);
                
                if ($zipPathToPurge) {
                    $disk = config('filesystems.default', 'local');
                    if ($order->delivery && $order->delivery->zip_disk) {
                        $disk = $order->delivery->zip_disk;
                    }
                    
                    Storage::disk($disk)->delete($zipPathToPurge);

                    $order->update(['zip_path' => null]);
                    if ($order->delivery) {
                        $order->delivery->update([
                            'zip_path'  => null,
                            'signed_url'=> null,
                        ]);
                    }

                    $this->line("     ✅ {$mediaCount} fichier(s) média + archive ZIP supprimés");
                } else {
                    $this->line("     ✅ {$mediaCount} fichier(s) média supprimés (aucun ZIP trouvé)");
                }

                $totalDeleted++;
            } else {
                // Mode simulation : affiche ce qui serait supprimé
                $mediaCount = $order->media->count();
                $this->line("     [DRY RUN] Supprimerait {$mediaCount} fichier(s) média" .
                    ($order->delivery ? ' + archive ZIP' : ''));
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn("  [DRY RUN] Aurait purgé {$orders->count()} commande(s). Relancer sans --dry-run pour appliquer.");
        } else {
            $this->info("  ✅ Purge réussie : {$totalDeleted} commande(s) nettoyée(s).");
        }

        return self::SUCCESS;
    }
}

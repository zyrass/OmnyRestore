<?php

namespace App\Console\Commands;

use App\Jobs\GenerateWatermarkJob;
use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Command: watermarks:regenerate
 *
 * Génère ou régénère les aperçus filigranés pour les commandes.
 *
 * Usage :
 *   php artisan watermarks:regenerate              → toutes les commandes DONE/PAID/DELIVERED
 *   php artisan watermarks:regenerate --order=ORD-2026-0001  → une commande spécifique
 *   php artisan watermarks:regenerate --sync       → synchrone (pas de queue)
 */
class RegenerateWatermarks extends Command
{
    protected $signature   = 'watermarks:regenerate
                              {--order= : Référence d\'une commande spécifique}
                              {--sync   : Exécuter de manière synchrone (sans queue)}';

    protected $description = 'Génère les aperçus filigranés pour les commandes avec photos retouchées';

    public function handle(): int
    {
        $this->info('🖼️  Génération des watermarks...');

        if ($ref = $this->option('order')) {
            $orders = Order::where('reference', $ref)->get();
            if ($orders->isEmpty()) {
                $this->error("Commande introuvable : {$ref}");
                return self::FAILURE;
            }
        } else {
            // Toutes les commandes qui ont des photos retouchées
            $orders = Order::whereIn('status', ['DONE', 'PAID', 'DELIVERED'])
                ->whereHas('media', fn ($q) => $q->where('collection_name', 'retouched'))
                ->get();
        }

        $this->info("📦 {$orders->count()} commande(s) à traiter.");

        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();

        foreach ($orders as $order) {
            if ($this->option('sync')) {
                GenerateWatermarkJob::dispatchSync($order);
            } else {
                GenerateWatermarkJob::dispatch($order);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $mode = $this->option('sync') ? 'traitées' : 'mises en queue';
        $this->info("✅  {$orders->count()} commande(s) {$mode}.");

        return self::SUCCESS;
    }
}

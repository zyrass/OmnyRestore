<?php

namespace App\Console\Commands;

use App\Jobs\AutoRestoreOrderPhotosJob;
use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Command: photos:restore
 *
 * Déclenche la restauration automatique IA pour une ou plusieurs commandes.
 *
 * Usage :
 *   php artisan photos:restore --order=ORD-2026-0002          → une commande (queue)
 *   php artisan photos:restore --order=ORD-2026-0002 --sync   → synchrone (bloquant)
 *   php artisan photos:restore                                 → toutes les commandes IN_PROGRESS
 *   php artisan photos:restore --all                           → toutes commandes avec originals
 *
 * Prérequis :
 *   - OPENAI_API_KEY configurée dans .env
 *   - Extension GD activée (pour upscale 8K)
 *   - Queue worker actif (sauf avec --sync)
 */
class AutoRestorePhotos extends Command
{
    protected $signature = 'photos:restore
                            {--order= : Référence d\'une commande spécifique (ex: ORD-2026-0002)}
                            {--all    : Traiter toutes les commandes avec des originals (pas seulement IN_PROGRESS)}
                            {--sync   : Exécuter de manière synchrone (bloquant, pour debug)}';

    protected $description = 'Restaure automatiquement les photos via GPT-4o + DALL-E 3 + upscale 8K';

    public function handle(): int
    {
        // Vérifier la clé API
        if (! config('openai.api_key')) {
            $this->error('❌ OPENAI_API_KEY non configurée dans .env');
            return self::FAILURE;
        }

        $this->info('🤖 Restauration automatique IA — OmnyRestore');
        $this->info('   Modèles : GPT-4o Vision + DALL-E 3 HD + Upscale 8K');
        $this->newLine();

        // Sélection des commandes
        if ($ref = $this->option('order')) {
            $orders = Order::where('reference', $ref)->get();
            if ($orders->isEmpty()) {
                $this->error("Commande introuvable : {$ref}");
                return self::FAILURE;
            }
        } elseif ($this->option('all')) {
            $orders = Order::whereHas('media', fn ($q) => $q->where('collection_name', 'originals'))->get();
        } else {
            // Par défaut : commandes IN_PROGRESS avec des originals
            $orders = Order::where('status', 'IN_PROGRESS')
                ->whereHas('media', fn ($q) => $q->where('collection_name', 'originals'))
                ->get();
        }

        if ($orders->isEmpty()) {
            $this->warn('Aucune commande éligible trouvée.');
            return self::SUCCESS;
        }

        $mode = $this->option('sync') ? '<fg=yellow>SYNCHRONE</>' : '<fg=green>QUEUE</>';
        $this->info("📦 {$orders->count()} commande(s) — Mode : {$mode}");
        $this->newLine();

        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();

        foreach ($orders as $order) {
            $originalCount = $order->getMedia('originals')->count();
            $this->newLine();
            $this->line("  → {$order->reference} ({$originalCount} photo(s))");

            if ($this->option('sync')) {
                AutoRestoreOrderPhotosJob::dispatchSync($order);
            } else {
                AutoRestoreOrderPhotosJob::dispatch($order);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $verb = $this->option('sync') ? 'traitées' : 'mises en queue';
        $this->info("✅ {$orders->count()} commande(s) {$verb}.");

        if (! $this->option('sync')) {
            $this->comment('ℹ️  Lance le worker pour traiter la queue : php artisan queue:listen --timeout=600');
        }

        return self::SUCCESS;
    }
}

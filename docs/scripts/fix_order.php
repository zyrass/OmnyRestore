<?php
/**
 * Script de correction d'urgence pour le prix d'une commande OmnyRestore.
 * 
 * Si un bug de logique ou un mauvais mapping a figé un prix incorrect 
 * (ex: 19.00€ au lieu de 18.00€), ce script permet de forcer la valeur 
 * directement en base de données.
 * 
 * Grâce à la mise à jour de `Order.php`, le système respecte désormais 
 * STRICTEMENT ce champ `total_price_cents`.
 */

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Cibler la commande problématique. 
// Remplacer 'latest()->first()' par 'find("UUID")' pour une commande précise.
$order = App\Models\Order::latest('id')->first();
echo "========================================\n";
echo "Correction de la commande ID: {$order->id}\n";
echo "========================================\n";

// Affichage de l'état actuel avant modification
echo "Prix actuel en base (total_price_cents): {$order->total_price_cents}\n";
echo "Total théorique (originaux) : " . $order->getMedia('originals')->sum(fn($m) => App\Services\PhotoDamageAnalyzer::PRICES_TTC[$m->getCustomProperty('ai_level', 'light')] ?? 100) . "\n";
echo "Total théorique (retouchées): " . $order->getMedia('retouched')->sum(fn($m) => App\Services\PhotoDamageAnalyzer::PRICES_TTC[$m->getCustomProperty('ai_level', 'light')] ?? 100) . "\n";

echo "----------------------------------------\n";

// --- MODIFICATION DU PRIX ---
// Définir ici le prix final souhaité en CENTIMES (ex: 1800 pour 18.00€)
$nouveauPrixCents = 1800; 

$order->total_price_cents = $nouveauPrixCents;
$order->save();

echo "=> SUCCÈS : total_price_cents forcé à {$nouveauPrixCents} centimes.\n";
echo "========================================\n";


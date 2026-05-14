<?php
/**
 * Script d'inspection des médias (Spatie Media Library) pour une commande OmnyRestore.
 * 
 * Ce script est utile pour le débogage (ex: incohérence de prix ou de niveau IA).
 * Il affiche les ID, Noms, Noms de fichiers et Niveaux IA des photos
 * "originales" (uploadées par le client) et "retouchées" (uploadées par l'admin).
 */

// Initialisation de l'environnement Laravel en CLI
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Récupération de la dernière commande générée (ou spécifiez l'ID souhaité via find())
// Exemple pour une commande précise : $order = App\Models\Order::find('votre-uuid-ici');
$order = App\Models\Order::latest('id')->first();
echo "========================================\n";
echo "Inspection de la commande ID: " . $order->id . "\n";
echo "========================================\n\n";

// --- SECTION ORIGINAUX ---
// Affiche les photos téléchargées par le client avant traitement.
echo "--- PHOTOS ORIGINALES (Uploadées par le client) ---\n";
foreach ($order->getMedia('originals') as $idx => $m) {
    // getCustomProperty('ai_level') retourne le tarif déterminé par l'IA (light, medium, heavy)
    echo "[$idx] ID:{$m->id} | Name:{$m->name} | File:{$m->file_name} | IA Level: " . ($m->getCustomProperty('ai_level') ?? 'Non défini') . "\n";
}

echo "\n";

// --- SECTION RETOUCHÉES ---
// Affiche les photos restaurées par l'admin.
// Utile pour vérifier si les 'ai_level' ont bien été propagés depuis les originaux.
echo "--- PHOTOS RETOUCHÉES (Uploadées par l'admin) ---\n";
foreach ($order->getMedia('retouched') as $idx => $m) {
    echo "[$idx] ID:{$m->id} | Name:{$m->name} | File:{$m->file_name} | IA Level: " . ($m->getCustomProperty('ai_level') ?? 'Non défini') . "\n";
}
echo "\n========================================\n";

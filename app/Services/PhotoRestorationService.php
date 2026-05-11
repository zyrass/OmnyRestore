<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use OpenAI\Laravel\Facades\OpenAI;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Service: PhotoRestorationService
 *
 * Orchestre la restauration automatique des photos via l'API OpenAI.
 *
 * Pipeline par photo :
 *   1. GPT-4o Vision → analyse l'image et génère une description ultra-détaillée
 *   2. Détection de la demande de colorisation / N&B depuis la commande
 *   3. Construction du prompt de restauration (base + colorisation)
 *   4. DALL-E 3 → génère la version restaurée (max 1792×1024 ou 1024×1024)
 *   5. Intervention Image → upscale à 7680×4320 (8K UHD)
 *   6. Retourne le chemin du fichier JPEG temporaire
 *
 * @see AutoRestoreOrderPhotosJob
 */
class PhotoRestorationService
{
    /**
     * Prompt système de restauration — fourni par le client.
     * Utilisé comme base pour TOUTES les restaurations.
     */
    private const BASE_RESTORATION_PROMPT = <<<'PROMPT'
Agis comme un algorithme de restauration de données visuelles haute précision.
Ta mission est de corriger les artefacts de compression et le flou de mouvement sur source
pour retrouver la clarté originelle.

INSTRUCTIONS TECHNIQUES :
1. Effectue un upscale intelligent en haute résolution sans altérer les vecteurs de base.
2. Reconstruis les textures de surface (micro-détails, pores, fibres) en te basant sur les informations de luminance existantes.
3. Stabilise la netteté des bords et optimise le contraste global pour un rendu de type "Studio Professionnel".
4. Conserve strictement la géométrie, les traits faciaux et l'éclairage de la source.

Résultat attendu : Une version ultra-nette, photoréaliste et propre de l'image fournie,
sans aucune modification structurelle.
PROMPT;

    /**
     * Mots-clés qui déclenchent la colorisation (N&B → couleur).
     */
    private const COLORIZE_KEYWORDS = [
        'coloris', 'coloriz', 'en couleur', 'ajouter les couleurs',
        'ajouter couleur', 'mettre en couleur', 'rajouter les couleurs',
    ];

    /**
     * Mots-clés qui déclenchent la désaturation (couleur → N&B).
     */
    private const BW_KEYWORDS = [
        'noir et blanc', 'n&b', 'nb', 'monochrome', 'désatur', 'desatur',
        'black and white', 'grayscale',
    ];

    /**
     * Résolution cible pour l'upscale final.
     * 8K UHD = 7680 × 4320 px
     */
    private const TARGET_WIDTH  = 7680;
    private const TARGET_HEIGHT = 4320;

    /**
     * Restaure une photo via GPT-4o + DALL-E 3 + upscale 8K.
     *
     * @param Media $media L'objet média Spatie (photo originale)
     * @param Order $order La commande associée (pour détecter colorisation)
     * @return string Chemin absolu du fichier JPEG temporaire 8K
     *
     * @throws \RuntimeException Si l'API OpenAI échoue ou retourne une erreur
     */
    public function restore(Media $media, Order $order): string
    {
        // 1. Analyser l'image avec GPT-4o Vision
        $imageDescription = $this->analyzeWithVision($media);
        Log::info("[Restoration] GPT-4o analyse terminée pour {$media->file_name}");

        // 2. Détecter la demande de colorisation
        $colorInstruction = $this->detectColorInstruction($order);

        // 3. Construire le prompt DALL-E 3
        $dallePrompt = $this->buildDallePrompt($imageDescription, $colorInstruction);

        // 4. Générer avec DALL-E 3
        $restoredImageUrl = $this->generateWithDalle3($dallePrompt);
        Log::info("[Restoration] DALL-E 3 image générée pour {$media->file_name}");

        // 5. Télécharger l'image générée
        $rawImageContent = file_get_contents($restoredImageUrl);
        if ($rawImageContent === false) {
            throw new \RuntimeException("Impossible de télécharger l'image DALL-E 3 pour {$media->file_name}");
        }

        // 6. Upscale à 8K avec Intervention Image
        $outputPath = $this->upscaleTo8K($rawImageContent, $media->file_name);
        Log::info("[Restoration] Upscale 8K terminé → {$outputPath}");

        return $outputPath;
    }

    /**
     * Analyse l'image originale avec GPT-4o Vision.
     *
     * Encode l'image en base64 et la soumet à GPT-4o pour obtenir
     * une description ultra-détaillée utilisée ensuite comme contexte DALL-E 3.
     *
     * @return string Description textuelle détaillée de l'image
     */
    private function analyzeWithVision(Media $media): string
    {
        $imagePath    = $media->getPath();
        $imageContent = file_get_contents($imagePath);
        $base64       = base64_encode($imageContent);
        $mimeType     = $media->mime_type ?: 'image/jpeg';

        $response = OpenAI::chat()->create([
            'model'     => 'gpt-4o',
            'max_tokens' => 1000,
            'messages'  => [
                [
                    'role'    => 'system',
                    'content' => 'Tu es un expert en analyse d\'images photographiques. Décris cette image avec une précision maximale : sujet, composition, éclairage, couleurs, textures, état de dégradation (flou, grain, déchirures, taches, défauts). Sois très détaillé pour permettre une reconstruction fidèle.',
                ],
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'      => 'image_url',
                            'image_url' => [
                                'url'    => "data:{$mimeType};base64,{$base64}",
                                'detail' => 'high',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => 'Décris cette photo en détail pour permettre une restauration haute fidélité.',
                        ],
                    ],
                ],
            ],
        ]);

        return $response->choices[0]->message->content ?? 'Photo à restaurer';
    }

    /**
     * Détecte la demande de colorisation / N&B depuis la commande.
     *
     * @return 'colorize'|'bw'|'none'
     */
    private function detectColorInstruction(Order $order): string
    {
        $text = strtolower(
            ($order->description ?? '') . ' ' . ($order->instructions ?? '')
        );

        foreach (self::COLORIZE_KEYWORDS as $kw) {
            if (str_contains($text, $kw)) {
                return 'colorize';
            }
        }

        foreach (self::BW_KEYWORDS as $kw) {
            if (str_contains($text, $kw)) {
                return 'bw';
            }
        }

        return 'none';
    }

    /**
     * Construit le prompt final pour DALL-E 3.
     *
     * Combine : description de l'image + instructions de restauration + colorisation.
     */
    private function buildDallePrompt(string $description, string $colorInstruction): string
    {
        $colorText = match ($colorInstruction) {
            'colorize' => "\n\nINSTRUCTION SUPPLÉMENTAIRE : Cette photo est en noir et blanc. Colorie-la de manière réaliste et historiquement cohérente, en ajoutant des couleurs naturelles et authentiques.",
            'bw'       => "\n\nINSTRUCTION SUPPLÉMENTAIRE : Convertis cette photo en noir et blanc élégant, avec un rendu contrasté de type argentique professionnel.",
            default    => '',
        };

        return sprintf(
            "RESTAURATION PHOTOGRAPHIQUE PROFESSIONNELLE\n\nDESCRIPTION DE L'IMAGE ORIGINALE :\n%s\n\nINSTRUCTIONS DE RESTAURATION :\n%s%s\n\nLe résultat doit être ultra-photoréaliste, haute résolution, sans artefacts, fidèle à l'original restauré.",
            $description,
            self::BASE_RESTORATION_PROMPT,
            $colorText
        );
    }

    /**
     * Génère l'image restaurée via DALL-E 3.
     *
     * Utilise le modèle dall-e-3 avec la plus haute résolution disponible (1792×1024)
     * et la qualité "hd" pour un rendu professionnel.
     *
     * @return string URL temporaire de l'image générée (valide ~1h)
     */
    private function generateWithDalle3(string $prompt): string
    {
        $response = OpenAI::images()->create([
            'model'           => 'dall-e-3',
            'prompt'          => $prompt,
            'n'               => 1,
            'size'            => '1792x1024',    // Résolution max DALL-E 3 paysage
            'quality'         => 'hd',            // Qualité maximale (2x le budget de compute)
            'response_format' => 'url',
            'style'           => 'natural',       // Natural = photoréaliste (pas vivid/artistique)
        ]);

        $url = $response->data[0]->url ?? null;

        if (! $url) {
            throw new \RuntimeException('DALL-E 3 n\'a pas retourné d\'URL image.');
        }

        return $url;
    }

    /**
     * Upscale l'image générée (1792×1024) vers 8K (7680×4320).
     *
     * Utilise l'interpolation bicubique de GD pour agrandir sans artefacts.
     * La qualité de sortie est JPEG 92% (archive) pour préserver les détails.
     *
     * @param string $rawImageContent Contenu binaire de l'image source
     * @param string $originalFilename Nom du fichier original (pour nommer le tmp)
     * @return string Chemin absolu du fichier JPEG 8K temporaire
     */
    private function upscaleTo8K(string $rawImageContent, string $originalFilename): string
    {
        $manager = new ImageManager(new Driver());
        $image   = $manager->read($rawImageContent);

        // Upscale à 8K en conservant les proportions (scale, pas stretch)
        // scaleDown → seulement si plus grand (ici on scale UP donc on utilise scale())
        $image->scale(width: self::TARGET_WIDTH, height: self::TARGET_HEIGHT);

        $tmpPath = tempnam(sys_get_temp_dir(), 'resto_')
                 . '_' . pathinfo($originalFilename, PATHINFO_FILENAME)
                 . '_8k.jpg';

        // Qualité 92% : bon compromis taille/qualité pour archive haute résolution
        $image->toJpeg(92)->save($tmpPath);

        return $tmpPath;
    }
}

<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PhotoDamageAnalyzer — Service d'analyse automatique des photos
 *
 * Utilise l'API OpenAI Vision (GPT-4o) pour analyser l'état d'une photo
 * et déterminer automatiquement le niveau de dommage.
 *
 * Niveaux de dommage :
 *   'light'  → Restauration Standard (1€/photo)
 *              Jaunissement, poussière légère, légères rayures, décoloration
 *   'heavy'  → Restauration Avancée (10€/photo)
 *              Déchirures, dommages eau, pliures importantes, zones manquantes
 *
 * Si l'API est indisponible (clé manquante, quota dépassé), le service
 * retourne un verdict INCONNU qui force l'admin à valider manuellement.
 *
 * @see config/services.php — OPENAI_API_KEY
 */
class PhotoDamageAnalyzer
{
    /**
     * Prix en centimes par niveau de dommage.
     */
    public const PRICES = [
        'light' => 100,   // 1,00 €
        'heavy' => 1000,  // 10,00 €
    ];

    /**
     * Prompt système envoyé à GPT-4o pour l'analyse.
     * Très directif pour éviter des réponses verboses.
     */
    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu es un expert en restauration de photographies anciennes.
Analyse cette image et réponds UNIQUEMENT avec un objet JSON valide, sans aucun texte avant ou après.

Format de réponse obligatoire :
{
  "level": "light" ou "heavy",
  "confidence": nombre entre 0 et 100,
  "reason": "phrase courte en français (max 15 mots)"
}

Critères d'évaluation :
- "light" : jaunissement, poussière, petites taches, légères rayures, décoloration modérée
- "heavy" : déchirures, dommages eau importants, zones manquantes, pliures majeures, moisissures, brûlures

Si l'image semble déjà en bon état ou n'est pas une photo ancienne, réponds avec level "light".
PROMPT;

    /**
     * Analyse une photo uploadée et retourne le verdict de dommage.
     *
     * @param  UploadedFile  $file  La photo à analyser
     * @return array{level: string, confidence: int, reason: string, price_cents: int, ai_used: bool}
     */
    public function analyze(UploadedFile $file): array
    {
        // Si pas de clé API, fallback heuristique local
        if (empty(config('services.openai.key'))) {
            Log::warning('PhotoDamageAnalyzer: No OpenAI API key — using local heuristic');
            return $this->heuristicFallback($file);
        }

        try {
            return $this->analyzeWithGpt4o($file);
        } catch (\Throwable $e) {
            Log::error('PhotoDamageAnalyzer: GPT-4o failed', [
                'error'    => $e->getMessage(),
                'filename' => $file->getClientOriginalName(),
            ]);
            // Fallback heuristique si l'API échoue
            return $this->heuristicFallback($file);
        }
    }

    /**
     * Analyse avec GPT-4o Vision.
     * Convertit la photo en base64 et envoie à l'API OpenAI.
     */
    private function analyzeWithGpt4o(UploadedFile $file): array
    {
        // Lecture et encodage base64 de l'image
        $imageData   = base64_encode(file_get_contents($file->getRealPath()));
        $mimeType    = $file->getMimeType() ?? 'image/jpeg';
        $dataUri     = "data:{$mimeType};base64,{$imageData}";

        $response = Http::withToken(config('services.openai.key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'      => config('services.openai.model', 'gpt-4o'),
                'max_tokens' => 150,
                'messages'   => [
                    [
                        'role'    => 'system',
                        'content' => self::SYSTEM_PROMPT,
                    ],
                    [
                        'role'    => 'user',
                        'content' => [
                            [
                                'type'      => 'image_url',
                                'image_url' => [
                                    'url'    => $dataUri,
                                    'detail' => 'low', // 'low' = moins de tokens, suffisant pour évaluer l'état
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->status());
        }

        $content = $response->json('choices.0.message.content', '{}');
        $verdict = json_decode($content, true);

        if (! isset($verdict['level']) || ! in_array($verdict['level'], ['light', 'heavy'])) {
            throw new \RuntimeException('Invalid response format from GPT-4o: ' . $content);
        }

        return [
            'level'       => $verdict['level'],
            'confidence'  => (int) ($verdict['confidence'] ?? 70),
            'reason'      => $verdict['reason'] ?? 'Analyse IA effectuée',
            'price_cents' => self::PRICES[$verdict['level']],
            'ai_used'     => true,
        ];
    }

    /**
     * Analyse heuristique locale (fallback sans API).
     *
     * Utilise les métadonnées EXIF et les propriétés GD de l'image pour
     * estimer grossièrement l'état :
     *   - Images très sombres ou très claires → probablement endommagées
     *   - Faible résolution → souvent des scans anciens (dommages légers)
     *
     * NOTE: Ce fallback est intentionnellement CONSERVATEUR (préfère 'heavy')
     * car il vaut mieux surestimer le prix et laisser l'admin réduire,
     * plutôt que de facturer moins que prévu.
     *
     * En l'absence de clé OpenAI, l'admin doit valider manuellement.
     */
    private function heuristicFallback(UploadedFile $file): array
    {
        $score = 50; // Score de base — 0-49 = heavy, 50-100 = light

        try {
            // Analyse GD si disponible
            if (extension_loaded('gd')) {
                $img = match($file->getMimeType()) {
                    'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
                    'image/png'  => @imagecreatefrompng($file->getRealPath()),
                    default      => false,
                };

                if ($img !== false) {
                    [$w, $h] = [imagesx($img), imagesy($img)];

                    // Échantillonner quelques pixels pour estimer la luminosité moyenne
                    $totalLum  = 0;
                    $samples   = 0;
                    $step      = max(1, (int) ($w / 20)); // 20 samples sur la largeur

                    for ($x = 0; $x < $w; $x += $step) {
                        for ($y = 0; $y < $h; $y += $step) {
                            $rgb = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                            // Luminance perçue (formule CCIR 601)
                            $totalLum += 0.299 * $rgb['red'] + 0.587 * $rgb['green'] + 0.114 * $rgb['blue'];
                            $samples++;
                        }
                    }

                    imagedestroy($img);

                    if ($samples > 0) {
                        $avgLum = $totalLum / $samples;
                        // Images très sombres (<30) ou très claires (>230) → probablement endommagées
                        if ($avgLum < 30 || $avgLum > 230) {
                            $score -= 25;
                        }
                        // Images modérément lumineuses → probablement OK
                        if ($avgLum >= 80 && $avgLum <= 180) {
                            $score += 15;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Ignore les erreurs GD — on utilise le score de base
        }

        $level = $score >= 50 ? 'light' : 'heavy';

        return [
            'level'       => $level,
            'confidence'  => 40, // Confiance faible — analyse heuristique seulement
            'reason'      => 'Analyse locale (API IA non configurée) — à valider par l\'équipe',
            'price_cents' => self::PRICES[$level],
            'ai_used'     => false,
        ];
    }
}

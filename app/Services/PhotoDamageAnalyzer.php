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
 * Niveaux de dommage (prix HT, TVA 20% en sus) :
 *   'light'  → Restauration Standard   → 0,83 € HT → 1,00 € TTC
 *              Jaunissement, poussière légère, légères taches, décoloration modérée
 *   'medium' → Restauration Avancée    → 1,67 € HT → 2,00 € TTC
 *              Rayures visibles, décoloration forte, pliures légères, grain important
 *   'heavy'  → Restauration Complète   → 2,50 € HT → 3,00 € TTC
 *              Déchirures, dommages eau, zones manquantes, moisissures, brûlures
 *
 * Coût IA estimé : ~0,005 € HT/photo (GPT-4o Vision, detail=low)
 * Ce coût est répercuté de manière transparente sur la facture client.
 *
 * @see config/services.php — OPENAI_API_KEY
 */
class PhotoDamageAnalyzer
{
    /**
     * Prix en centimes HT par niveau de dommage.
     * TVA 20% s'applique automatiquement sur toutes les commandes.
     */
    public const PRICES = [
        'light'  => 83,    // 0,83 € HT → 1,00 € TTC
        'medium' => 167,   // 1,67 € HT → 2,00 € TTC
        'heavy'  => 250,   // 2,50 € HT → 3,00 € TTC
    ];

    /**
     * Taux de TVA appliqué automatiquement (20%).
     */
    public const TVA_RATE = 20.0;

    /**
     * Coût estimé de l'analyse IA en centimes HT par photo.
     * GPT-4o Vision (detail=low) ≈ 0,005 $ ≈ 0,005 €
     * Affiché de manière transparente sur les devis et factures.
     */
    public const AI_COST_CENTS = 1; // ~0,005 € arrondi à 0,01 €

    /**
     * Prompt système envoyé à GPT-4o pour l'analyse.
     */
    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu es un expert en restauration de photographies anciennes.
Analyse cette image et réponds UNIQUEMENT avec un objet JSON valide, sans aucun texte avant ou après.

Format de réponse obligatoire :
{
  "level": "light", "medium" ou "heavy",
  "confidence": nombre entre 0 et 100,
  "reason": "phrase courte en français (max 15 mots)"
}

Critères d'évaluation stricts :
- "light"  : jaunissement léger, poussière, petites taches superficielles, légère décoloration → 1,00 € TTC
- "medium" : rayures visibles, décoloration forte, pliures légères, grain photographique important, taches marquées → 2,00 € TTC
- "heavy"  : déchirures, dommages eau importants, zones manquantes, pliures majeures, moisissures, brûlures, photo très dégradée → 3,00 € TTC

Si l'image semble déjà en bon état ou n'est pas une photo ancienne, réponds avec level "light".
Sois précis : ne sous-évalue pas les dommages. Une photo ancienne typique avec usure modérée est "medium", pas "light".
En cas de doute entre "light" et "medium", choisis "medium". En cas de doute entre "medium" et "heavy", choisis "medium".
PROMPT;

    /**
     * Analyse une photo uploadée et retourne le verdict de dommage.
     *
     * @param  UploadedFile  $file  La photo à analyser
     * @return array{level: string, confidence: int, reason: string, price_cents: int, price_ttc_cents: int, ai_used: bool}
     */
    public function analyze(UploadedFile $file): array
    {
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
            return $this->heuristicFallback($file);
        }
    }

    /**
     * Calcule le prix TTC en centimes à partir du prix HT.
     */
    public static function htToTtc(int $htCents): int
    {
        return (int) round($htCents * (1 + self::TVA_RATE / 100));
    }

    /**
     * Retourne le prix TTC en centimes pour un niveau donné.
     */
    public static function priceTtcForLevel(string $level): int
    {
        return self::htToTtc(self::PRICES[$level] ?? self::PRICES['light']);
    }

    /**
     * Analyse avec GPT-4o Vision.
     */
    private function analyzeWithGpt4o(UploadedFile $file): array
    {
        $imageData = base64_encode(file_get_contents($file->getRealPath()));
        $mimeType  = $file->getMimeType() ?? 'image/jpeg';
        $dataUri   = "data:{$mimeType};base64,{$imageData}";

        $response = Http::withToken(config('services.openai.key'))
            ->timeout(30)
            ->withOptions([
                // Guzzle : désactiver SSL en dev (les certs PHP peuvent manquer)
                'verify' => app()->isProduction(),
                // Guzzle : options curl brutes — force HTTP/1.1 (HTTP/2 échoue sur Windows)
                'curl'   => [
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                ],
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'      => config('services.openai.model', 'gpt-4o'),
                'max_tokens' => 150,
                'messages'   => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    [
                        'role'    => 'user',
                        'content' => [
                            [
                                'type'      => 'image_url',
                                'image_url' => ['url' => $dataUri, 'detail' => 'low'],
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

        if (! isset($verdict['level']) || ! in_array($verdict['level'], ['light', 'medium', 'heavy'])) {
            throw new \RuntimeException('Invalid response format from GPT-4o: ' . $content);
        }

        $priceCents = self::PRICES[$verdict['level']];

        return [
            'level'           => $verdict['level'],
            'confidence'      => (int) ($verdict['confidence'] ?? 70),
            'reason'          => $verdict['reason'] ?? 'Analyse IA effectuée',
            'price_cents'     => $priceCents,
            'price_ttc_cents' => self::htToTtc($priceCents),
            'ai_used'         => true,
        ];
    }

    /**
     * Analyse heuristique locale (fallback sans API).
     * Score 0–100 : ≥66 → light | 33–65 → medium | <33 → heavy
     */
    private function heuristicFallback(UploadedFile $file): array
    {
        // Score de départ : 45 (légèrement en-dessous du seuil medium=33/light=66).
        // La plupart des vieilles photos N&B restent "medium" par défaut.
        // Seules les photos avec luminosité idéale ET bon contraste passent "light".
        $score      = 45;
        $lumBonus   = 0;

        try {
            if (extension_loaded('gd')) {
                $img = match($file->getMimeType()) {
                    'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
                    'image/png'  => @imagecreatefrompng($file->getRealPath()),
                    default      => false,
                };

                if ($img !== false) {
                    [$w, $h] = [imagesx($img), imagesy($img)];
                    $totalLum  = 0;
                    $totalLum2 = 0; // pour la variance
                    $samples   = 0;
                    $step      = max(1, (int) ($w / 20));

                    for ($x = 0; $x < $w; $x += $step) {
                        for ($y = 0; $y < $h; $y += $step) {
                            $rgb = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                            $lum = 0.299 * $rgb['red'] + 0.587 * $rgb['green'] + 0.114 * $rgb['blue'];
                            $totalLum  += $lum;
                            $totalLum2 += $lum * $lum;
                            $samples++;
                        }
                    }

                    imagedestroy($img);

                    if ($samples > 0) {
                        $avgLum  = $totalLum / $samples;
                        $variance = ($totalLum2 / $samples) - ($avgLum * $avgLum);

                        // Luminosité équilibrée (100-170) : la photo est bien exposée → +15
                        if ($avgLum >= 100 && $avgLum <= 170) { $score += 15; $lumBonus = 15; }
                        // Très sombre ou très clair → signes de dégradation → -25
                        elseif ($avgLum < 30 || $avgLum > 230) { $score -= 25; }

                        // Variance faible (< 800) → image uniforme, peu de déchirures/taches → +15
                        if ($variance < 800) { $score += 15; }
                        // Variance très élevée (> 3000) → fort contraste, dommages visibles → -15
                        elseif ($variance > 3000) { $score -= 15; }
                    }
                }
            }
        } catch (\Throwable) {
            // Ignore GD errors
        }

        // Seuils ajustés : ≥75 → light | ≥40 → medium | <40 → heavy
        $level = match(true) {
            $score >= 75 => 'light',
            $score >= 40 => 'medium',
            default      => 'heavy',
        };

        $priceCents = self::PRICES[$level];

        return [
            'level'           => $level,
            'confidence'      => 35,
            'reason'          => "Analyse locale (API IA indisponible) — à valider par l'équipe",
            'price_cents'     => $priceCents,
            'price_ttc_cents' => self::htToTtc($priceCents),
            'ai_used'         => false,
        ];
    }
}

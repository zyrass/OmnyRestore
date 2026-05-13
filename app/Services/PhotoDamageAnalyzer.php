<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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
 * Résultat IA : amélioration significative de la netteté, des couleurs et des détails.
 * La qualité du résultat dépend de l'état initial de la photo et des capacités de l'IA.
 * Aucune résolution de sortie spécifique n'est garantie.
 *
 * @see config/services.php — OPENAI_API_KEY
 */
class PhotoDamageAnalyzer
{
    /**
     * Prix en centimes HT par niveau de dommage.
     * Ces valeurs sont calculées à rebours depuis le prix TTC voulu :
     *   light  : 1,00 € TTC → HT = round(100 / 1.20) = 83¢  (TTC réel : 100¢) ✓
     *   medium : 2,00 € TTC → HT = round(200 / 1.20) = 167¢ (TTC réel : 200¢) ✓
     *   heavy  : 3,00 € TTC → HT = round(300 / 1.20) = 250¢ (TTC réel : 300¢) ✓
     *
     * ⚠️ Pour éviter la perte d'1 centime lors d'une commande multi-niveaux,
     * le checkout calcule le TTC en sommant les prix TTC par photo (PRICES_TTC),
     * PAS en appliquant la TVA sur le total HT cumulé.
     */
    public const PRICES = [
        'light'  => 83,    // 0,83 € HT → 1,00 € TTC
        'medium' => 167,   // 1,67 € HT → 2,00 € TTC
        'heavy'  => 250,   // 2,50 € HT → 3,00 € TTC
    ];

    /**
     * Prix TTC exacts en centimes par niveau de dommage.
     * À utiliser pour calculer le montant facturé à Stripe (évite les arrondis).
     */
    public const PRICES_TTC = [
        'light'  => 100,   // 1,00 € TTC exact
        'medium' => 200,   // 2,00 € TTC exact
        'heavy'  => 300,   // 3,00 € TTC exact
    ];

    /**
     * Taux de TVA appliqué automatiquement (20%).
     */
    public const TVA_RATE = 20.0;

    /**
     * Coût estimé de l'analyse IA en centimes HT par photo.
     * Désactivé (0) pour ne pas polluer la facturation client/admin.
     */
    public const AI_COST_CENTS = 0;

    /**
     * Prompt système envoyé à GPT-4o pour l'analyse.
     */
    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu es un expert en restauration de photographies. Ton rôle est d'évaluer l'ETAT PHYSIQUE du support photographique uniquement.
Tu n'identifies AUCUNE personne. Tu analyses uniquement les dommages visibles sur la photo en tant que document.

Réponds UNIQUEMENT avec un objet JSON valide, sans aucun texte avant ou après, sans balises markdown.

Format obligatoire :
{"level": "light", "confidence": 85, "reason": "phrase courte en français max 15 mots"}

Critères stricts :
- "light"  : photo nette et peu endommagée, jaunissement léger, poussière, petites taches éparses → 1,00 € TTC
- "medium" : rayures nettes, décoloration forte, pliures visibles, grain important, bruit numérique, visages partiellement altérés → 2,00 € TTC
- "heavy"  : bords déchirés, coins manquants (perte de matière), déchirures, moisissures, brûlures, dégâts eau, visages fortement dégradés, nécessite une reconstruction importante de détails → 3,00 € TTC

Règles absolues :
- Si un dommage important touche un VISAGE ou le SUJET PRINCIPAL, privilégie le niveau supérieur.
- Si une photo présente des BORDS DÉCHIRÉS ou des COINS MANQUANTS, elle doit être classée en "heavy" (3,00 €).
- Si la photo est moderne et nette → "light"
- Si tu hésites entre deux niveaux, choisis TOUJOURS le niveau le plus élevé pour refléter la complexité du travail manuel requis.
- Si tu ne peux pas analyser l'image pour quelque raison que ce soit → réponds TOUJOURS {"level": "light", "confidence": 50, "reason": "Analyse non concluante, niveau standard appliqué"}
- Tu ne dois JAMAIS refuser de répondre ou écrire du texte libre
PROMPT;

    /**
     * Analyse une photo uploadée et retourne le verdict de dommage.
     *
     * Le résultat est mis en cache permanent par empreinte MD5 du fichier :
     * la même photo retournera toujours le même verdict, empêchant
     * les ré-uploads frauduleux pour obtenir un tarif plus bas.
     *
     * @param  UploadedFile  $file  La photo à analyser
     * @return array{level: string, confidence: int, reason: string, price_cents: int, price_ttc_cents: int, ai_used: bool}
     */
    public function analyze(UploadedFile $file): array
    {
        // ── Empreinte du fichier — même contenu = même hash = même résultat ──
        $hash     = md5_file($file->getRealPath());
        $cacheKey = 'photo_dmg_' . $hash;

        if (Cache::has($cacheKey)) {
            Log::info('PhotoDamageAnalyzer: cache hit', ['hash' => substr($hash, 0, 8)]);
            return Cache::get($cacheKey);
        }

        if (empty(config('services.openai.key'))) {
            Log::warning('PhotoDamageAnalyzer: No OpenAI API key — using local heuristic');
            // Le fallback heuristique n'est PAS mis en cache (moins fiable)
            return $this->heuristicFallback($file);
        }

        try {
            $result = $this->analyzeWithGpt4o($file);
            // Cache permanent — la photo ne changera jamais d'état
            Cache::forever($cacheKey, $result);
            return $result;
        } catch (\Throwable $e) {
            Log::error('PhotoDamageAnalyzer: GPT-4o failed', [
                'error'    => $e->getMessage(),
                'filename' => $file->getClientOriginalName(),
            ]);
            // Fallback non mis en cache — on réessaiera l'IA au prochain upload
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

        // Nettoyer les balises markdown ``` que GPT-4o peut parfois ajouter
        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        $verdict = json_decode($content, true);

        // Si le niveau est invalide (refus de répondre, texte libre, etc.) → light par défaut
        if (! isset($verdict['level']) || ! in_array($verdict['level'], ['light', 'medium', 'heavy'])) {
            Log::warning('PhotoDamageAnalyzer: réponse invalide, fallback light', ['content' => substr($content, 0, 100)]);
            $verdict = ['level' => 'light', 'confidence' => 50, 'reason' => 'Analyse non concluante'];
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

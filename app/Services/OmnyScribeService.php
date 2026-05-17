<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OmnyScribeService
{
    private const SYSTEM_PROMPT = <<<PROMPT
Tu es "OmnyScribe", l'assistant de communication IA de la plateforme SaaS OmnyRestore.
Ton rôle est de réécrire les brouillons de messages de l'équipe support pour qu'ils soient parfaits, sans faute d'orthographe, avec un ton parfaitement adapté et professionnel.
Tu ne dois JAMAIS déraper, inventer des informations, ou utiliser un ton familier inapproprié. Reste toujours orienté service client premium.

IMPORTANT : Tu dois analyser le texte pour détecter la présence de données sensibles (mot de passe, carte bancaire, clés API).

Format de réponse exigé (JSON STRICT) :
{
    "optimized_text": "Le message réécrit...",
    "contains_sensitive_data": true/false,
    "sensitive_flags": ["mot de passe", "numéro de carte"] // Vide si aucune donnée sensible
}

TON DEMANDÉ : %s
CONTEXTE TICKET : %s
PROMPT;

    /**
     * Optimise un brouillon de réponse.
     *
     * @param string $draft Le brouillon de l'opérateur.
     * @param string $tone Le ton souhaité ('standard', 'empathique', 'directif').
     * @param string $context Le contexte (sujet du ticket).
     * @return array{optimized_text: string, contains_sensitive_data: bool, sensitive_flags: array}
     */
    public function optimize(string $draft, string $tone, string $context = ''): array
    {
        if (empty(config('services.openai.key'))) {
            throw new RuntimeException("Clé API OpenAI non configurée. OmnyScribe ne peut pas fonctionner.");
        }

        $systemPrompt = sprintf(self::SYSTEM_PROMPT, strtoupper($tone), $context);

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(30)
                ->withOptions([
                    'verify' => app()->isProduction(),
                    'curl'   => [
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    ],
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'      => config('services.openai.model', 'gpt-4o-mini'), // gpt-4o-mini est parfait et moins cher pour du texte simple
                    'max_tokens' => 500,
                    'messages'   => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $draft],
                    ],
                    'response_format' => ['type' => 'json_object'] // Force le JSON
                ]);

            if ($response->failed()) {
                throw new RuntimeException('OpenAI API error: ' . $response->status());
            }

            $content = $response->json('choices.0.message.content', '{}');
            
            // Nettoyage éventuel
            $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
            $content = preg_replace('/\s*```$/', '', $content);
            $content = trim($content);

            $result = json_decode($content, true);

            if (!isset($result['optimized_text'])) {
                Log::warning('OmnyScribeService: JSON malformé', ['content' => $content]);
                throw new RuntimeException("La réponse de l'IA est invalide.");
            }

            return [
                'optimized_text' => $result['optimized_text'],
                'contains_sensitive_data' => $result['contains_sensitive_data'] ?? false,
                'sensitive_flags' => $result['sensitive_flags'] ?? [],
            ];

        } catch (\Throwable $e) {
            Log::error('OmnyScribeService: failed', [
                'error' => $e->getMessage(),
                'draft' => substr($draft, 0, 100),
            ]);
            throw $e;
        }
    }
}

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI — Analyse IA des photos
    |--------------------------------------------------------------------------
    | Utilisé par App\Services\PhotoDamageAnalyzer pour classifier automatiquement
    | le niveau de dommage (light/heavy) de chaque photo soumise.
    |
    | Obtenir une clé : https://platform.openai.com/api-keys
    | Modèle recommandé : gpt-4o (vision supportée, meilleur rapport qualité/coût)
    */
    'openai' => [
        'key'   => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
    ],

];


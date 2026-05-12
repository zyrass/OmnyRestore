<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware : SecurityHeaders
 *
 * Ajoute les en-têtes HTTP de sécurité recommandés sur toutes les réponses web.
 *
 * En-têtes appliqués :
 *   - X-Content-Type-Options      : Empêche le MIME sniffing
 *   - X-Frame-Options             : Protection contre le clickjacking
 *   - X-XSS-Protection            : Filtre XSS navigateurs legacy
 *   - Referrer-Policy             : Limite les informations transmises via Referer
 *   - Permissions-Policy          : Désactive les APIs navigateur inutiles
 *   - Strict-Transport-Security   : Force HTTPS (production uniquement)
 *   - Content-Security-Policy     : Restreint les sources de ressources
 *
 * Objectif : Grade A sur securityheaders.com en production.
 *
 * Enregistrement dans bootstrap/app.php :
 *   $middleware->web(append: [\App\Http\Middleware\SecurityHeaders::class]);
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // ─── Protection de base ───────────────────────────────────────────────
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // ─── Permissions navigateur ───────────────────────────────────────────
        // Désactive les APIs inutiles pour réduire la surface d'attaque
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(self), usb=()'
        );

        // ─── HSTS (HTTPS uniquement en production) ─────────────────────────────
        // Ne pas activer en local pour éviter de bloquer HTTP sur localhost
        if (app()->isProduction()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // ─── Content-Security-Policy ───────────────────────────────────────────
        // Adapté à la stack : Livewire (inline scripts), Stripe.js, Google Fonts
        $csp = implode('; ', [
            "default-src 'self'",
            // Livewire utilise des scripts inline, Stripe nécessite js.stripe.com
            "script-src 'self' 'unsafe-inline' https://js.stripe.com",
            // Stripe charge son iframe depuis js.stripe.com
            "frame-src https://js.stripe.com",
            // Photos S3 + data: pour les aperçus base64
            "img-src 'self' data: blob: https://*.amazonaws.com https://*.r2.cloudflarestorage.com",
            // Tailwind inline styles + Google Fonts
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            // Livewire WebSockets + Stripe API
            "connect-src 'self' https://api.stripe.com wss:",
            // Pas de workers, pas d'objets embarqués
            "worker-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}

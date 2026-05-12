<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test : En-têtes de sécurité HTTP
 *
 * Vérifie que le middleware SecurityHeaders ajoute bien
 * tous les en-têtes requis sur les réponses web.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_adds_x_content_type_options_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /** @test */
    public function it_adds_x_frame_options_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    /** @test */
    public function it_adds_referrer_policy_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /** @test */
    public function it_adds_permissions_policy_header(): void
    {
        $response = $this->get('/');

        $response->assertHeaderMissing('X-Powered-By');
        $response->assertHeader('Permissions-Policy');
    }

    /** @test */
    public function content_security_policy_is_absent_outside_production(): void
    {
        // En développement/test, le CSP est désactivé pour ne pas bloquer
        // Vite (localhost:5173) ni le Hot Module Replacement.
        // Le CSP ne s'applique qu'en production (APP_ENV=production).
        $this->assertFalse(app()->isProduction());

        $response = $this->get('/');

        $response->assertHeaderMissing('Content-Security-Policy');
    }

    /** @test */
    public function hsts_header_is_absent_outside_production(): void
    {
        // En environnement de test (non production), HSTS ne doit pas être envoyé
        // pour ne pas bloquer HTTP en local
        $this->assertFalse(app()->isProduction());

        $response = $this->get('/');

        $response->assertHeaderMissing('Strict-Transport-Security');
    }
}

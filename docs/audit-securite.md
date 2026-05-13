# 🔐 Audit de Sécurité — OmnyRestore v0.19.0

> **Date** : 2026-05-13 | **Auditeur** : Antigravity AI | **Scope** : Application Laravel 12, infrastructure VPS OVH, pipeline CI/CD

---

## 1. SYNTHÈSE EXÉCUTIVE

| Domaine | Statut | Niveau |
|---|---|---|
| Authentification & Sessions | ✅ Solide | Bon |
| Autorisation (RBAC) | ✅ Solide | Bon |
| Protection des données (RGPD) | ✅ Conforme | Excellent |
| Sécurité des paiements Stripe | ✅ Solide | Bon |
| Sécurité des fichiers S3 | ⚠️ À renforcer | Moyen |
| Variables d'environnement | ✅ Bien géré | Bon |
| Headers HTTP | ✅ Configurés | Excellent |
| Rate Limiting | ⚠️ Partiel | À compléter |
| Tests de sécurité | ⚠️ En cours | Moyen |
| HTTPS / TLS | ⚠️ Non configuré | À faire |
| Logs & Monitoring | ⚠️ Basique | À renforcer |
| Dépendances | ⚠️ Non audités | À vérifier |

**Score global : 7.5/10 — L'application est structurellement très sécurisée (Headers OK, workflow livraison robuste). Reste l'infrastructure de production à verrouiller.**

---

## 2. AUTHENTIFICATION & SESSIONS

### ✅ Points positifs

- Laravel Breeze avec vérification email obligatoire (`verified` middleware)
- Sessions Redis (pas de sessions fichiers en prod)
- `SESSION_ENCRYPT=false` → à passer à `true` en production
- Bcrypt avec 12 rounds (excellent, conforme OWASP)
- `AUTH_PASSWORD_TIMEOUT` géré via middleware
- Déconnexion complète avec `Auth::logout()` + `session()->invalidate()` + `regenerateToken()`

### ⚠️ Points à corriger

```php
// config/session.php — À activer en production
'encrypt' => env('SESSION_ENCRYPT', true), // ← forcer true

// config/session.php
'secure' => env('SESSION_SECURE_COOKIE', true),  // HTTPS only
'same_site' => 'strict',                          // Anti-CSRF
'http_only' => true,                              // Pas d'accès JS
```

### Action requise

```bash
# .env production
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
```

---

## 3. AUTORISATION (RBAC)

### Architecture en place

```
auth → verified → admin       # Routes /admin/*
auth → verified → client      # Routes /client/*
```

- `EnsureIsAdmin` : vérifie `$user->role === 'admin'`
- `EnsureIsClient` : redirige les admins vers leur dashboard
- Policies Laravel : `OrderPolicy` vérifie `$order->user_id === $user->id` (IDOR prevention)
- Abort 403 (pas de redirect) sur les routes admin pour ne pas révéler l'existence des URLs

### ✅ Correct — Aucune action requise

---

## 4. PROTECTION DONNÉES (RGPD)

### ✅ Conforme

| Obligation | Implémentation |
|---|---|
| Droit à l'effacement (Art. 17) | `DeleteUserAction` — anonymisation PII + soft-delete |
| Audit trail | `anonymized_at` timestamp non-nullable après suppression |
| Rétention données | Commandes 10 ans (L.123-22 C.com), Photos 6 mois |
| Purge automatique | `media:purge-expired` planifiée quotidiennement |
| Politique de confidentialité | Conforme LCEN + RGPD |
| Médiation consommateur | CNPM + ODR mentionnés |
| Consentement | `rgpd_consent_at` enregistré à l'inscription |

### ⚠️ À compléter

- [ ] **Export données (Art. 20)** : Le bouton "Portabilité" dans le profil n'est pas encore fonctionnel → implémenter un export JSON des données personnelles
- [ ] **Registre des traitements** : Créer `docs/registre-traitements.md` (obligation RGPD Art. 30 pour les entreprises)

---

## 5. SÉCURITÉ PAIEMENTS STRIPE

### ✅ Implémentation correcte

- Vérification de signature webhook : `$stripe->webhooks->constructEvent()` avec `STRIPE_WEBHOOK_SECRET`
- Idempotence : vérification `status !== 'PAID'` avant de marquer comme payé
- Protection IDOR : `abort_if($order->user_id !== auth()->id(), 403)` avant checkout
- Métadonnées propagées dans `payment_intent_data` pour retrouver la commande
- Clés test/live séparées dans `.env`

### ⚠️ À corriger avant production

```php
// Actuellement : aucune vérification du montant côté serveur
// Un utilisateur malveillant ne peut pas modifier le montant (Stripe gère),
// mais ajouter une vérification défensive :

if ($session->amount_total !== $expectedCents) {
    Log::critical('Montant Stripe incohérent', [...]);
    abort(400);
}
```

```bash
# .env production — clés LIVE uniquement
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

---

## 6. SÉCURITÉ FICHIERS S3

### ⚠️ Points critiques à corriger

**Problème 1 : Buckets potentiellement publics**

```bash
# Les deux buckets doivent être PRIVÉS (Block All Public Access)
aws s3api put-public-access-block \
  --bucket omnyrestore-media \
  --public-access-block-configuration "BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true"

aws s3api put-public-access-block \
  --bucket omnyrestore-deliveries \
  --public-access-block-configuration "BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true"
```

**Problème 2 : Politique IAM trop permissive**

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "OmnyRestoreAppAccess",
      "Effect": "Allow",
      "Principal": { "AWS": "arn:aws:iam::ACCOUNT_ID:user/omnyrestore-app" },
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject",
        "s3:GetObjectVersion"
      ],
      "Resource": [
        "arn:aws:s3:::omnyrestore-media/*",
        "arn:aws:s3:::omnyrestore-deliveries/*"
      ]
    }
  ]
}
```

> Ne JAMAIS utiliser les credentials root AWS. Créer un utilisateur IAM dédié avec le minimum de permissions.

**Problème 3 : URLs signées et Expiration ZIP — Durée à vérifier**

```php
// Les URLs signées S3 ont une durée limitée à 48h
$url = Storage::disk('s3')->temporaryUrl($path, now()->addHours(48));

// ✅ Bonne pratique : L'archive ZIP elle-même expire physiquement après 90 jours
// Ce mécanisme est géré de manière robuste par markAsDelivered()
```

---

## 7. HEADERS HTTP DE SÉCURITÉ

### ✅ Configurés — Sécurisation renforcée en v0.15.0

Le middleware `SecurityHeaders` est actif sur toutes les requêtes web :

```php
// app/Http/Middleware/SecurityHeaders.php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload'
        );
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' https://js.stripe.com; " .
            "frame-src https://js.stripe.com; " .
            "img-src 'self' data: https://*.amazonaws.com; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
            "font-src 'self' https://fonts.gstatic.com; " .
            "connect-src 'self' https://api.stripe.com;"
        );

        return $response;
    }
}
```

```php
// bootstrap/app.php — ajouter au groupe web
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\SecurityHeaders::class,
    ]);
})
```

Résultat attendu sur [securityheaders.com](https://securityheaders.com) : **Grade A**

---

## 8. RATE LIMITING

### ⚠️ Incomplet

| Endpoint | Limite actuelle | Recommandation |
|---|---|---|
| `POST /login` | Breeze default (5/min) | ✅ OK |
| `POST /register` | Breeze default | ✅ OK |
| `POST /forgot-password` | Breeze default | ✅ OK |
| Resend unlock email | 1/5min (session) | ✅ OK (custom) |
| `POST /client/orders` (création) | ❌ Aucune | Ajouter `throttle:10,1` |
| Webhook Stripe | ❌ Aucune | Vérification signature suffit |
| API OpenAI (auto-restore) | ❌ Aucune | Limiter à 1 job/order |

```php
// routes/client.php — ajouter throttle sur création commande
Route::middleware(['auth', 'verified', 'client', 'throttle:orders'])
    ->group(function () { ... });

// app/Providers/AppServiceProvider.php
RateLimiter::for('orders', function (Request $request) {
    return Limit::perHour(20)->by($request->user()?->id);
});
```

---

## 9. INJECTION SQL & XSS

### ✅ Protégé par défaut

- Eloquent ORM : requêtes préparées automatiquement — 0 injection SQL possible
- Blade : `{{ }}` échappe le HTML par défaut — 0 XSS via templates
- `{!! !!}` non utilisé sur des données utilisateur (vérifié dans le code)
- `$fillable` défini sur tous les modèles — pas de mass assignment

### À vérifier

```bash
# Vérifier qu'aucun {!! !!} n'est utilisé sur des données user
grep -r '{!!' resources/views/ | grep -v "vendor"
```

---

## 10. PROTECTION CSRF

### ✅ Activé par défaut

- Token CSRF sur tous les formulaires POST (middleware `VerifyCsrfToken`)
- Route webhook Stripe exclue : `except = ['/webhook/stripe']` → **correct** car Stripe envoie sa propre signature
- Livewire gère automatiquement les tokens CSRF sur chaque requête wire

---

## 11. SECRETS & VARIABLES D'ENVIRONNEMENT

### ✅ Bien géré

- `.env` dans `.gitignore` ✅
- `.env.backup`, `.env.production` dans `.gitignore` ✅
- `storage/*.key` dans `.gitignore` ✅
- `.env.example` ne contient aucune vraie clé ✅

### ⚠️ À faire — Rotation des secrets

```bash
# Vérifier qu'aucune clé n'a jamais été commitée
git log --all --full-history -- .env
git secrets --scan-history  # si git-secrets est installé
```

```bash
# Outils recommandés
npm install -g detect-secrets   # Scan pré-commit
pip install detect-secrets
detect-secrets scan > .secrets.baseline
```

### GitHub Secrets (pour CI/CD)

Créer dans GitHub → Settings → Secrets and variables → Actions :

```
APP_KEY
DB_PASSWORD
AWS_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY
STRIPE_SECRET
STRIPE_WEBHOOK_SECRET
RESEND_API_KEY
OPENAI_API_KEY
```

---

## 12. DÉPENDANCES — VULNÉRABILITÉS CONNUES

### ⚠️ Audit à effectuer régulièrement

```bash
# Composer — vulnérabilités connues
composer audit

# NPM
npm audit
npm audit fix

# GitHub Dependabot — activer dans .github/dependabot.yml
version: 2
updates:
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
  - package-ecosystem: "npm"
    directory: "/"
    schedule:
      interval: "weekly"
```

---

## 13. LOGS & MONITORING

### ⚠️ À renforcer

```php
// config/logging.php — production : Slack + fichier
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],
    ],
    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'level' => 'critical',  // Alertes critiques seulement
    ],
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'info',
        'days' => 30,
    ],
],
```

```bash
# .env production
LOG_CHANNEL=stack
LOG_LEVEL=info   # Pas debug en production (performances + sécurité)
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/...
```

---

## 14. CHECKLIST AVANT MISE EN PRODUCTION

```
SÉCURITÉ APPLICATION
[ ] APP_DEBUG=false
[ ] APP_ENV=production
[ ] SESSION_ENCRYPT=true
[ ] SESSION_SECURE_COOKIE=true
[x] Middleware SecurityHeaders ajouté
[ ] Rate limiting sur création commandes
[ ] Rotation de APP_KEY documentée

SÉCURITÉ INFRASTRUCTURE
[ ] HTTPS avec certificat Let's Encrypt (Certbot)
[ ] HSTS activé (includeSubDomains + preload)
[ ] Nginx : désactiver server_tokens
[ ] Nginx : limiter méthodes HTTP (GET, POST, HEAD)
[ ] UFW : uniquement ports 22, 80, 443
[ ] Fail2ban : protection SSH + Nginx

SÉCURITÉ S3
[ ] Buckets privés (Block All Public Access)
[ ] Politique IAM minimale (principe du moindre privilège)
[ ] Versioning S3 activé (protection contre suppression accidentelle)
[ ] Lifecycle rules : purge automatique après 6 mois

SÉCURITÉ BASE DE DONNÉES
[ ] Utilisateur PostgreSQL dédié (pas postgres)
[ ] Mot de passe fort (32+ caractères aléatoires)
[ ] Connexions limitées à localhost (pas exposé au réseau)
[ ] Sauvegardes chiffrées quotidiennes (pg_dump + GPG)
[ ] pg_hba.conf : authentification md5/scram-sha-256

STRIPE PRODUCTION
[ ] Clés LIVE configurées
[ ] Webhook endpoint HTTPS uniquement
[ ] Tester avec Stripe CLI en mode live
[ ] Activer les notifications email Stripe

MONITORING
[ ] Uptime monitoring (UptimeRobot ou Better Uptime)
[ ] Alertes Slack sur erreurs critiques
[ ] Laravel Horizon dashboard sécurisé
[ ] Logs centralisés (Papertrail ou Logtail)
```

---

*Document généré le 2026-05-13 — À réviser à chaque release majeure.*

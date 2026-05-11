# Changelog

Tous les changements notables d'**OmnyRestore** sont documentés ici.

Ce projet respecte le [Semantic Versioning](https://semver.org/) et les conventions [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

> Prochaines étapes : intégration OpenAI auto, conformité RGPD complète, MVP production.

---

## [0.6.0] — 2026-05-12

### Ajouté
- **Watermark automatique (Intervention Image v3 + PHP GD)** :
  - `GenerateWatermarkJob` : lecture + redimensionnement 1200px + filigrane diagonal tuilé "OmnyRestore" (Inter Bold, blanc 18%) + export JPEG 75%
  - Collection `watermarked` (Spatie) alimentée automatiquement — plus de `singleFile()` (1 image watermarked par photo retouchée)
  - Listener `GenerateWatermarkOnRetouchedUpload` : écoute `MediaHasBeenAddedEvent` → dispatch automatique sur upload admin dans `retouched`
  - Commande `php artisan watermarks:regenerate [--order=REF] [--sync]` pour régénérer en masse ou commande par commande
  - Font Inter Bold bundlée dans `storage/app/fonts/watermark.ttf`
- **Activation extension GD** dans `php.ini` (PHP 8.2.6 local)
- **`intervention/image-laravel` v1.5** (`intervention/image` v3.11 — compatible PHP 8.2)

### Modifié
- `app/Models/Order::registerMediaCollections()` : retire `singleFile()` de la collection `watermarked`
- `app/Providers/AppServiceProvider` : enregistrement `Event::listen(MediaHasBeenAddedEvent, GenerateWatermarkOnRetouchedUpload)`
- Vue client `orders/show.blade.php` : utilise les vrais fichiers watermarked serveur — CSS overlay uniquement en fallback si job pas encore tourné

---

## [0.5.1] — 2026-05-12 (hotfixes)

### Corrigé

- **`authorize()` cassé (Laravel 12)** : le trait `AuthorizesRequests` n'est plus inclus automatiquement dans `Controller`.
  Remplacé par des `abort_if()` explicites dans `OrderCheckoutController` et `OrderDownloadController`.

- **`$slot` undefined sur pages paiement** : le layout `app.blade.php` utilisait `{{ $slot }}` (composant Blade) mais les vues `payment/success` et `payment/cancel` utilisaient `@extends`/`@section`. Fix : layout hybride via `{!! isset($slot) ? $slot : $__env->yieldContent('content') !!}`

- **`zip_path` non persisté** : champs `zip_path`, `zip_expires_at`, `payment_intent_id`, `paid_at`, `delivered_at` absents du `$fillable` du modèle `Order`. `GenerateOrderZipJob` ne pouvait pas sauvegarder le chemin ZIP.

- **Bouton download invisible** : la vue `show.blade.php` vérifiait `$order->delivery?->zip_path` (relation `OrderDelivery` inexistante) au lieu de `$order->zip_path`.

- **Download ne servait pas le fichier** : `OrderDownloadController` cherchait un `OrderDelivery` qui n'existe pas. Simplifié pour lire `order->zip_path` directement et servir le fichier via `response()->download()` (local) ou URL S3 pré-signée (prod).

- **`BinaryFileResponse` return type** : `response()->download()` retourne `Symfony\BinaryFileResponse`, incompatible avec le type hint. Fix : `\Symfony\Component\HttpFoundation\Response` comme type de base.

- **Config Stripe** : clés `pk_test_`/`sk_test_` correctement configurées (Mailtrap pour les emails de test).

### Ajouté

- **Config Mailtrap** pour tester les emails en local (`MAIL_MAILER=smtp`, `sandbox.smtp.mailtrap.io`)
- `GenerateOrderZipJob::dispatchSync()` utilisable pour tests locaux sans queue worker

---

## [0.5.0] — 2026-05-12

### Ajouté
- **Intégration Stripe Checkout** — flux de paiement complet :

  - `OrderCheckoutController` : crée une Stripe Checkout Session avec métadonnées `order_id`
  - Redirection vers la page Stripe hébergée (locale `fr`, mode `payment`)
  - `success_url` → `/payment/success` + `cancel_url` → commande (annulation propre)
  - Montant calculé depuis `order.total_price_cents` (TTC)

- **Webhook Stripe** (`POST /webhook/stripe`) opérationnel :

  - Vérification signature HMAC (`STRIPE_WEBHOOK_SECRET`) avant tout traitement
  - `checkout.session.completed` → marque commande `PAID` + dispatch `GenerateOrderZipJob`
  - `payment_intent.payment_failed` → log de l'échec pour suivi admin
  - Idempotence : skip si la commande est déjà `PAID`
  - Email `OrderPaidConfirmation` envoyé via queue au client après paiement

- **`GenerateOrderZipJob`** (queue async, 3 retries, timeout 300s) :
  - Collecte les fichiers de la collection `retouched` (Spatie)
  - Crée un ZIP dans `storage/app/orders/zips/` avec README.txt lisible
  - Met à jour `order.zip_path` et passe le statut à `DELIVERED`

- **Téléchargement ZIP client** (`GET /client/orders/{order}/download`) :
  - Vérifie ownership via `OrderPolicy::download`
  - Vérifie `payment_status === 'paid'`
  - Génère / rafraîchit URL signée (48h)

- **Route stream local** (`GET /client/orders/download/stream/{delivery}`) :
  - Pour environnement de développement sans S3
  - URL Laravel signée temporaire (48h) → `response()->download()`
  - Vérification ownership + signature avant de servir le fichier

- **`SignedUrlService`** mis à jour :

  - Disk `local` : URL Laravel signée via `URL::temporarySignedRoute()`
  - Disk `s3` : URL AWS pré-signée (inchangé)
  - Cache URL sur `OrderDelivery` pour éviter les appels S3 répétés
- **Migrations Cashier** publiées : `subscriptions`, `subscription_items` (avec `meter_id`, `meter_event_name`)
- **Pages paiement** : `/payment/success` (animée, con confirmation + lien mes commandes) + `/payment/cancel`
- **Route Cashier native** : `POST /stripe/webhook` + `GET /stripe/payment/{id}` (Cashier built-in)

### Modifié

- `app/Http/Controllers/Webhook/StripeWebhookController.php` : ajout dispatch job ZIP + mail confirmation
- `app/Services/SignedUrlService.php` : support disk local via URL signée Laravel
- `routes/client.php` : route stream local + imports `OrderDelivery` + `Storage`

---

## [0.4.1] — 2026-05-12

### Ajouté

- **Module tickets support côté admin** (`/admin/tickets`) :

  - Liste paginée avec filtres par statut (Ouvert / En attente / Fermé)
  - Badge or dans la nav indiquant le nombre de tickets non lus (nouveaux messages clients)
  - Vue conversation (`/admin/tickets/{ticket}`) : fil chronologique, réponse, fermer / rouvrir
  - Passage automatique `open → pending` à l'ouverture par l'admin, `pending → open` à la réponse client
  - Sidebar : infos client + lien vers commande liée

- **Module tickets support côté client** (`/client/tickets`) :

  - Formulaire de création avec pré-sélection de commande via `?order_id=xxx`
  - Fil de conversation avec métadonnées (date, auteur, équipe OmnyRestore)
  - Action clore / rouvrir ticket

- **Routes admin tickets** : `GET /admin/tickets` + `GET /admin/tickets/{ticket}`

- **Navigation contextuelle** selon le rôle :

  - Admin : Dashboard / Commandes / Tickets (avec badge non-lus)
  - Client : Mes commandes / + Nouvelle commande / Support

- **Badge rôle Admin** dans la barre de navigation : badge `[Admin]` en or + avatar avec bordure 2px pleine

- **Modal de confirmation custom Alpine.js** (remplace `wire:confirm` navigateur) :

  - `window.omnyConfirm({title, message, confirmLabel, danger})` → `Promise`
  - `Alpine.store('confirmModal')` disponible globalement
  - Design dark/gold cohérent avec le thème : backdrop blur, bandeau top, icône contextuelle
  - Appliqué sur : admin tickets (Fermer/Rouvrir ×3) + client tickets (Clore)

- **Commandes Artisan de diagnostic** :

  - `php artisan debug:media` — vérifie URLs, paths, disk, symlink, fichiers présents
  - `php artisan debug:users` — liste tous les utilisateurs avec leur rôle

- **Barre de progression Livewire** en couleur or `#C9A84C` (cohérence thème)

- **Support TIFF** dans les `preview_mimes` Livewire

### Corrigé

- **Race condition Livewire + Spatie MediaLibrary** sur les uploads client ET admin :

  - Les fichiers tmp Livewire étaient supprimés avant que `addMedia()` puisse les lire
  - Fix : copie explicite dans `storage/app/tmp-uploads/` avant `addMedia()`, nettoyage post-upload
  - Appliqué sur : `client/orders/create.blade.php` + `admin/orders/show.blade.php`

- **APP_URL port incorrect** : `http://127.0.0.1:8000` → `http://127.0.0.1:8001`

  - Spatie générait des URLs pointant vers le mauvais port → images broken

- **Aperçu photos restaurées côté client** : cherchait la collection `watermarked` (vide)

  - Fix : fallback `watermarked → retouched` avec watermark CSS (gradient diagonal + texte overlay)

- **Limites d'upload insuffisantes** — silençaient les échecs :

  - PHP `upload_max_filesize` : 2 Mo → **100 Mo**
  - PHP `post_max_size` : 8 Mo → **120 Mo**
  - Livewire `temporary_file_upload.rules` : `max:12288` → **`max:102400`** (100 Mo)
  - Livewire `disk` : `null` → `local` (explicite)

- **Crash `MethodNotFoundException`** dans la liste admin : `wire:click="$navigate()"` n'existe pas en Livewire, remplacé par `onclick="window.location='...'"` natif

- **Erreur PostgreSQL** `HAVING "u" > 0` sur alias de sous-requête (badge tickets nav) : remplacé par `whereHas()` (génère un `WHERE EXISTS`)

- **Navigation admin `$navigate`** : remplacé par `onclick` JavaScript natif (Livewire ne supporte pas `$navigate` dans `wire:click`)

- **Disk Spatie hardcodé `s3`** → lecture dynamique via `config('media-library.disk_name')` et `MEDIA_DISK=public` dans `.env`

- **`analysisResults[array_key_first()]`** utilisé pour toutes les photos → chaque photo utilise maintenant son propre index `$i`

### Modifié

- `config/livewire.php` : publié et configuré (limites upload, disk local, max_upload_time 10 min, tiff support)
- `resources/views/layouts/app.blade.php` : navigation contextuelle admin/client + modal confirmation global + badge rôle

---

## [0.4.0] — 2026-05-11

### Ajouté

- **Back office admin** complet :

  - Dashboard KPIs (commandes PENDING / IN_PROGRESS / DONE, chiffre du mois)
  - Liste commandes avec filtres statut et pagination
  - Vue détail commande : photos originales, upload retouchées, notes internes, prix, historique audit
  - Prise en charge (PENDING → IN_PROGRESS) avec notification email client

- **Module media** via Spatie MediaLibrary :

  - Collections `originals` (photos client) et `retouched` (photos admin restaurées)
  - Configuration disk via `MEDIA_DISK` env

- **Middleware `EnsureIsAdmin`** pour les routes `/admin/*`

- **Audit trail** : événements `ORDER_CREATED`, `ORDER_STATUS_CHANGED` loggués

- **Notifications email** : prise en charge commande (client), confirmation paiement

---

## [0.3.0] — 2026-05-11

### Ajouté

- **Module client** complet :

  - Formulaire création commande avec analyse IA (GPT-4o Vision) et verdict prix
  - Liste commandes avec statuts et badges
  - Vue détail : état spinner (PENDING/IN_PROGRESS), aperçu filigranné (DONE), bouton paiement
  - Profil client avec informations et gestion compte

- **Analyse IA photos** via `PhotoDamageAnalyzer` : classification `light` / `heavy`, prix 1€ / 10€

- **Routing client** : `client.orders.*`, `client.profile`, `client.tickets.*`

- **Policy `OrderPolicy`** : prévention IDOR, vérification propriété sur chaque requête

---

## [0.2.0] — 2026-05-11

### Ajouté

- Migrations PostgreSQL : `users`, `orders`, `order_deliveries`, `audit_logs`, `media`, `support_tickets`, `support_ticket_messages`
- Modèles Eloquent avec relations, scopes, et commentaires PHPDoc
- Seeders : utilisateurs de test (1 admin + 4 clients), commandes exemple
- `SupportTicket` + `SupportTicketMessage` : structure tickets support

---

## [0.1.0] — 2026-05-11

### Ajouté

- Scaffold Laravel 12 avec Breeze TALL (Tailwind 4 / Alpine.js 3 / Livewire 3 / Volt)
- Configuration PostgreSQL 16 + Redis
- Layout `app.blade.php` dark theme (#0D0B08 / #C9A84C) avec Google Fonts Inter + Playfair Display
- Landing page publique avec CTA, portfolio, tarifs, FAQ
- Authentification complète : inscription (avec consentement RGPD), connexion, déconnexion, email vérifié
- Structure routes : `web.php` (public) + `client.php` + `admin.php` + `webhook.php`

---

## [0.0.1] — 2026-05-11

### Ajouté

- Documentation architecturale initiale (`omnyrestore_architecture.md`)
- Initialisation dépôt Git et remote GitHub
- Stratégie branches : `main` (production) + `test` (intégration par défaut)
- README.md professionnel avec badges, diagrammes Mermaid, instructions installation

---

<!-- Liens -->
[Unreleased]: https://github.com/zyrass/OmnyRestore/compare/v0.4.1...HEAD
[0.4.1]: https://github.com/zyrass/OmnyRestore/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/zyrass/OmnyRestore/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/zyrass/OmnyRestore/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/zyrass/OmnyRestore/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/zyrass/OmnyRestore/compare/v0.0.1...v0.1.0
[0.0.1]: https://github.com/zyrass/OmnyRestore/releases/tag/v0.0.1

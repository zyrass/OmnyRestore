# Changelog

Tous les changements notables d'**OmnyRestore** sont documentés ici.

Ce projet respecte le [Semantic Versioning](https://semver.org/) et les conventions [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

> Prochaines étapes : dashboard admin (URSSAF, coûts IA), export CSV des commandes, notifications push, page portfolio publique avant/après.

---

## [0.16.0] — 2026-05-13 — Workflow post-paiement, corrections critiques fillable & arrondi TVA

### 🚨 Critique — Corrigé

- **`status` et `payment_status` ignorés silencieusement par `update()`** :
  - Ces champs sont intentionnellement exclus de `$fillable` (sécurité state machine).
  - `PaymentSuccessController`, `GenerateOrderZipJob` et `OrderCheckoutController` utilisaient `$order->update(['status' => 'PAID'])` dont l'effet était nul.
  - Résultat : commandes bloquées en `DONE` après paiement Stripe, page de confirmation redirigée vers `orders.show`, email `OrderDeliveryReady` jamais envoyé.
  - **Fix** : remplacement par `markAsPaid()` (méthode dédiée du modèle) + `forceFill(['status'])->save()` dans le job, entouré d'un `try/catch InvalidArgumentException` (idempotence webhook).

- **`abort_unless()` utilisé avec un `redirect()` comme 2e argument** :
  - `abort_unless(condition, redirect()->route(...))` lève `"Object could not be converted to int"` — le 2e argument doit être un code HTTP entier.
  - **Fix** : remplacement par `if (!condition) return redirect()->route(...)`, type de retour `mount()` passé de `void` à `mixed`.

### Ajouté

- **Page de confirmation post-paiement** (`/client/orders/{order}/payment-success`) :
  - Composant Livewire Volt (`payment-success.blade.php`) affiché immédiatement après le retour Stripe.
  - Icône animée (pulse), récapitulatif commande (référence, photos, montant TTC, date/heure précise).
  - Message "📧 Surveillez vos mails !" avec l'email du client.
  - **Polling 5s** (`wire:poll`) : détecte automatiquement le passage à `DELIVERED` → affiche le bouton de téléchargement ZIP sans rechargement de page.
  - Garde de sécurité dans `mount()` : redirige vers `orders.show` si le statut n'est pas `PAID` ou `DELIVERED`.

- **`PaymentSuccessController` redirige vers la page de confirmation** :
  - Les deux cas (paiement normal + idempotence webhook déjà passé) atterrissent sur `payment-success` au lieu de `orders.show` avec un simple flash.
  - `try/catch \InvalidArgumentException` pour l'idempotence si le webhook a déjà transitionné.

- **Constante `PRICES_TTC`** dans `PhotoDamageAnalyzer` :
  - Grille tarifaire TTC exacte en centimes (100, 200, 300) pour calculs sans perte d'arrondi.

- **Documentation** : `docs/workflow-cycle-complet.md` — flowchart Mermaid TB du cycle complet avec explications par phase, table des transitions de statut et emails automatiques.

### Modifié

- **`GenerateOrderZipJob`** : `$order->update(['status' => 'DELIVERED'])` remplacé par `forceFill(['status' => 'DELIVERED'])->save()` — déclenche correctement l'`OrderObserver` qui envoie `OrderDeliveryReady`.

- **Panel admin** (`admin/orders/show.blade.php`) : section "Statut paiement & livraison" refondée :
  - Bouton "Envoyer ZIP + Facture PDF" en dégradé or (gold) visible dès le statut `PAID`.
  - Badge d'attente animé (spinner) pour les statuts antérieurs.
  - Heure de paiement affichée au format `d/m/Y à H:i:s`.

- **Arrondi TVA corrigé** (`create.blade.php`, `OrderCheckoutController`) :
  - **Avant** : `sum(HT) * 1.20` → perte de 1 centime sur commandes multi-niveaux (ex: 3×83¢ + 167¢ = 416¢ HT → 499¢ TTC au lieu de 500¢).
  - **Après** : somme des `price_ttc_cents` individuels par photo (100¢, 200¢, 300¢) → TTC exact garanti.

---

## [0.15.0] — 2026-05-13 — Conformité RGPD complète, Testimonials & Traduction

### Ajouté

- **Moteur de témoignages complet** :
  - Formulaire client dans `show.blade.php` (status DELIVERED uniquement) : étoiles interactives Alpine.js, compteur de caractères en temps réel, validation Livewire
  - Contrainte UNIQUE `order_id` en base — un seul avis par commande livrée
  - `Testimonial::initialsFrom()` — génère les initiales automatiquement depuis le nom complet
  - Workflow 3 états : **en attente** / **publié** / **rejeté** (via `rejected_at`, sans suppression)
  - `App\Models\Testimonial` : scopes `pending()`, `published()`, `rejected()`, relations `order()` / `user()`
  - Migration `add_order_user_to_testimonials_table` : colonnes `order_id`, `user_id`, `rejected_at`

- **Panel d'administration des avis** (`/admin/testimonials`) :
  - 3 onglets : En attente / Publiés / Rejetés
  - Badge doré dans la nav (nombre d'avis en attente)
  - Actions contextuelles : Publier, Rejeter, Dépublier, Supprimer (avec `wire:confirm`)
  - Accessible depuis la nav desktop ET le dropdown avatar (mobile inclus)

- **Suppression de compte RGPD Art. 17 (libre-service)** :
  - Page dédiée `/client/account/delete` (2 étapes : avertissement + formulaire mot de passe)
  - `App\Actions\DeleteUserAction` : suppression médias Spatie, tickets support, anonymisation PII irréversible, soft-delete
  - Migration `add_anonymized_at_to_users_table` : colonne audit trail RGPD
  - Remplacement du lien `mailto:` dans la Zone critique du profil par un vrai lien de page

- **Email-gate** : déverrouillage de l'aperçu filigrané via **lien signé unique** (7 jours)
  - `UnlockPreviewController` + middleware `signed` + route `client.orders.unlock`
  - `OrderReadyForPayment` mailable mis à jour avec le lien signé uniquement
  - Bouton "Renvoyer l'email" avec throttle 5 minutes dans `show.blade.php`

- **Paiement échoué Stripe** :
  - Gestion de l'évènement `payment_intent.payment_failed` dans `StripeWebhookController`
  - Lookup robuste par métadonnées Stripe (`order_id`) ou `payment_intent_id`
  - Nouveau mailable `OrderPaymentFailed` envoyé au client avec lien de réessai
  - Propagation de `payment_intent_data.metadata` dans `OrderCheckoutController`

### Modifié

- **Politique de confidentialité** (`privacy.blade.php`) : refonte complète
  - Rétention factures corrigée : 5 ans → **10 ans** (Art. L.123-22 C.com)
  - Adresse physique du responsable du traitement (Alain GUILLON)
  - Section cookies (strictement nécessaires), notification violation 72h, droits RGPD Art. 17

- **Mentions légales** (`mentions.blade.php`) : refonte complète
  - Directeur de publication (LCEN), adresse physique
  - Médiation consommateur obligatoire : CNPM + plateforme ODR européenne

- **Commentaires PHP** : traduction intégrale EN → FR
  - `EnsureIsAdmin.php`, `PurgeExpiredMediaCommand.php`, `OrderController.php` (admin), `DebugMedia.php`
  - `PurgeExpiredMediaCommand` : rétention des commandes corrigée 5 ans → 10 ans dans les commentaires

- **Nav admin** : lien **Avis** ajouté avec badge (en attente) dans la barre desktop ET le dropdown avatar

---

## [0.10.0] — 2026-05-12 — Phase 2 complète : Workflow livraison, recalcul par photo, layout

### 🚨 Critique — Corrigé

- **Workflow email livraison cassé** : l'email `OrderPaidConfirmation` prétendait que le ZIP était disponible **immédiatement** alors qu'il est généré en asynchrone. Le client cliquait sur "Télécharger" et obtenait une erreur 404.
- **Email post-livraison manquant** : `GenerateOrderZipJob` terminait (statut DELIVERED) sans envoyer **aucun** email. Le client ne recevait jamais ses liens de téléchargement malgré la promesse du flash message.

### Ajouté

- **`App\Mail\OrderDeliveryReady`** — nouvelle classe mail envoyée quand le ZIP est réellement prêt :
  - Sujet : "⬇ Vos photos sont prêtes à télécharger — {référence}"
  - Dispatché par `OrderObserver` lors du passage au statut `DELIVERED`

- **`emails/orders/delivery-ready.blade.php`** — template email livraison :
  - Récapitulatif commande (référence, photos livrées, date, montant TTC)
  - Deux CTA : **⬇ Archive ZIP** (`client.orders.download`) + **📄 Facture PDF** (`client.orders.invoice`)
  - Date d'expiration du lien (90 jours depuis `zip_expires_at`)
  - Style dark-gold cohérent avec les autres emails transactionnels

- **`OrderObserver`** — ajout case `'DELIVERED'` dans le `match` :
  - Dispatch de `OrderDeliveryReady` à la fin du job ZIP
  - Log `"email DELIVERED queued → {email}"` traçable

### Modifié

- **`emails/orders/paid-confirmation.blade.php`** — message honnête :
  - ❌ Avant : "Vos photos sont disponibles au téléchargement" + bouton "⬇ Télécharger"
  - ✅ Après : "Votre archive ZIP est en cours de préparation — vous recevrez un second email" + bouton "Voir ma commande"

- **`client/orders/show.blade.php`** — `recalcPriceFromActivePhotos()` :
  - ❌ Avant : `activeCount × price[order.damage_level]` (un seul prix pour toutes les photos)
  - ✅ Après : somme `price[media.ai_level]` **par photo** — cohérent avec la facture PDF
  - Log enrichi avec breakdown photo-par-photo (id, level, price)

- **`client/orders/show.blade.php`** — garde-fous corrigés :
  - `deletePhoto()` : guard `$totalCount <= 1` (commande ne peut pas être vide)
  - Suppression du guard `activeCount <= 1` dans `rejectPhoto()` (laissé au choix du client)

- **`layouts/app.blade.php`** — largeur layout :
  - `max-w-screen-xl` (1280px) → `max-w-[1400px]` (1400px) sur `<header>` et `<main>`
  - Intermédiaire entre `max-w-7xl` (1280px) et `max-w-screen-2xl` (1536px)

- **`layouts/guest.blade.php`** — pages d'authentification :
  - Contenu gauche (cadre photo) et formulaire droit parfaitement centrés dans leurs colonnes respectives
  - Appliqué sur : connexion, inscription, mot de passe oublié

---

## [0.9.0] — 2026-05-12 — Refactorisation critique : Validation photos côté Client

### ⚠️ Changement de workflow (breaking UX)

Le droit de valider / rejeter les photos restaurées est désormais **exclusivement côté client** (avant paiement). L'admin ne peut plus modifier la sélection.

### Ajouté

- **Composant client `show.blade.php`** — méthodes `rejectPhoto()`, `restorePhoto()`, `recalcPriceFromActivePhotos()` :
  - Sécurité renforcée : vérification `user_id + status === DONE` avant chaque action
  - Rejection marquée avec `is_rejected`, `rejected_at`, `rejected_by = client` sur le média
  - Recalcul automatique de `total_price_cents` (+ recalcul coupon si présent) après chaque rejet/réintégration
  - Log info traçable : `"Client recalc REF: N photo(s) × P cts = T cts HT net."`

- **UI client DONE** — grille de sélection interactive :
  - Bandeau d'information explicatif ("Vous ne payez que ce que vous gardez")
  - Chaque photo affiche un bouton ✕ au survol (✕ Retirer / ↩ Réintégrer)
  - Photos retirées : overlay rouge + badge "Retirée" + opacité réduite
  - Compteur en-tête : "N sélectionnée(s) / N retirée(s)"
  - Bouton Payer masqué si toutes les photos sont retirées
  - Prix TTC du bouton Payer mis à jour après chaque action

### Modifié

- **Admin `show.blade.php`** — grille photos restaurées en lecture seule :
  - Boutons Rejeter / Réintégrer supprimés de l'interface admin
  - Note informative : "Le client sélectionne les photos depuis son espace client"
  - Affichage du statut de rejet (badges "Retirée par client") visible en lecture

---

## [0.8.1] — 2026-05-12 — Patch : Coupon client, exclusion ZIP & recalcul prix

### Ajouté

- **Coupon côté client (formulaire de création de commande)** :
  - Champ de saisie du code de réduction dans la sidebar du formulaire `/client/orders/create`
  - Bouton "Appliquer" appelle `CouponService::apply()` via Livewire (`wire:click="applyCoupon"`)
  - Feedback immédiat : badge vert ✓ avec message si valide, alerte rouge si invalide
  - Bouton × pour retirer le coupon appliqué
  - Le coupon est réinitialisé automatiquement si les photos changent (montant HT différent)
  - Lors de la soumission : `discount_cents` et `coupon_code` sauvegardés sur l'`Order`, `CouponService::confirm()` incrémente `used_count`
  - `coupon_code` et `discount_cents` ajoutés au `$fillable` du modèle `Order`

- **Affichage prix 3 niveaux dans le récapitulatif client** :
  - 1,00 € (Standard), 2,00 € (Avancée), 5,00 € (Complète) avec couleur de badge distincte
  - Décomposition HT / TVA 20% / TTC en temps réel (Alpine.js)
  - Ligne Réduction visible si coupon appliqué

- **Worst-case level corrigé** : l'algo `updatedPhotos()` supporte maintenant le niveau `medium` (précédemment seul `heavy` était testé)

### Modifié

- **`ZipGeneratorService::generate()`** : filtre les médias `is_rejected = true` avant de générer le ZIP
  - Log info si des photos sont exclues
  - Levée d'exception si *toutes* les photos sont rejetées
  - `buildZipReadme()` mis à jour : affiche le nombre de photos actives et mentionne les exclues

- **`rejectPhoto()` / `restorePhoto()` dans `admin/orders/show.blade.php`** :
  - Appel de `recalcPriceFromActivePhotos()` après chaque action
  - `recalcPriceFromActivePhotos()` : compte les médias non rejetés, recalcule `total_price_cents` (HT) en centimes et sauvegarde l'order
  - Log info du calcul (N photos × prix/photo = total)
  - Message flash mis à jour : "prix recalculé" / "prix mis à jour"

---

## [0.8.0] — 2026-05-12 — Phase 9 : Admin UX, Tarification IA & Facturation

### Ajouté

- **Panel Admin rouge dans la navigation** :
  - Bouton "⚙ Panel Admin" à thème rouge dans la barre de navigation
  - Badge "Admin" rouge/bordeaux à côté du nom de l'utilisateur (remplace le badge doré)
  - Lien "Réductions" dans le menu admin pointant vers `/admin/coupons`

- **Largeur maximale `max-w-7xl`** sur tous les éléments pour un meilleur rendu sur 27"+

- **Dashboard en direct (`wire:poll.10s`)** :
  - Auto-refresh toutes les 10 secondes
  - Indicateur visuel "● En direct · HH:MM:SS" avec point vert pulsant

- **Tableau des clients dans le Dashboard admin** :
  - Nom, email, nombre de commandes, total dépensé HT, date d'inscription
  - Lien vers les commandes du client (recherche pré-remplie)

- **Tarification IA 3 niveaux (refonte `PhotoDamageAnalyzer`)** :
  | Niveau | Critères | HT | TTC |
  |--------|----------|-----|-----|
  | `light` Standard | Jaunissement, poussière, petites taches | 0,83 € | **1,00 €** |
  | `medium` Avancée | Rayures, décoloration forte, pliures, grain | 1,67 € | **2,00 €** |
  | `heavy` Complète | Déchirures, dommages eau, zones manquantes | 4,17 € | **5,00 €** |
  - `PRICES`, `TVA_RATE`, `AI_COST_CENTS`, `htToTtc()`, `priceTtcForLevel()`
  - Fallback heuristique GD mis à jour avec 3 niveaux

- **Affichage TVA transparent dans la fiche commande admin** :
  - Décomposition HT / TVA 20% / TTC dans la sidebar
  - Ligne "Dont coût IA" pour la transparence tarifaire

- **Génération de factures PDF (Phase C)** :
  - Dépendance `barryvdh/laravel-dompdf ^3.1` installée
  - Route `GET /client/orders/{order}/invoice` + `InvoiceController::download()`
  - Template `pdf/invoice.blade.php` : en-tête, parties, tampon Payée, HT/TVA/TTC, note IA, remise coupon
  - Bouton "Télécharger la facture PDF" dans l'espace client (commandes payées)

- **Système de codes de réduction (Phase E)** :
  - Table `coupons` : code, type (percentage/fixed), valeur, min_order_cents, max_uses, expires_at, is_active
  - Champs `coupon_code` et `discount_cents` sur `orders`
  - `Coupon` model + `CouponService::apply()` / `confirm()`
  - Page admin `GET /admin/coupons` : créer, activer/désactiver, supprimer

- **Rejet de photos restaurées (Phase D)** :
  - Boutons "✕ Rejeter" / "↩ Réintégrer" au survol de chaque photo restaurée
  - Via `custom_properties` Spatie MediaLibrary (`is_rejected`, `rejected_at`) — sans migration
  - Overlay rouge "Rejetée" avec opacité réduite
  - Actions Livewire : `rejectPhoto(int $mediaId)` et `restorePhoto(int $mediaId)`

### Modifié

- `layouts/app.blade.php` : max-w-7xl, Panel Admin rouge, badge Admin rouge, lien Réductions
- `admin/dashboard.blade.php` : wire:poll.10s, indicateur live, tableau clients
- `admin/orders/show.blade.php` : grille de rejet, décomposition TVA, labels 3 niveaux
- `client/orders/show.blade.php` : bouton facture PDF
- `PhotoDamageAnalyzer.php` : refonte 3 niveaux, htToTtc(), TVA_RATE, AI_COST_CENTS
- `routes/admin.php` : route /admin/coupons
- `routes/client.php` : route /client/orders/{order}/invoice

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
[Unreleased]: https://github.com/zyrass/OmnyRestore/compare/v0.16.0...HEAD
[0.16.0]: https://github.com/zyrass/OmnyRestore/compare/v0.15.0...v0.16.0
[0.15.0]: https://github.com/zyrass/OmnyRestore/compare/v0.10.0...v0.15.0
[0.10.0]: https://github.com/zyrass/OmnyRestore/compare/v0.9.0...v0.10.0
[0.9.0]: https://github.com/zyrass/OmnyRestore/compare/v0.8.1...v0.9.0
[0.8.1]: https://github.com/zyrass/OmnyRestore/compare/v0.8.0...v0.8.1
[0.8.0]: https://github.com/zyrass/OmnyRestore/compare/v0.6.0...v0.8.0
[0.6.0]: https://github.com/zyrass/OmnyRestore/compare/v0.5.1...v0.6.0
[0.5.1]: https://github.com/zyrass/OmnyRestore/compare/v0.5.0...v0.5.1
[0.5.0]: https://github.com/zyrass/OmnyRestore/compare/v0.4.1...v0.5.0
[0.4.1]: https://github.com/zyrass/OmnyRestore/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/zyrass/OmnyRestore/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/zyrass/OmnyRestore/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/zyrass/OmnyRestore/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/zyrass/OmnyRestore/compare/v0.0.1...v0.1.0
[0.0.1]: https://github.com/zyrass/OmnyRestore/releases/tag/v0.0.1


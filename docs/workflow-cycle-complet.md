# Cycle complet d'une commande — OmnyRestore

> Flowchart du parcours d'une commande, de la soumission des photos jusqu'à la livraison du ZIP au client, en passant par la restauration IA et le paiement Stripe.

---

## Diagramme

```mermaid
flowchart TB
    classDef client   fill:#1a3a5c,stroke:#4a9eda,color:#e8f4ff
    classDef admin    fill:#3a2a00,stroke:#c9a84c,color:#fff8e1
    classDef stripe   fill:#1a1a3e,stroke:#7c7cff,color:#e8e8ff
    classDef job      fill:#1a3a1a,stroke:#4aaa4a,color:#e8ffe8
    classDef observer fill:#3a1a1a,stroke:#dd5555,color:#ffe8e8
    classDef status   fill:#0d0d0d,stroke:#555,color:#ccc
    classDef email    fill:#2a1a3a,stroke:#aa55dd,color:#f0e8ff

    START(["Client soumet ses photos\net cree une commande"]):::client
    S1["Order creee\nstatus = PENDING\nreference = ORD-YYYY-XXXX"]:::status
    START --> S1

    S1 --> A1["Admin voit la commande\ndans le panel admin"]:::admin
    A1 --> A2["Admin clique 'Prendre en charge'\nstartProcessing()"]:::admin
    A2 --> S2["status = IN_PROGRESS"]:::status
    S2 --> A3["Admin restaure les photos\net uploade les retouches"]:::admin
    A3 --> A4["Admin clique 'Marquer terminee'\nmarkAsDone()"]:::admin
    A4 --> S3["status = DONE\ndelivered_at = now()"]:::status

    S3 --> OBS1["OrderObserver\nstatus DONE detecte"]:::observer
    OBS1 --> MAIL1["Email OrderReadyForPayment\n'Vos photos sont pretes'\nlien signe 7 jours"]:::email

    MAIL1 --> C1["Client recoit l'email"]:::client
    C1 --> C2["Clic lien signe\nUnlockPreviewController\npreview_unlocked_at = now()"]:::client
    C2 --> C3["Client voit les apercus\nfiligranes et selectionne\nses photos"]:::client
    C3 --> C4{"Satisfait ?"}

    C4 -- "Non" --> C3
    C4 -- "Oui" --> C5["Client clique\n'Payer X,XX euro TTC'"]:::client

    C5 --> STR1["StripeCheckoutController\ncree session Checkout\nmode payment, currency EUR"]:::stripe
    STR1 --> STR2["Redirection Stripe\nHosted Checkout Page"]:::stripe
    STR2 --> STR3{"Paiement\nStripe"}

    STR3 -- "Abandon" --> STR4["Redirect /payment/cancel\nstatus reste DONE"]:::stripe
    STR3 -- "Paiement valide" --> STR5["Stripe confirme"]:::stripe

    STR5 --> SPLIT{{"Double filet\nde securite"}}

    SPLIT --> WH["WEBHOOK Stripe\nPOST /webhook/stripe\ncheckout.session.completed\nSource de verite principale"]:::stripe
    SPLIT --> PSC["REDIRECT navigateur\nGET /payment/success\nPaymentSuccessController\nFallback si webhook echoue"]:::stripe

    WH --> WH2["Verification HMAC\nStripe-Signature"]:::stripe
    WH2 --> WH3["markAsPaid\nstatus = PAID\npayment_status = paid\npaid_at = now()"]:::stripe

    PSC --> PSC2{"Deja PAID ?"}
    PSC2 -- "Oui" --> PSCR["Redirect payment-success"]:::stripe
    PSC2 -- "Non" --> PSC3["markAsPaid\ntry/catch si deja traite"]:::stripe
    PSC3 --> PSCR

    WH3 --> S4["status = PAID\npayment_status = paid"]:::status
    PSCR --> PAGE1["Page 'Paiement confirme !'\n/client/orders/id/payment-success\nPolling 5s attend DELIVERED"]:::client

    S4 --> OBS2["OrderObserver\nstatus PAID detecte"]:::observer
    OBS2 --> MAIL2["Email OrderPaidConfirmation\n'Paiement recu,\nZIP en preparation'"]:::email

    S4 --> JOB["GenerateOrderZipJob\ndispatch via queue\nidempotent - webhook + fallback"]:::job

    JOB --> JOB2["Cree archive ZIP\nPhotos HD sans filigrane\nREADME.txt"]:::job
    JOB2 --> JOB3["Sauvegarde ZIP\nstorage/app/orders/zips/"]:::job
    JOB3 --> JOB4["forceFill DELIVERED\nzip_path renseigne\nzip_expires_at +90 jours"]:::job
    JOB4 --> S5["status = DELIVERED"]:::status

    S5 --> OBS3["OrderObserver\nstatus DELIVERED detecte"]:::observer
    OBS3 --> MAIL3["Email OrderDeliveryReady\n'Vos photos sont pretes !'\nBouton ZIP + Facture PDF"]:::email

    S5 --> PAGE1_UPDATE["Polling page confirmation\ndetecte DELIVERED\nAffiche bouton telechargement"]:::client

    MAIL3 --> C6["Client recoit email livraison"]:::client
    C6 --> C7["Telechargement ZIP\nOrderDownloadController\nVerif payment_status = paid"]:::client
    C7 --> END(["Livraison terminee"])

    S5 --> ADM2["Panel Admin\nbouton 'Envoyer ZIP + Facture'\ndevient actif gold apres PAID"]:::admin
    ADM2 --> ADM3["Admin peut renvoyer\nl'email manuellement\n1 envoi / 5 min"]:::admin
    ADM3 --> END
    PAGE1_UPDATE --> END
```

---

## Detail des etapes par phase

### Phase 1 — Soumission client

| Etape | Acteur | Detail technique |
|---|---|---|
| Creation commande | Client | `OrderController::store()` — genere `ORD-YYYY-XXXX`, status `PENDING` |
| Upload photos | Client | Spatie Media Library, collection `originals` |

### Phase 2 — Traitement admin

| Etape | Methode | Guard |
|---|---|---|
| Prise en charge | `startProcessing()` | Depuis `PENDING` uniquement |
| Photos restaurees uploadees | Admin panel | Collection `retouched` + watermarks auto |
| Marquer terminee | `markAsDone()` | Depuis `IN_PROGRESS` uniquement |
| Email apercu | `OrderReadyForPayment` | Declenche par Observer (status → DONE) |

### Phase 3 — Apercu et selection

| Etape | Detail |
|---|---|
| Unlock apercu | URL signee Laravel, expire 7 jours, `preview_unlocked_at` renseigne |
| Visualisation | Photos watermarquees (basse res, filigrane OmnyRestore) |
| Selection | Client peut rejeter des photos — ZIP final n'incluera que les photos acceptees |

### Phase 4 — Paiement Stripe

| Etape | Acteur | Detail |
|---|---|---|
| Session Checkout | `StripeCheckoutController` | Mode `payment`, metadata `order_id`, currency `EUR` |
| Hosted Checkout | Stripe | Page Stripe — 3D Secure gere par Stripe |
| **Webhook** (source verite) | Stripe → App | `POST /webhook/stripe` — HMAC `Stripe-Signature` verifiee |
| **Fallback redirect** | Navigateur → App | `GET /payment/success?session_id=` — filet si webhook absent (dev local) |
| `markAsPaid()` | `Order` model | `forceFill` sur `status`, `payment_status`, `paid_at` — hors `$fillable` |

> [!IMPORTANT]
> `status` et `payment_status` sont **exclus de `$fillable`** pour raisons de securite.
> Seules `markAsPaid()` et `forceFill()` peuvent les modifier.
> `$order->update(['status' => 'PAID'])` **les ignore silencieusement** — bug corrige dans les 3 endroits : `PaymentSuccessController`, `GenerateOrderZipJob`.

### Phase 5 — Generation ZIP

| Etape | Detail |
|---|---|
| Dispatch | `GenerateOrderZipJob::dispatch()->onQueue('default')` — dispatche par webhook ET fallback (idempotent) |
| Creation | `ZipArchive` — photos HD + `README.txt` |
| Stockage | `storage/app/orders/zips/` — hors dossier `public/`, non accessible directement |
| Transition | `forceFill(['status' => 'DELIVERED'])->save()` — declenche l'Observer |
| Email | `OrderDeliveryReady` — liens ZIP + facture — declenche par Observer (status → DELIVERED) |

> [!NOTE]
> Le ZIP expire apres **90 jours** (`zip_expires_at`). Le telechargement passe par `OrderDownloadController` qui verifie `payment_status = paid` et l'existence du fichier.

### Phase 6 — Livraison

| Etape | Detail |
|---|---|
| Page confirmation | `payment-success` — poll 5s — detecte `DELIVERED` — affiche bouton telechargement |
| Email livraison | `OrderDeliveryReady` — bouton ZIP + bouton facture PDF |
| Telechargement ZIP | `OrderDownloadController` — local: `response()->download()` — prod: URL S3 pre-signee 48h |
| Facture PDF | `InvoiceController` — PDF genere a la volee |
| Renvoi admin | Bouton `sendDeliveryEmail()` — style gold dans le panel admin — limite 1 envoi / 5 min |

---

## Machine d'etat des statuts

```
PENDING ──► IN_PROGRESS ──► DONE ──► PAID ──► DELIVERED
                │               │
                └──► CANCELLED  └──► CANCELLED
```

| Transition | Methode | Condition |
|---|---|---|
| `PENDING → IN_PROGRESS` | `startProcessing()` | Admin prend en charge |
| `IN_PROGRESS → DONE` | `markAsDone()` | Admin uploade les retouches |
| `DONE → PAID` | `markAsPaid($intentId)` | Webhook ou fallback Stripe |
| `PAID → DELIVERED` | `forceFill(['status'])` dans `GenerateOrderZipJob` | ZIP genere avec succes |
| `→ CANCELLED` | `cancel($reason)` | Depuis `PENDING` ou `IN_PROGRESS` uniquement |

---

## Emails automatiques (OrderObserver)

| Email | Statut declencheur | Contenu |
|---|---|---|
| `OrderReadyForPayment` | `DONE` | Lien signe apercu + bouton payer |
| `OrderPaidConfirmation` | `PAID` | Confirmation paiement + "ZIP en preparation" |
| `OrderDeliveryReady` | `DELIVERED` | Lien telecharger ZIP + lien facture PDF |

> [!TIP]
> Tous les emails passent par la **queue** (`QUEUE_CONNECTION=database`).
> Un worker doit tourner en arriere-plan : `php artisan queue:work`
> En production : supervise par Supervisor ou Forge/Envoyer.

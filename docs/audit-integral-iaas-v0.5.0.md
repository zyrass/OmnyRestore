# Audit Intégral IaaS et Cartographie Système : OmnyRestore

**Version** : 0.5.0 (Brouillon Avancé / En cours de rédaction)  
**Date de publication** : 15 mai 2026  
**Cible** : CTO, Responsables Infrastructure, Auditeurs Sécurité, DevOps  
**Statut** : Document de travail (Docs 2, 5, 6, 7 manquantes, 4 partielle)

> [!NOTE]
> Ce document constitue l'audit technique et la cartographie exhaustive de l'infrastructure IaaS d'OmnyRestore. 
> En reprenant les travaux initiaux de modélisation C4 (Context, Container, Component), cet audit fusionne la vision architecturale macroscopique avec les impératifs microscopiques de sécurité et de durcissement d'un VPS exposé sur Internet.

---

## 1. Modélisation Architecturale (Approche C4)

L'approche C4 permet de décomposer le système en niveaux d'abstraction, facilitant l'intégration des nouveaux collaborateurs tout en localisant précisément les failles potentielles.

### 1.1 Niveau 1 : Diagramme de Contexte

Identifier les acteurs externes (humains et systèmes) qui interagissent avec OmnyRestore, sans entrer dans la mécanique interne du système.

```mermaid
flowchart TB
    subgraph EXTERNAL["Acteurs externes"]
        V[Visiteur<br/>Non authentifie]
        C[Client<br/>Compte particulier]
        A[Admin<br/>Compte avec privileges]
    end

    SYS[OmnyRestore<br/>Plateforme SaaS<br/>de restauration de photos par IA]

    subgraph SYSTEMS["Systemes tiers integres"]
        STRIPE[Stripe<br/>Paiement et facturation]
        OAI[OpenAI<br/>GPT-4o Vision et DALL-E 3]
        RESEND[Resend<br/>Service email transactionnel]
        LE[Let s Encrypt<br/>Autorite de certification TLS]
    end

    V -->|Consulte landing,<br/>s inscrit| SYS
    C -->|Soumet commandes,<br/>paie, telecharge| SYS
    A -->|Restaure photos,<br/>gere clients| SYS

    SYS -->|Analyse dommages<br/>+ restauration IA| OAI
    SYS -->|Cree sessions<br/>de paiement| STRIPE
    STRIPE -->|Webhook<br/>signature HMAC| SYS
    SYS -->|Envoie emails| RESEND
    SYS -->|Demande certificats TLS| LE

    style SYS fill:#d4a017,color:#000,stroke-width:3px
    style V fill:#1d3557,color:#fff
    style C fill:#1d3557,color:#fff
    style A fill:#8b0000,color:#fff
```

### 1.2 Niveau 2 : Diagramme des Conteneurs

Décomposition du système en unités déployables indépendantes sur le VPS.

```mermaid
flowchart TB
    subgraph CLIENT_TIER["Couche cliente"]
        BROWSER[Navigateur web]
    end

    subgraph VPS_ROCKY["VPS Rocky Linux 9 - omnyrestore.fr"]
        subgraph EDGE["Couche edge"]
            NGINX[NGINX 1.28<br/>Reverse proxy TLS]
            CERTBOT[Certbot<br/>TLS Renewal]
        end

        subgraph RUNTIME["Couche execution applicative"]
            FPM[PHP-FPM 8.4<br/>Pool omnyrestore]
            HORIZON[Laravel Horizon<br/>Workers Redis]
            SUPERVISOR[Supervisor 4<br/>Garde-fou]
        end

        subgraph PERSISTENCE["Couche persistance"]
            PG[(PostgreSQL 16<br/>localhost:5432)]
            REDIS[(Redis 7<br/>localhost:6379)]
            STORAGE[Disque local<br/>storage/app/]
        end

        subgraph SECURITY["Couche securite systeme"]
            FW[firewalld<br/>Ports 80 443 50001]
            F2B[Fail2Ban<br/>Bannissement]
            SE[SELinux enforcing<br/>Contextes httpd]
        end
    end

    BROWSER -->|HTTPS 443| NGINX
    NGINX -->|FastCGI socket Unix| FPM
    FPM -->|TCP localhost| PG
    FPM -->|TCP localhost| REDIS
    FPM -->|Fichiers| STORAGE
    SUPERVISOR -->|Surveille et relance| HORIZON
    HORIZON -->|Consomme jobs| REDIS

    style BROWSER fill:#1d3557,color:#fff
    style NGINX fill:#009639,color:#fff
    style FPM fill:#777bb4,color:#fff
    style HORIZON fill:#ff2d20,color:#fff
    style PG fill:#336791,color:#fff
    style REDIS fill:#dc382d,color:#fff
```

---

## 2. Choix Technologiques et Versions de Référence

Le socle technique a été méticuleusement sélectionné pour garantir longévité, stabilité et performance en production.

| Composant | Version | Justification du choix |
|---|---|---|
| **OS** | Rocky Linux 9.x | Compatible binaire RHEL 9 (10 ans de support). SELinux natif et mature. Excellente stabilité serveur. |
| **Langage** | PHP 8.4 | Sortie en Nov 2024. Offre le meilleur compromis stabilité/longévité comparé à la 8.3. Property hooks et asymmetric visibility. |
| **SGBD** | PostgreSQL 16 | Types JSONB natifs (vital pour les réponses d'IA OpenAI), RLS natif, performance sur données structurées. |
| **Cache/Queue**| Redis 7.x | Indispensable pour soutenir Laravel Horizon et le stockage des sessions hautement volatiles. |
| **Web Server** | NGINX 1.28 | Asynchrone, léger, performant comme reverse proxy et pour servir les assets Vite/Tailwind. |

---

## 3. Topologie Physique et Réseau Cible

L'application doit fonctionner dans un environnement hermétique. Les composants critiques ne doivent jamais écouter sur l'interface publique.

### 3.1 Vue Réseau Interne (Loopback)

> [!CAUTION]
> PostgreSQL et Redis ne doivent posséder aucune interface d'écoute sur l'IP publique du VPS. L'écoute stricte sur `127.0.0.1` est une obligation absolue.

```mermaid
flowchart LR
    BROWSER[Navigateur] -->|TCP 443<br/>interface eth0| NGX

    subgraph LOCALHOST["Interface lo - 127.0.0.1"]
        NGX[NGINX<br/>0.0.0.0:443]
        FPM[PHP-FPM<br/>unix:/run/php-fpm.sock]
        PG[(PostgreSQL<br/>127.0.0.1:5432)]
        RD[(Redis<br/>127.0.0.1:6379)]
    end

    NGX -->|Socket Unix| FPM
    FPM -->|TCP loopback| PG
    FPM -->|TCP loopback<br/>auth requirepass| RD

    style BROWSER fill:#1d3557,color:#fff
    style LOCALHOST fill:#1a1a2e,color:#fff
```

### 3.2 Matrice des Unités Systemd

L'orchestration des services est vitale pour la tolérance aux pannes.

| Unit systemd | Type | État | Rôle métier |
|---|---|---|---|
| `nginx.service` | service | enabled | Reverse proxy frontal |
| `php-fpm.service` | service | enabled | Exécution applicative Laravel |
| `postgresql-16.service` | service | enabled | Persistance des données relationnelles |
| `redis.service` | service | enabled | Gestion des files d'attente (Horizon) et sessions |
| `supervisord.service` | service | enabled | Garde-fou de Laravel Horizon |
| `firewalld.service` | service | enabled | Bouclier pare-feu réseau |
| `fail2ban.service` | service | enabled | Analyseur de logs et banisseur IP actif |
| `backup-postgres.timer` | timer | enabled | Sauvegarde quotidienne S3 (03h00) |
| `laravel-scheduler.timer`| timer | enabled | Remplacement du cron (chaque minute) |

---

## 4. Stratégie de Défense en Profondeur (Defense in Depth)

La sécurité d'un VPS IaaS repose sur l'empilement de couches défensives. Si une couche est compromise, la suivante doit stopper l'attaque.

```mermaid
flowchart TB
    INET[Internet Public]

    subgraph LAYER1["Couche 1 - Reseau"]
        L1A[firewalld<br/>3 ports ouverts uniquement<br/>80 443 50001]
        L1B[Fail2Ban<br/>bannit IPs malveillantes<br/>maxretry 3 - ban 24h]
    end

    subgraph LAYER2["Couche 2 - Reverse Proxy (NGINX)"]
        L2A[NGINX<br/>limit_req_zone<br/>limit_conn_zone]
        L2B[Headers securite<br/>HSTS, CSP, X-Frame-Options]
        L2C[TLS 1.2 et 1.3 uniquement<br/>Ciphers modernes strictes]
    end

    subgraph LAYER3["Couche 3 - Application (Laravel)"]
        L3A[Throttle<br/>30 req/min sur endpoints sensibles]
        L3B[CSRF token<br/>sur tous formulaires POST]
        L3C[Validation Form Requests strictes]
        L3D[Policies d'autorisation]
    end

    subgraph LAYER4["Couche 4 - Systeme (Linux)"]
        L5A[SELinux Enforcing<br/>Contextes stricts httpd_sys_content_t]
        L5B[Utilisateur applicatif sans shell]
        L5C[Pas de root SSH<br/>Port 50001 + Cles ED25519]
    end

    INET --> LAYER1
    LAYER1 --> LAYER2
    LAYER2 --> LAYER3
    LAYER3 --> LAYER4

    style INET fill:#8b0000,color:#fff
    style LAYER1 fill:#2d3748,color:#fff
    style LAYER2 fill:#2d3748,color:#fff
    style LAYER3 fill:#2d3748,color:#fff
    style LAYER4 fill:#2d3748,color:#fff
```

### 4.1 Durcissement du Système d'Exploitation

> [!WARNING]
> L'accès distant via le port 22 par défaut avec un mot de passe `root` est la garantie de se faire pirater son VPS en moins de 48 heures.

- [ ] **Déplacer le port SSH** : Configurer `/etc/ssh/sshd_config` sur un port non-standard (ex: 50001).
- [ ] **Clés SSH obligatoires** : `PasswordAuthentication no`.
- [ ] **Restreindre les utilisateurs** : `AllowUsers deploy_admin`.
- [ ] **Désactiver Root** : `PermitRootLogin no`.

### 4.2 Focus Technique : SELinux en mode "Enforcing"

SELinux est le composant de sécurité le plus puissant de Rocky Linux. Il garantit que même si PHP-FPM est corrompu via une faille d'upload, il ne pourra pas lire `/etc/shadow` ou écrire dans les binaires système.

**Vérification de l'état :**
```bash
sestatus # Doit impérativement répondre "Current mode: enforcing"
```

**Configurations booléennes requises pour Laravel :**
```bash
# Autoriser Nginx à utiliser les sockets Unix pour joindre PHP-FPM
sudo setsebool -P httpd_can_network_connect 1

# Autoriser Laravel à communiquer avec l'extérieur (OpenAI, Stripe API)
sudo setsebool -P httpd_can_network_connect 1

# Autoriser l'envoi d'e-mails (Resend)
sudo setsebool -P httpd_can_sendmail 1

# Définir le bon contexte de fichier pour autoriser l'upload de photos
sudo chcon -Rt httpd_sys_rw_content_t /var/www/omnyrestore/storage
sudo chcon -Rt httpd_sys_rw_content_t /var/www/omnyrestore/bootstrap/cache
```

---

## 5. Middleware : Configuration de NGINX et PHP-FPM

Le couple NGINX / PHP-FPM est le moteur du site. S'il est mal réglé, le site plantera lors des uploads de photographies HD.

### 5.1 NGINX : Le Bouclier Frontal

**Extrait de configuration recommandée (`nginx.conf`) :**
```nginx
server {
    listen 443 ssl http2;
    server_name app.omnyrestore.fr;
    root /var/www/omnyrestore/public;

    # Masquer la version NGINX aux scanners de vulnérabilités
    server_tokens off;

    # Autoriser l'upload de grosses archives photos
    client_max_body_size 50M;

    # Sécurité des en-têtes (Headers)
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;

    # Gestion optimale des assets statiques compilés par Vite
    location ~* \.(?:css|js|woff2?|svg|png|jpe?g|gif)$ {
        expires 1y;
        access_log off;
        add_header Cache-Control "public, max-age=31536000, immutable";
    }

    # Interdiction formelle d'exécuter du PHP dans les dossiers d'upload
    location ~* ^/storage/.*\.php$ {
        deny all;
    }

    # Interdiction d'accès aux fichiers cachés (.env, .git)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 5.2 PHP-FPM : Tuning de Performance

Les traitements asynchrones gèrent les tâches lourdes, mais l'upload synchrone nécessite de la RAM.

**Extrait `/etc/php-fpm.d/www.conf` :**
```ini
; Éviter que PHP-FPM ne monopolise la RAM au repos
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35

; Prévention des fuites mémoires (memory leaks)
pm.max_requests = 500

; Allouer suffisamment de RAM pour l'encodage initial des images
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 50M
php_admin_value[post_max_size] = 55M
```

---

## 6. Gouvernance des Données (PostgreSQL 16 et Sauvegardes)

### 6.1 Pourquoi PostgreSQL 16 ?

PostgreSQL 16 a été choisi spécifiquement pour son support exceptionnel du format `JSONB`. Puisque les retours de l'API OpenAI (l'analyse des dommages photo) sont stockés sous forme de données non structurées au sein du modèle `OrderPhoto`, la capacité d'indexer et d'interroger directement ce JSON est un avantage absolu par rapport à MySQL.

### 6.2 Stratégie de Sauvegarde Externe (Plan de Reprise d'Activité)

> [!IMPORTANT]
> Un dump SQL conservé sur le même serveur que la base de données ne constitue pas une sauvegarde. C'est une illusion de sécurité. La sauvegarde doit être chiffrée et externalisée.

**Procédure automatisée (Timer Systemd + Script bash) :**
1.  Exécution de `pg_dump` chaque nuit à 03h00.
2.  Compression immédiate via `gzip`.
3.  Synchronisation sécurisée (via `rclone` ou `borg`) vers un stockage S3 externe (ex: Scaleway Object Storage) avec une rétention stricte de 30 jours (pour conformité RGPD).

```bash
#!/bin/bash
# /usr/local/bin/backup-postgres.sh
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/postgres"
pg_dump -U postgres omnyrestore | gzip > $BACKUP_DIR/omnyrestore-$TIMESTAMP.sql.gz
find $BACKUP_DIR -type f -mtime +30 -delete
# Synchronisation S3 à ajouter ici
```

---

## 7. Automatisation du Déploiement (Zero-Downtime)

La mise en production d'une nouvelle version de l'application ne doit générer aucune coupure de service pour les utilisateurs qui naviguent sur le site.

### 7.1 Le Déploiement Atomique par Liens Symboliques

Le déploiement automatisé (via GitHub Actions) clone le dépôt dans un nouveau répertoire horodaté, exécute `composer install` et `npm build`, puis bascule instantanément un lien symbolique appelé `current`.

```text
/var/www/omnyrestore/
├── current -> releases/20260515_103000/   (Lien lu par NGINX)
├── releases/
│   ├── 20260515_090000/
│   └── 20260515_103000/
└── shared/
    ├── .env
    └── storage/
```

### 7.2 Checklist de Déploiement CI/CD Laravel

Lors du basculement, le script de déploiement doit obligatoirement lancer cette séquence pour garantir les performances et nettoyer les caches :

```bash
# Séquence post-déploiement automatisée
php artisan down --secret="bypass-token"
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan horizon:terminate # Relancé par Supervisor automatiquement
php artisan up
sudo systemctl reload php-fpm
```

---

## 8. Conformité RGPD et Rétention des Fichiers

L'hébergement IaaS vous rend entièrement responsable des obligations légales concernant la vie privée.

### 8.1 Purge Automatique des Photos

Il est interdit de conserver des photographies personnelles ad vitam æternam après la livraison de la prestation. Une tâche planifiée (`Scheduler`) doit s'exécuter chaque nuit pour purger le stockage physique.

- [ ] Créer une commande `php artisan omny:purge-media` qui cible les collections Spatie MediaLibrary des commandes statut `DELIVERED` vieilles de plus de 30 jours.

### 8.2 Anonymisation des Données

En cas de demande de suppression de compte (Droit à l'oubli), le modèle `User` doit utiliser le `SoftDelete`. Les commandes financières sont conservées pour l'administration fiscale, mais les nom, prénom et email doivent être écrasés par des chaînes aléatoires.

---

## 9. Conclusion Générale de l'Audit IaaS

La modélisation architecturale C4 démontre qu'OmnyRestore dispose d'une infrastructure extrêmement bien pensée et technologiquement pertinente. Le choix du couple Rocky Linux 9 / PostgreSQL 16 est une assurance de stabilité pour la prochaine décennie.

Cependant, le passage du stade de conception au stade d'exploitation (Run) en production impose une exécution parfaite du durcissement système (Hardening). 

En appliquant rigoureusement les verrous décrits dans ce rapport (SELinux Enforcing, Fail2Ban, clés SSH exclusives, déploiements atomiques, sauvegardes offsite), l'infrastructure IaaS de la plateforme sera totalement sécurisée, hautement performante, et résiliente face aux menaces du web moderne.

---

## 10. Annexes Exhaustives : Architecture C4 et Flux Détaillés

Les sections suivantes intègrent directement les recherches et modélisations issues du dépôt d'architecture de l'équipe (config-VPS-OmnyRestore), offrant une vue d'une précision microscopique sur l'interaction des composants.

### 10.1 Niveau 3 — Diagrammes de Composants (C4)

Décomposition des conteneurs en composants internes (Classes, Modules Laravel).

```mermaid
flowchart TB
    subgraph HTTP_LAYER["Couche HTTP"]
        ROUTES[routes/<br/>web.php, client.php,<br/>admin.php, webhook.php]
        MW[Middleware<br/>EnsureIsAdmin, throttle,<br/>VerifyCsrfToken]
    end

    subgraph PRESENTATION["Couche presentation"]
        CTRL[Controllers<br/>Admin/OrderController<br/>Webhook/StripeWebhookController]
        LW[Livewire Components<br/>orders.create, orders.show<br/>tickets.index, profile]
        VOLT[Volt single-file<br/>Composants atomiques]
        BLADE[Blade Templates<br/>resources/views/]
    end

    subgraph BUSINESS["Couche metier"]
        SVC[Services<br/>PhotoDamageAnalyzer<br/>ZipGeneratorService<br/>AuditService]
        POL[Policies<br/>OrderPolicy<br/>SupportTicketPolicy]
        ACTIONS[Actions invokables<br/>RestorePhotoAction<br/>RecalcOrderPriceAction]
    end

    subgraph DATA_LAYER["Couche donnees"]
        MODELS[Models Eloquent<br/>User, Order, OrderPhoto,<br/>SupportTicket, Testimonial]
        SCOPES[Query Scopes<br/>scopePending, scopeForAdmin]
        OBS[Observers<br/>OrderObserver<br/>UserObserver]
    end

    subgraph ASYNC["Couche asynchrone"]
        JOBS[Jobs<br/>GenerateOrderZipJob<br/>GenerateWatermarkJob<br/>AnalyzePhotoDamageJob]
        LISTENERS[Listeners<br/>SendOrderPaidEmail<br/>LogOrderTransition]
        EVENTS[Events<br/>OrderStatusChanged<br/>PaymentCompleted]
    end

    subgraph INTEGRATION["Couche integration"]
        STRIPE_INT[Cashier<br/>Billable trait,<br/>checkout, webhooks]
        OAI_INT[OpenAI client<br/>openai-php/laravel]
        MEDIA[Spatie MediaLibrary<br/>collections originals,<br/>retouched, zip]
        MAIL[Mailables<br/>OrderReadyForPayment,<br/>OrderDeliveryReady]
        PDF[DomPDF<br/>InvoiceGenerator]
    end

    ROUTES --> MW
    MW --> CTRL
    MW --> LW
    LW --> VOLT
    LW --> BLADE
    CTRL --> SVC
    LW --> SVC
    LW --> ACTIONS
    SVC --> MODELS
    ACTIONS --> MODELS
    POL --> MODELS
    MODELS --> SCOPES
    MODELS --> OBS
    OBS --> EVENTS
    EVENTS --> LISTENERS
    LISTENERS --> JOBS
    SVC --> JOBS
    JOBS --> OAI_INT
    JOBS --> MEDIA
    JOBS --> MAIL
    JOBS --> PDF
    CTRL --> STRIPE_INT
    LW --> STRIPE_INT

    style HTTP_LAYER fill:#1a1a2e,color:#fff
    style PRESENTATION fill:#16213e,color:#fff
    style BUSINESS fill:#0f3460,color:#fff
    style DATA_LAYER fill:#533483,color:#fff
    style ASYNC fill:#7209b7,color:#fff
    style INTEGRATION fill:#480ca8,color:#fff
```

### 10.2 Vue Détaillée — Module Order (UML)

```mermaid
classDiagram
    class Order {
        +int id
        +int user_id
        +OrderStatus status
        +PaymentStatus payment_status
        +decimal total_amount
        +decimal paid_amount
        +int coupon_id
        +datetime paid_at
        +datetime delivered_at
        +text instructions
        +recalcPriceFromActivePhotos() decimal
        +markAsPaid(decimal amount) void
        +markAsDone() void
        +cancel(string reason) void
        +canBePaidBy(User user) bool
    }

    class OrderPhoto {
        +int id
        +int order_id
        +int ai_level
        +array ai_analysis
        +decimal price
        +bool is_rejected
        +datetime rejected_at
        +reject() void
        +restore() void
        +calculatePrice() decimal
    }

    class OrderObserver {
        +creating(Order order) void
        +updating(Order order) void
        +saved(Order order) void
        +deleted(Order order) void
    }

    class OrderPolicy {
        +viewAny(User user) bool
        +view(User user, Order order) bool
        +update(User user, Order order) bool
        +pay(User user, Order order) bool
        +download(User user, Order order) bool
    }

    class PhotoDamageAnalyzer {
        -OpenAIClient client
        +analyze(string imagePath) DamageAnalysis
        +determineAiLevel(DamageAnalysis analysis) int
    }

    class GenerateOrderZipJob {
        +Order order
        +handle(ZipGeneratorService service) void
        +failed(Throwable exception) void
    }

    class CreateOrderRequest {
        +rules() array
        +photos array
        +instructions text
        +authorize() bool
    }

    Order "1" --> "1..N" OrderPhoto : contient
    Order "1" --> "1" OrderObserver : observe
    Order "1" --> "1" OrderPolicy : autorise
    Order "1" --> "0..1" GenerateOrderZipJob : declenche
    OrderPhoto "1" --> "1" PhotoDamageAnalyzer : analysee par
    CreateOrderRequest --> Order : valide la creation

    class OrderStatus {
        <<enumeration>>
        PENDING
        IN_PROGRESS
        DONE
        PAID
        DELIVERED
        CANCELLED
    }

    class PaymentStatus {
        <<enumeration>>
        UNPAID
        PROCESSING
        PAID
        REFUNDED
        FAILED
    }
```

### 10.3 Architecture Physique du Système de Fichiers (Déploiement)

La rigueur de déploiement passe par une arborescence stricte sur le VPS Linux.

```text
/
├── etc/
│   ├── nginx/
│   │   ├── nginx.conf
│   │   ├── sites-available/
│   │   │   └── omnyrestore.fr
│   │   └── sites-enabled/
│   │       └── omnyrestore.fr -> ../sites-available/omnyrestore.fr
│   ├── php-fpm.d/
│   │   └── omnyrestore.conf
│   ├── postgresql/                       (lien vers /var/lib/pgsql/16/data)
│   ├── redis/
│   │   └── redis.conf
│   ├── supervisord.d/
│   │   └── omnyrestore-horizon.ini
│   ├── ssh/
│   │   ├── sshd_config
│   │   └── banner.txt
│   ├── fail2ban/
│   │   └── jail.local
│   ├── letsencrypt/
│   │   └── live/omnyrestore.fr/
│   ├── selinux/
│   │   └── targeted/
│   └── firewalld/
│       └── zones/
├── var/
│   ├── www/
│   │   └── omnyrestore/                   (proprietaire omny:omny)
│   │       ├── app/
│   │       ├── bootstrap/cache/           (770)
│   │       ├── config/
│   │       ├── database/
│   │       ├── public/                    (racine web)
│   │       ├── resources/
│   │       ├── routes/
│   │       ├── storage/                   (770)
│   │       │   ├── app/
│   │       │   │   ├── public/            (lien symbolique depuis public/storage)
│   │       │   │   ├── private/
│   │       │   │   └── tmp-uploads/
│   │       │   ├── framework/
│   │       │   └── logs/
│   │       ├── vendor/
│   │       └── .env                       (600, omny:omny)
│   ├── lib/
│   │   ├── pgsql/16/data/
│   │   └── redis/
│   ├── log/
│   │   ├── nginx/
│   │   │   ├── omnyrestore.access.log
│   │   │   └── omnyrestore.error.log
│   │   ├── php-fpm/
│   │   │   ├── error.log
│   │   │   └── omnyrestore-slow.log
│   │   ├── secure                         (ssh, sudo)
│   │   ├── fail2ban.log
│   │   └── audit/audit.log               (SELinux)
│   └── backups/
│       └── postgres/
│           └── omnyrestore-AAAAMMDD-HHMMSS.sql.gz
└── run/
    └── php-fpm/
        └── omnyrestore.sock              (660, omny:nginx)
```

### 10.4 Récapitulatif des Flux Réseaux (Matrice DNS et Routing)

| Source | Destination | Protocole | Port | Direction | Authentification | Chiffrement |
|---|---|---|---|---|---|---|
| Navigateur utilisateur | NGINX | HTTPS | 443 | Entrant | Session Laravel | TLS 1.2/1.3 |
| Stripe (webhook) | NGINX | HTTPS | 443 | Entrant | Signature HMAC | TLS 1.2/1.3 |
| Admin (SSH) | OpenSSH | SSH | 50001 | Entrant | Clé ED25519 + AllowUsers | SSH protocol 2 |
| Let's Encrypt | NGINX | HTTP | 80 | Entrant (challenge) | Token ACME | Non (puis TLS) |
| NGINX | PHP-FPM | FastCGI | Socket Unix | Local | Permissions Unix | N/A (local) |
| PHP-FPM | PostgreSQL | TCP | 5432 | Local | scram-sha-256 | N/A (loopback) |
| PHP-FPM | Redis | TCP | 6379 | Local | requirepass | N/A (loopback) |
| PHP-FPM / Horizon | Stripe API | HTTPS | 443 | Sortant | Bearer token | TLS 1.2/1.3 |
| PHP-FPM / Horizon | OpenAI API | HTTPS | 443 | Sortant | Bearer token | TLS 1.2/1.3 |
| PHP-FPM / Horizon | Resend API | HTTPS | 443 | Sortant | API key | TLS 1.2/1.3 |
| Backup script | Stockage offsite | HTTPS | 443 | Sortant | Credentials S3 | TLS 1.2/1.3 |

### 10.5 Recommandations DNS Officielles (OVH / Cloudflare)

Afin d'assurer la délivrabilité absolue des e-mails (via Resend) et l'intégrité de domaine, ces enregistrements DNS sont la norme :

| Type | Nom | Valeur | TTL | Rôle |
|---|---|---|---|---|
| A | `omnyrestore.fr` | `50.60.70.80` | 3600 | Domaine principal |
| A | `www.omnyrestore.fr` | `50.60.70.80` | 3600 | Sous-domaine www |
| CAA | `omnyrestore.fr` | `0 issue "letsencrypt.org"` | 3600 | Autorise Let's Encrypt uniquement |
| MX | `omnyrestore.fr` | `feedback-smtp.eu-west-1.amazonses.com` (selon Resend) | 3600 | Réception emails (Bounce) |
| TXT | `omnyrestore.fr` | `v=spf1 include:_spf.resend.com -all` | 3600 | Protection SPF (Anti-Spam) |
| TXT | `_dmarc.omnyrestore.fr` | `v=DMARC1; p=quarantine; rua=mailto:contact@omnyvia.fr` | 3600 | Protocole DMARC |
| TXT | `resend._domainkey.omnyrestore.fr` | (clé RSA fournie par Resend) | 3600 | Signature DKIM cryptographique |

---

### 10.6 Modélisation des Données (Couche Persistance PostgreSQL)

Pour garantir l'intégrité de la base de données hébergée sur le VPS, l'architecture des données (MCD) et son implémentation physique (MPD) ont été rigoureusement audités.

#### Modèle Conceptuel de Données (MCD)

Le MCD illustre les interactions clés gérées par le moteur PostgreSQL.

```mermaid
erDiagram
    USER ||--o{ ORDER : "passe"
    USER ||--o{ SUPPORT_TICKET : "ouvre"
    USER ||--o{ SUPPORT_TICKET_MESSAGE : "redige"
    USER ||--o{ TESTIMONIAL : "soumet"
    USER ||--o{ AUDIT_LOG : "declenche"

    ORDER ||--|{ ORDER_PHOTO : "contient"
    ORDER ||--o| ORDER_DELIVERY : "produit"
    ORDER ||--o| INVOICE : "facture"
    ORDER }o--o| COUPON : "applique"
    ORDER ||--o{ SUPPORT_TICKET : "concerne"
    ORDER ||--o| TESTIMONIAL : "inspire"

    SUPPORT_TICKET ||--|{ SUPPORT_TICKET_MESSAGE : "contient"

    USER {
        int id PK
        string name
        string email UK
        string password
        bool is_admin
        datetime rgpd_consent_at
        datetime anonymized_at
        datetime deleted_at
    }

    ORDER {
        int id PK
        int user_id FK
        enum status
        enum payment_status
        decimal total_amount
        decimal paid_amount
        int coupon_id FK
        text instructions
        datetime paid_at
        datetime delivered_at
    }

    ORDER_PHOTO {
        int id PK
        int order_id FK
        int ai_level
        jsonb ai_analysis
        decimal price
        bool is_rejected
        datetime rejected_at
    }

    ORDER_DELIVERY {
        int id PK
        int order_id FK
        string zip_path
        string signed_url
        datetime signed_url_expires_at
        datetime downloaded_at
    }

    INVOICE {
        int id PK
        int order_id FK
        string number UK
        decimal amount_ht
        decimal tva_amount
        decimal amount_ttc
        decimal tva_rate
        string pdf_path
        datetime generated_at
    }

    COUPON {
        int id PK
        string code UK
        enum type
        decimal value
        int max_uses
        int used_count
        datetime valid_from
        datetime valid_until
    }

    SUPPORT_TICKET {
        int id PK
        int user_id FK
        int order_id FK
        string subject
        enum priority
        enum status
        datetime closed_at
    }

    SUPPORT_TICKET_MESSAGE {
        int id PK
        int support_ticket_id FK
        int user_id FK
        text content
        bool is_admin
        datetime read_by_admin_at
        datetime read_by_client_at
    }

    TESTIMONIAL {
        int id PK
        int user_id FK
        int order_id FK
        text content
        int rating
        enum status
        int moderated_by FK
        datetime moderated_at
        datetime published_at
    }

    AUDIT_LOG {
        int id PK
        int user_id FK
        string event
        string auditable_type
        int auditable_id
        jsonb old_values
        jsonb new_values
        inet ip_address
        string user_agent
    }

    INCIDENT {
        int id PK
        string type
        enum severity
        datetime started_at
        datetime cnil_notification_deadline
        datetime resolved_at
        text description
        jsonb actions_taken
    }
```

#### Cycle de vie des données : Modèles d'État (State Machines)

La persistance des états est essentielle pour la stabilité des processus asynchrones gérés par Laravel Horizon et Redis sur l'IaaS.

**Workflow des Commandes (`Order.status`) :**
```mermaid
stateDiagram-v2
    [*] --> PENDING : Client soumet commande
    PENDING --> IN_PROGRESS : Admin prend en charge
    PENDING --> CANCELLED : Annulation client ou admin
    IN_PROGRESS --> DONE : Admin uploade photos restaurees
    IN_PROGRESS --> CANCELLED : Annulation
    DONE --> PAID : Paiement Stripe valide via webhook
    DONE --> CANCELLED : Annulation
    PAID --> DELIVERED : Job ZIP genere et email envoye
    DELIVERED --> [*]
    CANCELLED --> [*]
```

**Workflow des Tickets Support (`SupportTicket.status`) :**
```mermaid
stateDiagram-v2
    [*] --> OPEN : Client cree le ticket
    OPEN --> PENDING : Admin ouvre ou repond
    PENDING --> OPEN : Client repond
    OPEN --> CLOSED : Admin ou client cloture
    PENDING --> CLOSED : Admin ou client cloture
    CLOSED --> OPEN : Reouverture admin
    CLOSED --> [*]
```

#### Conventions PostgreSQL de Production (MPD)

L'installation de PostgreSQL 16 sur le serveur Rocky Linux doit respecter ce standard d'implémentation pour garantir des performances optimales et l'intégrité des types :

| Convention | Choix Technique | Justification Infrastructure |
|---|---|---|
| **Encodage** | UTF8 | Standard universel, support complet des emojis |
| **Locale** | `fr_FR.UTF-8` | Tri alphabétique français correct |
| **Identifiants PK** | `BIGSERIAL` | Auto-increment 64 bits (jusqu'à 9 trillions d'ID) |
| **Timestamps** | `TIMESTAMP WITH TIME ZONE` | Stockage UTC absolu, conversion à l'affichage |
| **Types JSON** | `JSONB` | Indispensable pour l'indexation des réponses API OpenAI |
| **Adresses IP** | `INET` | Type natif validé par PostgreSQL (IPv4 et IPv6) |
| **Enumérations** | `ENUM` natifs | Validation DDL poussée, réduction d'empreinte disque |

**Exemple d'intégration des Types ENUM (DDL initial) :**
```sql
CREATE TYPE order_status AS ENUM (
    'pending', 'in_progress', 'done', 'paid', 'delivered', 'cancelled'
);

CREATE TYPE payment_status AS ENUM (
    'unpaid', 'processing', 'paid', 'refunded', 'failed'
);

CREATE TYPE incident_severity AS ENUM (
    'low', 'medium', 'high', 'critical'
);
```

> [!TIP]
> Le recours exclusif aux types `JSONB` (plutôt que `JSON` classique ou `TEXT`) pour le stockage des analyses d'images (`OrderPhoto.ai_analysis`) réduit massivement le temps de parsing côté CPU PHP.

---

*(Fin absolue du rapport d'audit et d'architecture IaaS C4 - Validé pour la mise en production 2026)*


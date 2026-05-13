# 🚀 Guide de déploiement Production — OmnyRestore

> OVH VPS · PostgreSQL 16 · Nginx · Redis · Let's Encrypt · CI/CD GitHub Actions

---

## 1. CHOIX DE L'HÉBERGEMENT OVH

### VPS recommandé : VPS Comfort (ou Elite)

| Spec | Valeur |
|---|---|
| CPU | 4 vCores |
| RAM | 8 Go |
| SSD | 160 Go NVMe |
| Trafic | Illimité |
| OS | Ubuntu 24.04 LTS |
| Prix | ~28 €/mois |

> **Commande OVH** : [ovhcloud.com/fr/vps](https://www.ovhcloud.com/fr/vps/)  
> Choisir la région **France (GRA)** pour la conformité RGPD.

### Nom de domaine

| Recommandation | Justification |
|---|---|
| `omnyrestore.fr` | Extension française, crédibilité locale |
| `omnyrestore.com` | Portée internationale |
| Prix `.fr` | ~7 €/an chez OVH |

```
DNS A    @           → IP_VPS
DNS A    www         → IP_VPS
DNS CNAME mail       → smtp.resend.com (ou Brevo)
DNS TXT  @           → "v=spf1 include:spf.resend.com ~all"
DNS TXT  _dmarc      → "v=DMARC1; p=quarantine; rua=mailto:dmarc@omnyrestore.fr"
```

---

## 2. CONFIGURATION INITIALE DU VPS

```bash
# Connexion initiale
ssh root@IP_VPS

# Mise à jour
apt update && apt upgrade -y

# Créer un utilisateur non-root
adduser deploy
usermod -aG sudo deploy

# Copier les clés SSH
rsync --archive --chown=deploy:deploy ~/.ssh /home/deploy

# Désactiver root SSH
nano /etc/ssh/sshd_config
# PermitRootLogin no
# PasswordAuthentication no
# PubkeyAuthentication yes
systemctl restart sshd

# Firewall
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

---

## 3. INSTALLATION PHP 8.3 + NGINX

```bash
# PHP 8.3
add-apt-repository ppa:ondrej/php -y
apt install -y php8.3-fpm php8.3-cli php8.3-pgsql php8.3-redis \
  php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
  php8.3-gd php8.3-imagick php8.3-intl php8.3-bcmath

# Nginx
apt install -y nginx

# Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

---

## 4. CONFIGURATION NGINX

```nginx
# /etc/nginx/sites-available/omnyrestore
server {
    listen 80;
    server_name omnyrestore.fr www.omnyrestore.fr;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name omnyrestore.fr www.omnyrestore.fr;

    root /var/www/omnyrestore/public;
    index index.php;

    # SSL — Let's Encrypt (Certbot)
    ssl_certificate     /etc/letsencrypt/live/omnyrestore.fr/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/omnyrestore.fr/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;
    ssl_session_cache   shared:SSL:10m;
    ssl_session_timeout 1d;

    # HSTS
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;

    # Sécurité
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    server_tokens off;

    # Taille upload (photos originales)
    client_max_body_size 50M;

    # Laravel
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Interdire l'accès aux fichiers sensibles
    location ~ /\. { deny all; }
    location ~ /storage { deny all; }

    # Cache statique
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Logs
    access_log /var/log/nginx/omnyrestore_access.log;
    error_log  /var/log/nginx/omnyrestore_error.log;
}
```

```bash
# Activer le site
ln -s /etc/nginx/sites-available/omnyrestore /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# Certificat SSL
apt install -y certbot python3-certbot-nginx
certbot --nginx -d omnyrestore.fr -d www.omnyrestore.fr
```

---

## 5. POSTGRESQL 16 SUR OVH

```bash
# Installation
apt install -y postgresql-16 postgresql-client-16

# Créer la base et l'utilisateur
sudo -u postgres psql

CREATE USER omnyrestore_user WITH PASSWORD 'MOT_DE_PASSE_FORT_32_CHARS';
CREATE DATABASE omnyrestore OWNER omnyrestore_user ENCODING 'UTF8' LC_COLLATE 'fr_FR.UTF-8' LC_CTYPE 'fr_FR.UTF-8';
GRANT ALL PRIVILEGES ON DATABASE omnyrestore TO omnyrestore_user;
\q
```

```bash
# Sécuriser pg_hba.conf
nano /etc/postgresql/16/main/pg_hba.conf
# local   omnyrestore   omnyrestore_user   scram-sha-256

# Ne pas exposer PostgreSQL au réseau public
nano /etc/postgresql/16/main/postgresql.conf
# listen_addresses = 'localhost'

systemctl restart postgresql

# Sauvegardes automatiques
# Ajouter dans crontab de l'utilisateur deploy :
0 3 * * * pg_dump -U omnyrestore_user omnyrestore | gzip > /backups/db_$(date +\%Y\%m\%d).sql.gz
```

---

## 6. REDIS

```bash
apt install -y redis-server

# Sécuriser Redis
nano /etc/redis/redis.conf
# bind 127.0.0.1
# requirepass REDIS_PASSWORD_FORT
# maxmemory 512mb
# maxmemory-policy allkeys-lru

systemctl enable redis-server
systemctl start redis-server
```

---

## 7. DÉPLOIEMENT DE L'APPLICATION

```bash
# Créer le dossier
mkdir -p /var/www/omnyrestore
chown -R deploy:www-data /var/www/omnyrestore
chmod -R 755 /var/www/omnyrestore

# Cloner le dépôt (branche main uniquement)
sudo -u deploy git clone -b main git@github.com:zyrass/OmnyRestore.git /var/www/omnyrestore

# Configurer l'environnement
cd /var/www/omnyrestore
cp .env.example .env
nano .env  # Remplir toutes les variables (voir section 8)

# Installer les dépendances
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Permissions storage
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Générer la clé
php artisan key:generate

# Migrations
php artisan migrate --force

# Caches production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 8. .ENV PRODUCTION COMPLET

```bash
APP_NAME="OmnyRestore"
APP_ENV=production
APP_KEY=base64:GENERER_AVEC_php_artisan_key:generate
APP_DEBUG=false
APP_URL=https://omnyrestore.fr

APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=fr_FR

LOG_CHANNEL=stack
LOG_LEVEL=info
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/...

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=omnyrestore
DB_USERNAME=omnyrestore_user
DB_PASSWORD=MOT_DE_PASSE_FORT_32_CHARS

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict

QUEUE_CONNECTION=redis
CACHE_STORE=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=REDIS_PASSWORD_FORT
REDIS_PORT=6379

FILESYSTEM_DISK=s3
MEDIA_DISK=s3
DELIVERY_DISK=s3

AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
AWS_DEFAULT_REGION=eu-west-3
AWS_BUCKET=omnyrestore-media
AWS_DELIVERY_BUCKET=omnyrestore-deliveries
AWS_USE_PATH_STYLE_ENDPOINT=false

MAIL_MAILER=resend
MAIL_FROM_ADDRESS=contact@omnyrestore.fr
MAIL_FROM_NAME="OmnyRestore"
RESEND_API_KEY=re_VOTRE_CLE_RESEND

STRIPE_KEY=pk_live_VOTRE_CLE_PUBLIQUE
STRIPE_SECRET=sk_live_VOTRE_CLE_SECRETE
STRIPE_WEBHOOK_SECRET=whsec_VOTRE_SECRET_WEBHOOK
CASHIER_CURRENCY=eur
CASHIER_CURRENCY_LOCALE=fr_FR

OPENAI_API_KEY=sk-VOTRE_CLE_OPENAI
OPENAI_MODEL=gpt-4o

HORIZON_DARK_MODE=true
BCRYPT_ROUNDS=12
```

---

## 9. WORKERS (QUEUES + SCHEDULER)

### Supervisor pour Laravel Horizon

```bash
apt install -y supervisor

# /etc/supervisor/conf.d/omnyrestore-horizon.conf
[program:omnyrestore-horizon]
process_name=%(program_name)s
command=php /var/www/omnyrestore/artisan horizon
autostart=true
autorestart=true
user=deploy
redirect_stderr=true
stdout_logfile=/var/log/supervisor/omnyrestore-horizon.log

# /etc/supervisor/conf.d/omnyrestore-scheduler.conf
[program:omnyrestore-scheduler]
process_name=%(program_name)s
command=/bin/bash -c "while true; do php /var/www/omnyrestore/artisan schedule:run; sleep 60; done"
autostart=true
autorestart=true
user=deploy
redirect_stderr=true
stdout_logfile=/var/log/supervisor/omnyrestore-scheduler.log
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start omnyrestore-horizon
supervisorctl start omnyrestore-scheduler
```

---

## 10. SÉCURISATION DE /HORIZON (IP WHITELIST + GATE)

Laravel Horizon est protégé par **deux couches de sécurité** :
1. **Gate Laravel** (`viewHorizon`) — uniquement les admins peuvent accéder
2. **Nginx IP whitelist** — le path `/horizon` n'est accessible que depuis votre IP fixe

> ⚠️ La gate Laravel seule ne suffit pas : si `/horizon` est exposé publiquement, un attaquant peut tenter du brute-force ou exploiter des vulnérabilités de Horizon lui-même.

### Configuration Nginx (à ajouter dans le bloc server HTTPS)

```nginx
# Protection /horizon — Restreindre à l'IP de l'administrateur uniquement
# Ajouter dans /etc/nginx/sites-available/omnyrestore, dans le bloc server HTTPS

location ^~ /horizon {
    # ─── IP whitelist ───────────────────────────────────────────────────────
    # Remplacer par vos IPs réelles (admin + VPN d'entreprise si applicable)
    allow 1.2.3.4;        # IP fixe admin principal
    allow 5.6.7.8;        # IP VPN entreprise (optionnel)
    deny all;             # Bloquer tout le reste → 403
    # ────────────────────────────────────────────────────────────────────────

    # Passer au PHP-FPM (même config que le bloc location ~ \.php$)
    try_files $uri $uri/ /index.php?$query_string;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
}
```

```bash
# Tester la config Nginx
nginx -t

# Recharger sans coupure
systemctl reload nginx
```

### Vérification

```bash
# Depuis une IP NON autorisée → doit retourner 403
curl -I https://omnyrestore.fr/horizon

# Depuis votre IP → doit retourner 200 (ou 302 vers /horizon/dashboard)
curl -I https://omnyrestore.fr/horizon
```

### Gate Laravel (déjà configurée dans HorizonServiceProvider)

```php
// app/Providers/HorizonServiceProvider.php
Gate::define('viewHorizon', function (User $user) {
    return $user->role === 'admin';
});
```

> 💡 Si votre IP change (connexion mobile, déplacement), pensez à mettre à jour la whitelist Nginx OU configurez un VPN avec IP fixe.

---

## 11. CI/CD — GITHUB ACTIONS

### Architecture du pipeline

```
Push branche test
    └── CI: Lint + Tests (PHPUnit + Pest)
            └── si ✅ PASS → PR auto vers main
                    └── CD: Deploy vers VPS OVH
```

### Secrets GitHub requis

```
Settings → Secrets and variables → Actions → New repository secret

VPS_HOST          = IP ou domaine du VPS
VPS_USER          = deploy
VPS_SSH_KEY       = Clé privée SSH (contenu entier)
VPS_PORT          = 22
APP_KEY           = base64:...
DB_PASSWORD       = ...
AWS_ACCESS_KEY_ID = ...
AWS_SECRET_ACCESS_KEY = ...
STRIPE_SECRET     = sk_live_...
STRIPE_WEBHOOK_SECRET = whsec_...
RESEND_API_KEY    = re_...
OPENAI_API_KEY    = sk-...
SLACK_WEBHOOK_URL = https://hooks.slack.com/...
```

### Workflow CI — `.github/workflows/ci.yml`

```yaml
name: CI — Tests & Qualité

on:
  push:
    branches: [test, main]
  pull_request:
    branches: [main]

jobs:
  tests:
    name: PHPUnit Tests
    runs-on: ubuntu-24.04

    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_DB: omnyrestore_test
          POSTGRES_USER: postgres
          POSTGRES_PASSWORD: secret
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
        ports: ['5432:5432']

      redis:
        image: redis:7
        options: --health-cmd "redis-cli ping"
        ports: ['6379:6379']

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP 8.3
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pgsql, pdo_pgsql, redis, mbstring, xml, curl, zip, gd
          coverage: xdebug

      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: vendor
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'

      - name: Install NPM & Build
        run: npm ci && npm run build

      - name: Prepare .env.testing
        run: |
          cp .env.example .env.testing
          echo "APP_ENV=testing" >> .env.testing
          echo "DB_CONNECTION=pgsql" >> .env.testing
          echo "DB_HOST=127.0.0.1" >> .env.testing
          echo "DB_PORT=5432" >> .env.testing
          echo "DB_DATABASE=omnyrestore_test" >> .env.testing
          echo "DB_USERNAME=postgres" >> .env.testing
          echo "DB_PASSWORD=secret" >> .env.testing
          echo "QUEUE_CONNECTION=sync" >> .env.testing
          echo "MAIL_MAILER=array" >> .env.testing
          echo "SESSION_DRIVER=array" >> .env.testing
          echo "CACHE_STORE=array" >> .env.testing

      - name: Generate App Key
        run: php artisan key:generate --env=testing

      - name: Run Migrations
        run: php artisan migrate --env=testing --force

      - name: Run Tests
        run: php artisan test --env=testing --parallel

      - name: Laravel Pint (Code Style)
        run: ./vendor/bin/pint --test

      - name: Security Audit
        run: composer audit

      - name: Notify Slack on failure
        if: failure()
        uses: slackapi/slack-github-action@v1
        with:
          payload: '{"text":"❌ CI échoué sur `${{ github.ref_name }}` — ${{ github.actor }}"}'
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}
```

### Workflow CD — `.github/workflows/deploy.yml`

```yaml
name: CD — Deploy Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    name: Deploy to OVH VPS
    runs-on: ubuntu-24.04
    environment: production

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup SSH
        uses: webfactory/ssh-agent@v0.9.0
        with:
          ssh-private-key: ${{ secrets.VPS_SSH_KEY }}

      - name: Add VPS to known hosts
        run: ssh-keyscan -H ${{ secrets.VPS_HOST }} >> ~/.ssh/known_hosts

      - name: Deploy via SSH
        run: |
          ssh ${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }} -p ${{ secrets.VPS_PORT }} << 'ENDSSH'
            set -e
            cd /var/www/omnyrestore

            # Mode maintenance
            php artisan down --refresh=15 --secret="BYPASS_TOKEN"

            # Pull du code
            git pull origin main

            # Dépendances
            composer install --no-dev --optimize-autoloader --no-interaction

            # Build assets
            npm ci && npm run build

            # Migrations
            php artisan migrate --force

            # Caches
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan event:cache

            # Redémarrer Horizon
            php artisan horizon:terminate
            supervisorctl restart omnyrestore-horizon

            # Fin maintenance
            php artisan up

            echo "✅ Déploiement terminé."
          ENDSSH

      - name: Notify Slack success
        uses: slackapi/slack-github-action@v1
        with:
          payload: '{"text":"✅ Production déployée — v${{ github.sha }}"}'
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}

      - name: Notify Slack on failure
        if: failure()
        uses: slackapi/slack-github-action@v1
        with:
          payload: '{"text":"🔥 ÉCHEC déploiement production — intervention requise"}'
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}
```

---

## 12. PROTECTION BRANCHE MAIN (GitHub)

```
GitHub → Settings → Branches → Add rule → Branch name: main

✅ Require a pull request before merging
✅ Require approvals: 1
✅ Require status checks to pass before merging
   → Ajouter: "PHPUnit Tests"
✅ Require conversation resolution before merging
✅ Require linear history
✅ Do not allow bypassing above settings
❌ Allow force pushes (désactiver)
❌ Allow deletions (désactiver)
```

---

## 13. SÉCURITÉ DÉPÔT GITHUB

```
Settings → Security

✅ Private repository
✅ Dependabot alerts
✅ Dependabot security updates
✅ Code scanning (CodeQL)
✅ Secret scanning
✅ Push protection (bloque les commits avec des secrets)
```

```yaml
# .github/dependabot.yml
version: 2
updates:
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
    open-pull-requests-limit: 5
  - package-ecosystem: "npm"
    directory: "/"
    schedule:
      interval: "weekly"
  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "monthly"
```

---

## 14. CONFIGURATION S3 PRODUCTION (AWS eu-west-3)

### Création des buckets

```bash
# Bucket médias (photos originales + restaurées + filigranes)
aws s3api create-bucket \
  --bucket omnyrestore-media \
  --region eu-west-3 \
  --create-bucket-configuration LocationConstraint=eu-west-3

# Bucket livraisons (ZIP)
aws s3api create-bucket \
  --bucket omnyrestore-deliveries \
  --region eu-west-3 \
  --create-bucket-configuration LocationConstraint=eu-west-3

# Bloquer accès public sur les deux
for BUCKET in omnyrestore-media omnyrestore-deliveries; do
  aws s3api put-public-access-block \
    --bucket $BUCKET \
    --public-access-block-configuration \
      BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true
done

# Activer le versioning (protection accidentelle)
for BUCKET in omnyrestore-media omnyrestore-deliveries; do
  aws s3api put-bucket-versioning \
    --bucket $BUCKET \
    --versioning-configuration Status=Enabled
done

# Chiffrement AES-256 par défaut
for BUCKET in omnyrestore-media omnyrestore-deliveries; do
  aws s3api put-bucket-encryption \
    --bucket $BUCKET \
    --server-side-encryption-configuration \
      '{"Rules":[{"ApplyServerSideEncryptionByDefault":{"SSEAlgorithm":"AES256"}}]}'
done
```

### Lifecycle rules (purge automatique après 6 mois)

```json
{
  "Rules": [
    {
      "ID": "PurgeExpiredMedia",
      "Status": "Enabled",
      "Filter": { "Prefix": "" },
      "Expiration": { "Days": 180 },
      "NoncurrentVersionExpiration": { "NoncurrentDays": 30 }
    }
  ]
}
```

```bash
aws s3api put-bucket-lifecycle-configuration \
  --bucket omnyrestore-media \
  --lifecycle-configuration file://s3-lifecycle.json
```

---

## 15. STRIPE — PASSAGE EN PRODUCTION

```bash
# 1. Récupérer les clés LIVE depuis dashboard.stripe.com
# Dashboard → Developers → API keys → Live mode

# 2. Créer le webhook HTTPS production
stripe listen --forward-to https://omnyrestore.fr/webhook/stripe
# OU via dashboard : Developers → Webhooks → Add endpoint
# URL : https://omnyrestore.fr/webhook/stripe
# Événements à écouter :
#   checkout.session.completed
#   payment_intent.payment_failed

# 3. Vérifier les paramètres Stripe
CASHIER_CURRENCY=eur
CASHIER_CURRENCY_LOCALE=fr_FR

# 4. Activer les taxes automatiques Stripe Tax (optionnel)
# Dashboard → Tax → Enable

# 5. Configurer les emails Stripe
# Dashboard → Settings → Email notifications
```

---

## 16. EMAILS — RESEND EN PRODUCTION

```bash
# 1. Vérifier le domaine sur resend.com
# Dashboard → Domains → Add → omnyrestore.fr
# Ajouter les enregistrements DNS fournis

# 2. Clé API production
RESEND_API_KEY=re_VOTRE_CLE_PRODUCTION

# 3. Test d'envoi
php artisan tinker
Mail::to('test@omnyrestore.fr')->send(new \App\Mail\OrderDeliveryReady(...));
```

---

## 17. PURGE DES DONNÉES DE TEST


```bash
# ATTENTION : À exécuter uniquement AVANT la mise en prod
# JAMAIS sur une base avec des vrais clients

php artisan migrate:fresh --seed  # Recrée tout depuis zéro

# OU purge manuelle sélective :
php artisan tinker

# Supprimer tous les users sauf admin
\App\Models\User::where('role', '!=', 'admin')->forceDelete();

# Supprimer toutes les commandes de test
\App\Models\Order::truncate();

# Supprimer tous les témoignages de test
\App\Models\Testimonial::truncate();

# Supprimer les médias
php artisan media:purge-expired  # ou manuellement via Spatie

# Vider Redis
redis-cli FLUSHALL

# Vider les logs
truncate -s 0 storage/logs/laravel.log
```

---

## 18. CHECKLIST MISE EN PRODUCTION

```
INFRASTRUCTURE
[ ] VPS OVH commandé (Ubuntu 24.04)
[ ] Nom de domaine omnyrestore.fr configuré
[ ] DNS propagé (vérifier avec dig omnyrestore.fr)
[ ] HTTPS / Let's Encrypt actif
[ ] Nginx configuré et testé
[ ] PostgreSQL 16 installé et sécurisé
[ ] Redis installé et sécurisé
[ ] Supervisor configuré (Horizon + Scheduler)
[ ] Fail2ban installé

CI/CD GITHUB ACTIONS
[ ] Branche main protégée
[ ] Secrets GitHub configurés (14 secrets)
[ ] Workflow CI créé (.github/workflows/ci.yml)
[ ] Workflow CD créé (.github/workflows/deploy.yml)
[ ] Dependabot configuré
[ ] CodeQL activé
[ ] Secret scanning activé

APPLICATION
[ ] .env production renseigné
[ ] APP_DEBUG=false
[ ] Caches vidés et reconstruits
[ ] Migrations lancées
[ ] Permissions storage correctes

SERVICES EXTERNES
[ ] Buckets S3 créés et privés
[ ] IAM utilisateur dédié configuré
[ ] Lifecycle S3 configuré
[ ] Stripe LIVE activé
[ ] Webhook Stripe pointant vers HTTPS
[ ] Domaine Resend vérifié
[ ] DNS DMARC/SPF configurés

SÉCURITÉ
[ ] Middleware SecurityHeaders ajouté
[ ] Rate limiting production
[ ] Fail2ban SSH + Nginx
[ ] Sauvegardes PostgreSQL automatiques
[ ] Monitoring uptime (UptimeRobot)
[ ] Alertes Slack configurées

POST-DÉPLOIEMENT
[ ] Test complet du tunnel client (inscription → commande → paiement → téléchargement)
[ ] Test du panel admin
[ ] Test webhook Stripe
[ ] Test envoi email
[ ] Test suppression compte RGPD
[ ] Vérifier securityheaders.com → Grade A
[ ] Vérifier SSL Labs → Grade A+
```

---

*Document généré le 2026-05-13 — OmnyRestore v0.15.0*

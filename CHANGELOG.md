# Changelog

Tous les changements notables d'**OmnyRestore** sont documentés ici.

Ce projet respecte le [Semantic Versioning](https://semver.org/) et les conventions [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [2.2.0] — 2026-05-17 — Accès Lecture Seule Marketing & Rétablissement Suite de Tests 100% Verte

### 🔑 Rôle Marketing en Lecture Seule (Détail Commande)
- **Autorisation de Consultation** :
  - Ajustement des middlewares de sécurité pour permettre aux utilisateurs de type `marketing` d'accéder individuellement aux fiches de commandes sans compromettre le blocage des tickets et de la liste globale des commandes.
- **Sécurisation Multi-Couche de l'Interface** :
  - Intégration de contrôles stricts de sécurité côté backend (`abort_if(..., 403)`) bloquant toutes les actions d'écriture et de mutation sur le composant Livewire (ex. prise en charge, annulation, uploader les photos, modifier les notes internes, signaler CSAM/NSFW, rembourser Stripe).
  - Masquage intelligent dans l'interface utilisateur (UI) de tous les boutons et zones d'action pour préserver l'intégrité opérationnelle.
  - Désactivation esthétique de la zone d'édition des notes internes.

### 🧪 Rétablissement et Alignement des Tests Légataires (100% Verts)
- **Tests d'Authentification (`AuthenticationTest`)** :
  - Adaptation des redirections de connexion pour valider les comportements distincts lors de la première connexion (redirection vers le profil) et des connexions ultérieures (vers la liste des commandes).
- **Tests d'Enregistrement (`RegistrationTest`)** :
  - Ajustement des assertions pour refléter la nouvelle page de confirmation d'inscription et s'assurer que le nouvel inscrit demeure invité (`assertGuest`) en attente de validation de son adresse de contact réelle.
- **Tests de Vérification (`EmailVerificationTest`)** :
  - Redirection du lien de vérification d'email vers la page de connexion sécurisée.
- **Tests de Sécurité RBAC (`AccessControlTest`)** :
  - Écriture d'un test d'intégration complet garantissant le blocage des actions Livewire en 403 Forbidden et la bonne restitution visuelle pour le marketing.

---

## [2.1.1] — 2026-05-17 — Durcissement RBAC & Alignement Habilitations (Marjorie RBAC Fix)

### 🔒 Restriction Stricte de Sécurité (Routage)
- **Bloquage de sécurité au Routage (Middleware)** :
  - Mise à niveau du middleware `EnsureIsStaff.php` pour accepter un paramètre de rôle interdit (ex. `staff:marketing` ou `staff:operator`).
  - Restructuration et cloisonnement des groupes de routes dans [admin.php](file:///g:/Omnyvia/omnyretouche/routes/admin.php) :
    - Blocage total des routes de **Commandes** (`/admin/orders/*`) et de **Tickets** (`/admin/tickets/*`) pour le rôle `marketing` (renvoie une exception HTTP 403 Forbidden).
    - Blocage total des routes de **Coupons** (`/admin/coupons*`) et d'**Avis** (`/admin/testimonials*`) pour le rôle `operator` (renvoie une exception HTTP 403 Forbidden).
- **Sécurisation du bouton "Accéder au CA"** :
  - Restriction de l'accès au chiffre d'affaires accumulé aux seuls Super-Administrateurs sur la liste des clients.

### 🛠️ Refonte de la Navbar (Interface)
- **Visibilité Contextuelle RBAC** dans [navbar.blade.php](file:///g:/Omnyvia/omnyretouche/resources/views/livewire/layout/navbar.blade.php) :
  - Masquage automatique des onglets `Commandes` et `Tickets` pour le rôle `marketing`.
  - Masquage automatique des onglets `Avis` et `Réductions` pour le rôle `operator`.

### 🧪 Tests d'Intégration d'Habilitations
- Écriture et passage au vert de 4 tests de fonctionnalités dans `AccessControlTest.php` couvrant les autorisations et les interdictions strictes pour les rôles `marketing` et `operator`.

---

## [2.1.0] — 2026-05-17 — Cybersécurité & Isolation des Emails (Phase 1.6)

### 🛡️ Cybersécurité & Séparation des Emails
- **Séparation Email d'Identification et Contact Réel** :
  - **Base de Données** : Ajout de la colonne `contact_email` (nullable string) après la colonne `email` sur la table `users`.
  - **Modèle `User`** : Intégration de `contact_email` dans les propriétés mass-assignables `$fillable` et typées `$casts`.
  - **Routage Intelligent des Notifications** : Surcharge de la méthode native Laravel `routeNotificationForMail()` pour rediriger de manière transparente toutes les notifications réelles (telles que les invitations, réinitialisations de mot de passe, alertes de sécurité) vers l'e-mail de contact réel (`contact_email`) s'il est configuré, tout en conservant l'adresse `email` d'origine comme identifiant de connexion (qui peut être fictive, ex: `collab@omny.internal`).
- **Évolution de l'Interface Collaborateurs Premium (`/admin/team/roles`)** :
  - **Modal de Création** : Ajout d'un champ facultatif "Adresse E-mail de Sécurité (Réelle)" avec des explications claires sur la prévention contre les compromissions futures.
  - **Édition Rapide Inline** : Intégration de la modification de l'e-mail de contact réel directement dans le formulaire d'édition rapide inline aux côtés du rôle.
  - **Badge de Protection** : Affichage d'un élégant badge de sécurité `🛡️ Contact : ...` sous le nom du collaborateur si son adresse de contact réelle diffère de son identifiant de connexion.

### 🧪 Validation & Sécurité
- Écriture d'une suite de tests robustes (`ContactEmailSecurityTest.php`) couvrant la surcharge de routage, l'envoi effectif de réinitialisation de mot de passe vers l'email sécurisé, ainsi que la création et modification Livewire.

---

## [2.0.0] — 2026-05-17 — L'Écosystème Collaboratif, Licences & Transparence (OmnyRestore v2.0)

Cette mise à jour majeure marque l'achèvement complet et la mise en production de **l'Écosystème Collaboratif v2.0** d'OmnyRestore. Le projet bascule officiellement d'une application mono-utilisateur vers une plateforme d'équipe hautement sécurisée, conforme aux directives européennes et durcie face aux menaces cyber.

### 💼 Gestion de l'Équipe, Licences & Suspension (Phase 1.5)
- **Gestion de l'Équipe & Rôles** (`/admin/team/roles`) :
  - Création d'une interface d'administration premium et exclusive au Super-Admin pour piloter les comptes collaborateurs.
  - **Widget de Licence (Quota 10 Sièges)** : Barre de progression dorée et indicateur en temps réel pour suivre le quota strict des 10 sièges collaborateurs actifs (hors clients), avec blocage automatique des invitations en cas de dépassement.
  - **Matrice de Permissions Auditable (RBAC)** : Tableau visuel haut de gamme récapitulant les droits applicatifs de chaque rôle (Super-Admin, Opérateur, Marketing) pour une conformité et traçabilité maximales.
  - **Tableau de Bord de l'Équipe** : Liste détaillée des collaborateurs avec recherche en temps réel, filtres par rôle, affichage de la dernière connexion et statut actif/suspendu.
- **Suspension Active des Accès** :
  - Ajout de la colonne `suspended_at` et du helper `isSuspended()` sur le modèle `User`.
  - Durcissement des middlewares `EnsureIsStaff` et `EnsureIsAdmin` pour intercepter et rejeter instantanément tout utilisateur suspendu avec une erreur 403.
  - Actions interactives de suspension et réactivation d'un clic depuis la liste d'équipe.
- **Suppression RGPD & Anonymisation (Art. 17)** :
  - Intégration d'un flux de suppression définitive avec anonymisation non réversible : remplacement du nom par un tag unique (`Ex-Collaborateur [UUID]`) et purge de l'email, blocage définitif de l'authentification et application d'un soft-delete pour conserver la cohérence historique de l'audit trail.

### ⚖️ Architecture RBAC & Transparence Salariale (Phase 1)
- **Sécurisation RBAC (Role-Based Access Control)** :
  - Restructuration majeure des accès avec la création du middleware `EnsureIsStaff` (Opérateurs, Marketing, Super-Admin) et le durcissement de `EnsureIsAdmin` (réservé à la Direction).
  - Cloisonnement strict des données financières, statistiques et légales pour protéger la confidentialité de l'entreprise tout en ouvrant l'opérationnel.
- **Assignation des Commandes (Anti-Collision)** :
  - Nouveau système de verrouillage : l'action de "Prendre en charge" assigne automatiquement la commande à l'opérateur connecté (`operator_id`).
  - Sécurité active : les actions de livraison (upload photos, notification) sont désormais inaccessibles aux autres collaborateurs pour éviter les doublons de traitement.
- **Dashboard de Transparence Salariale** (`/admin/transparency`) :
  - Création d'un module conforme à la directive de l'UE sur la transparence des rémunérations.
  - Affichage en temps réel du Chiffre d'Affaires généré et du volume de commandes traitées par chaque collaborateur sur le mois en cours.
  - Interface "Glass-Card" premium accessible à tout le staff, favorisant l'honnêteté et la motivation de l'équipe.

### 🛠️ Maintenance, Ergonomie & Master Plan
- **Navigation Admin** : Déplacement du lien "Transparence Salariale" dans le menu déroulant du profil pour le Super-Admin, afin d'épurer la barre de navigation principale (le lien reste visible directement pour les opérateurs).
- **Documentation Stratégique** : Réorganisation logique du Master Plan (`collaborative_ecosystem_v2.md`), en déplaçant la phase de stabilisation IA (Phase 0) à la fin du processus (Phase 7).

---

## [0.26.0] — 2026-05-16 — Optimisation Mémoire & Expérience Premium Post-Paiement

### ✨ Nouveautés & Améliorations

- **Emails Transactionnels Premium** :
  - **Confirmation de Paiement** (`paid.blade.php`) : Refonte totale du design pour correspondre à l'identité visuelle Dark/Gold de la marque. Ajout d'un badge "PAIEMENT VALIDÉ", d'une boîte de transaction stylisée façon facture, et d'un bloc d'information clair sur la préparation des fichiers.
  - **Échec de Paiement** (`payment-failed.blade.php`) : Redesign complet avec un ton visuel adouci (dégradé rouge subtil). Remplacement de l'alerte agressive par un encadré pédagogique détaillant les causes fréquentes d'échec (3D Secure, plafond, etc.) et un bouton de relance.

- **Expérience Utilisateur (Téléchargement)** :
  - Implémentation d'un système de **Polling dynamique (10s)** sur la page de statut client.
  - Si le statut passe à `DELIVERED` mais que le ZIP est encore en cours de création en arrière-plan, l'interface affiche un indicateur de chargement continu au lieu d'une page vide, éliminant toute friction post-paiement.

### 🛠️ Maintenance & Robustesse

- **Correction de Fuite Mémoire (Queue Worker)** :
  - **Optimisation critique** de `GenerateOrderZipJob.php` : Remplacement de l'utilisation de `file_get_contents()` qui saturait la mémoire vive (RAM) en chargeant intégralement les images HD.
  - Implémentation de `$zip->addFile()` permettant un streaming direct depuis le disque via la bibliothèque native `libzip`, éliminant définitivement les crashs du worker dus aux erreurs *Memory Exhaustion*.

---

## [0.25.0] — 2026-05-16 — Optimisation Support & Résilience Workers

### ✨ Nouveautés & Améliorations

- **Dashboard Admin (Ergonomie Alpine.js)** :
  - Remplacement des longues listes empilées par un système d'onglets dynamiques ultra-fluides (File d'attente, En cours, Derniers paiements).
  - Intégration de badges dynamiques pour suivre les volumes en temps réel.
  - Centralisation intelligente des raccourcis "Liste clients" et "Chiffre d'affaires" dans l'onglet des paiements.
- **Automatisation & Conformité du Support** :
  - **Auto-Réponse (Remboursement)** : Pré-remplissage intelligent du modèle de réponse admin incluant le prénom du client et la commande si le sujet contient "remboursement".
  - **Blocage CGV Automatique** : Blocage total de l'interface de génération de bons de réduction / avoirs si le ticket est un remboursement ET que le client a déjà téléchargé ses fichiers.
  - **Auto-Close** : Création d'une commande système (`tickets:close-inactive`) tournant toutes les heures pour fermer les tickets "En attente client" sans réponse depuis 24h. Mention dissuasive ajoutée aux messages sortants.

### 🛠️ Maintenance & Robustesse

- **Système de secours JIT (Just-In-Time)** :
  - Mise en place d'un filet de sécurité synchrone (`GenerateWatermarkJob::generateForMedia`) dans le contrôleur d'accès aux photos. Si le worker asynchrone (Queue) tombe en panne et que le filigrane manque, il est généré instantanément au chargement de la page client, évitant l'erreur 403.
  - Ajout d'un bloc d'assistance d'urgence (renvoi de lien) dans la sidebar client.

---

## [0.24.0] — 2026-05-16 — Stratégie d'Acquisition & Croissance SASU

### ✨ Nouveautés & Améliorations
 
 - **Stratégie d'Acquisition Mobile** : Planification de deux applications (Swift & React Native) pour la numérisation simplifiée avec retrait automatique des métadonnées EXIF.
 - **Simulateur de Croissance SASU** : Nouvel onglet permettant de projeter le passage en SASU (Charges sociales dirigeant ~82%, IS 15%, Frais comptables).
 - **Option Collaborateur Salarié** : Ajout d'un toggle Freelance vs CDI dans le simulateur financier pour anticiper l'explosion des quotas AE et sécuriser le lien contractuel.
 - **Monitoring Plafond Micro (Real-Time)** : Jauge dynamique basée sur le CA Réel (Janvier à M-1) + Projection simulée, avec alerte critique à 80% du seuil des 77 700 €.
 - **Interface "Preuve par le Calcul"** : Encart dédié offrant une transparence totale sur l'atterrissage annuel estimé au 31/12.
 - **Hardening Délivrabilité** : Intégration de la stratégie DMARC dans le plan pour protéger la réputation d'envoi des mass mailers.

 ## [0.23.0] — 2026-05-15 — Finalisation Ergonomique & Durcissement des Assets Admin
 
 ### ✨ Nouveautés & Améliorations
 
 - **Durcissement des Assets Critiques** :
   - **Logo CNIL** : Transition vers une intégration en **SVG Inline** dans les templates Blade, garantissant un rendu 100% fiable sans dépendances externes.
   - **Logo ANSSI** : Mise à jour vers la version circulaire officielle, désormais hébergée localement (`/images/anssi.png`) pour éviter les blocages de hotlinking.
   - **Logo OVHcloud** : Hébergement local du favicon (`/images/ovh.ico`) pour une résilience totale du tableau de bord.
 - **Accessibilité & Lisibilité** :
   - **Lexique de Modération** : Refonte de la hiérarchie typographique dans la barre latérale (titres en `text-base`, descriptions en `text-sm`) et agrandissement des textes de mentions légales.
   - **Suivi des Commandes** : Optimisation des contrastes pour les adresses e-mail clients et renforcement visuel des badges de statut (passage en `font-bold` avec palettes de couleurs ravivées).
 - **Standardisation UI/UX** :
   - **Boutons d'Action** : Homogénéisation des largeurs (passage à `sm:w-60`) et augmentation de la taille de police (`text-sm`) pour une meilleure affordance dans les modules de modération et de commandes.
   - **Cellule de Crise** : Amélioration visuelle des conteneurs de recommandation de communication (bordures colorées accentuées Emerald/Red) pour une lecture rapide en situation d'urgence.
 
 ---

## [0.22.0] — 2026-05-14 — Gouvernance Administrative & Stratégique SI

### ✨ Nouveautés & Améliorations

- **Refonte du Panel Conformité** (`/admin/compliance`) :
  - Transition vers une interface tabulée ultra-premium pilotée par Alpine.js.
  - Séparation en trois piliers stratégiques : **Conformité Légale** (RGPD, Loi Godfrain), **Sécurité & Normes** (NIS2, ISO 27001) et **Stratégie SI** (SDSI, PSSI).
  - Enrichissement textuel massif (densité pédagogique x2) détaillant les obligations réglementaires et les mesures techniques.
  - Design "Glass-Card" avec thématiques de couleurs spécifiques (Gold, Blue, Emerald) pour une navigation intuitive.
- **Cadre de Gouvernance SI** :
  - **Schéma Directeur SI (SDSI)** : Formalisation des pré-requis (BIA, EBIOS-RM) et de la planification stratégique sur 24-36 mois.
  - **Politique de Sécurité (PSSI)** : Intégration des règles opérationnelles dérivées de l'ISO 27001 (RBAC, Moindre privilège).
  - **Cinématique de Gouvernance** : Intégration d'un diagramme de séquence interactif via **Mermaid.js** illustrant le flux décisionnel entre la Direction Métier et les opérations de sécurité.
- **Standards ISO 27001** :
  - Implémentation pédagogique du triptyque **DIC** (Disponibilité, Intégrité, Confidentialité) et du cycle **PDCA** (Plan-Do-Check-Act).
  - Clarification des conditions d'usage normatif et des contrôles de l'Annexe A.
- **UX & Visibilité** :
  - Optimisation des tailles de police pour les écrans haute résolution (passage de `text-xs` à `text-sm/xl` pour les titres et labels).
  - Homogénéisation et augmentation de la taille des logos officiels (passage à `w-16`) sur l'ensemble de la plateforme (Navbar & Footers).
  - Amélioration des contrastes et de la hiérarchie visuelle globale.

---

## [0.21.0] — 2026-05-14 — Pilotage Financier & Simulateur Objectifs

### ✨ Nouveautés & Améliorations

- **Dashboard Pilotage Financier** (`/admin/revenue`) :
  - Centralisation analytique avec graphiques d'évolution journalière, hebdomadaire et mensuelle (Chart.js via Livewire Volt).
  - Indicateurs de performance (KPI) : CA HT, CA TTC, volume de commandes, coûts d'IA estimés, cotisations URSSAF (BNC).
  - Résultat net estimé après déductions.
- **Simulateur d'Objectifs Dynamique** (`/admin/revenue/simulation`) :
  - Outil temps réel pour calculer le Chiffre d'Affaires cible (TTC) requis pour garantir un salaire net au dirigeant et à un collaborateur.
  - Intégration automatique des coûts incompressibles : URSSAF (21.2%), coûts d'IA (moyenne 30 jours), frais Stripe (1.5% + 0.25€) et Frais fixes (BFR/Serveurs).
  - Module "Détail par le calcul" prouvant mathématiquement le reste à vivre après toutes déductions.
  - Persistance dans le Cache de la configuration de simulation pour synchronisation avec le tableau de bord principal.
  - Affichage visuel puissant de la "Progression vers l'objectif" sur l'accueil financier.
- **Rapports & Exports PDF** :
  - Ajout d'une annexe dédiée à la simulation (Page 2) lors de l'export du rapport financier PDF (via DomPDF).
  - Mise en forme élégante des paramètres et du détail de calcul dans le document confidentiel généré.
- **Typographie Globale** :
  - Augmentation de la taille de police de base de `16px` à `17px` sur l'ensemble de l'application (ajout au tag html) pour améliorer le confort de lecture.

---

## [0.20.3] — 2026-05-14 — CSAM & NSFW AI Detection

### 🛡️ Sécurité & Modération
- **Détection Automatisée de Contenu (IA)** :
  - Intégration de l'API OpenAI `omni-moderation-latest` pour analyser automatiquement toutes les photos téléchargées par les clients (Jobs asynchrones).
  - Blocage instantané de la commande et suspension au statut `FLAGGED` si un contenu sensible ou illégal est détecté (NSFW / CSAM).
  - Enregistrement de l'adresse IP (`client_ip`) lors de la création de la commande pour répondre aux exigences légales des autorités de signalement.
- **Interface d'Administration de Crise** :
  - Ajout d'une protection visuelle : floutage lourd des images identifiées comme sensibles avec obligation pour l'admin d'accepter de les révéler à ses propres risques.
  - Nouvelles actions d'urgence :
    - Faux positif : Remise en attente.
    - Ban & Destruction : Suppression physique immédiate des fichiers et désactivation du compte utilisateur en un clic.
    - Signalement PHAROS : Génération et téléchargement d'un fichier rapport (IP, Date, Email) formaté pour transmission aux autorités (cas de pédocriminalité CSAM).
- **Notifications Administratives** :
  - Création du `Mailable` `AdminOrderFlagged` envoyant un mail d'urgence au format "Alerte Rouge" lorsqu'un tel contenu est détecté.

---

## [0.20.2] — 2026-05-14 — Dev Environment & SMTP Hardening

### 🛠️ Environnement & Configuration

- **Reset de l'Environnement de Développement** :
  - Nettoyage intégral de la base de données (`migrate:fresh --seed`).
  - Suppression de toutes les archives ZIP orphelines et purge de la bibliothèque de médias (Spatie Media Library).
  - Validation et re-configuration du SMTP (Mailtrap) pour l'envoi d'emails réels (vérification d'inscription, factures).
- **Mise à jour Documentaire** :
  - Ajout d'un encart explicatif détaillé sur la pondération des scores de l'audit intégral.

---

## [0.20.1] — 2026-05-14 — GDPR Compliance & Financial Integrity

### 🔒 Sécurité & Conformité Légale

- **Intégrité Comptable des Factures** (Art. L.123-22 C. com.) :
  - Création d'une migration pour stocker les informations de facturation (`billing_name`, `billing_email`) directement sur la commande (`Order`) au moment du paiement.
  - Résout une faille majeure où la suppression d'un compte client modifiait rétroactivement le nom sur les factures PDF déjà émises en "Utilisateur supprimé".
- **Anonymisation RGPD** (Art. 17) :
  - L'action de suppression de compte (`DeleteUserAction`) supprime désormais physiquement l'archive ZIP contenant les photos restaurées du client sur le serveur.
- **Purge Automatisée** (Art. 5.1.e) :
  - La commande planifiée de purge à 6 mois (`PurgeExpiredMediaCommand`) vérifie maintenant le `zip_path` au niveau global de la commande, assurant une couverture de suppression totale.

### 🐛 Corrections de Bugs

- **Affichage des Prix (Email)** :
  - Correction d'un bug dans l'email `ready-for-payment` où le montant facturé et le nombre de photos affichés étaient calculés sur la base des envois initiaux plutôt que sur les photos réellement traitées/retenues par l'admin.

---

## [0.20.0] — 2026-05-14 — Admin Compliance & UX Polishing

### ✨ Nouveautés & Améliorations

- **Dashboard Admin & Navigation** :
  - Création de la page **Conformité & Légal** (`/admin/compliance`) regroupant les rappels RGPD, NIS2, Loi Godfrain et ISO 27001 avec un code couleur spécifique (Or, Bleu, Rouge, Violet).
  - Nettoyage de la barre de navigation : déplacement du bouton d'urgence "🚨 CRISE" dans le menu déroulant de profil admin.
  - Ajout d'une affordance visuelle (chevron rotatif AlpineJS) sur l'avatar utilisateur (commun Admin & Client) pour indiquer la présence du menu déroulant.
- **Support & UX Client** :
  - Amélioration de l'interface de réduction (sidebar droite) dans la vue de ticket admin : les boutons de génération/sélection de coupon passent en colonne sur les petits écrans pour éviter les chevauchements (`flex-col lg:flex-row`).
  - Refonte sémantique des statuts de tickets admin : "Ouvert" devient "À prendre en charge" et "En attente" devient "Attente client", évitant toute confusion opérationnelle.
  - Blocage du passage automatique des tickets en statut "Attente client" lors de la simple ouverture par l'admin ; ce statut n'est désormais appliqué que lorsqu'une réponse est effectivement envoyée au client.
  - **Transparence RGPD** : Le texte d'export de données (portabilité JSON) a été clarifié pour informer explicitement les clients qu'il s'agit de texte brut et non de médias téléchargeables (qui se trouvent dans les commandes).

---

## [0.19.3] — 2026-05-13 — Documentation Refactoring & Security Audit

### 📚 Documentation & Conformité

- **Refonte Documentaire Complète** :
  - **Déploiement OVH** : Le guide a été refondu avec une approche pédagogique (explications sur Nginx, Supervisor, Redis, sécurisation UFW) et un diagramme d'architecture réseau complet.
  - **Cycle de Vie** : Ajout d'un diagramme de séquence dédié à la phase de livraison, illustrant la facturation (DomPDF), l'expiration des ZIP, et le téléchargement S3.
  - **Architecture** : Intégration du statut final `DELIVERED` et des règles d'expiration des données à 90 jours.
  - Archivage du document historique `phase-9.md` vers `docs/archives/`.
- **Audits** :
  - Création du rapport `audit-integral-v0.19.0.md` validant une architecture de livraison saine et une séparation UX côté client. Le score global passe à **82/100**.
  - Validation du middleware `SecurityHeaders` dans `audit-securite.md` (visant le grade A).

---

## [0.19.2] — 2026-05-13 — Delivery UX & Professional Filenaming

### ✨ Nouveautés & Améliorations

- **Livrables Professionnels** :
  - Modification du nommage des fichiers dans l'archive ZIP livrée au client. Les photos restaurées reprennent désormais le nom du fichier d'origine avec le suffixe `-HD` (ex: `photo_vacances-HD.jpg`), évitant les noms techniques.
- **Espace Client (Téléchargement)** :
  - Refonte UI de la section "LIVRÉ" pour plus de clarté.
  - Mise en évidence du bouton de téléchargement ZIP et affichage explicite de la date d'expiration de l'archive (90 jours).
  - Séparation visuelle de la section "Facture" dans un encadré distinct.
- **Admin & Processus de Livraison** :
  - Mise à jour du libellé du bouton de livraison final : "Envoyer le mail comprenant la facture et le lien pour télécharger le fichier".
  - Ajout de la méthode `markAsDelivered()` sur le modèle `Order` pour tracer formellement la transition d'état et initialiser la date `zip_expires_at` à 90 jours lors du premier envoi.

## [0.19.1] — 2026-05-13 — Pricing Display Fixes & Label Consistency

### 🛠️ Maintenance & Correctifs

- **Admin : Correction de l'Incohérence Tarifaire** :
  - Rectification d'une erreur d'affichage dans les détails de commande : le niveau "Complète" est désormais correctement affiché à **3,00 € TTC** (au lieu de 5 €).
  - Harmonisation des libellés de niveaux de dommage (Standard, Avancée, Complète) sur l'ensemble de l'interface admin (Dashboard, Index des commandes, Panel détails).
  - **Réactivité accrue** : Augmentation de la fréquence de rafraîchissement automatique (**polling 5s** au lieu de 10s) sur le Dashboard et la vue Commande pour un suivi du paiement Stripe en "quasi temps réel".
  - **UX Notification** : Modification du libellé du bouton de rappel pour plus de clarté : "Renvoyer la notification si elle a été perdue".
- **Transparence Financière** :
  - **Décomposition Mixte** : Implémentation d'un affichage dynamique dans la vue admin pour les commandes à tarifs multiples (ex: "Mixte (2 Compl., 5 Std)").
  - Ajout d'une méthode `getDamageBreakdown()` dans le modèle `Order` pour faciliter l'analyse granulaire des commandes.
- **Emails & Docs** :
  - Mise à jour des templates d'emails de paiement pour supporter les 3 niveaux de prix.
  - Mise à jour de la documentation technique (`architecture.mdx` et `phase-9.md`) avec les tarifs définitifs.

---

## [0.19.0] — 2026-05-13 — UI Password Toggle, AI Pricing Sensitivity & PDF Branding

### ✨ Nouvelles fonctionnalités

- **UI : Basculement de Visibilité Mot de Passe** :
  - Ajout d'une icône "œil" (show/hide) sur tous les champs sensibles : Connexion, Inscription, Confirmation de mot de passe, Réinitialisation et Mise à jour du profil.
  - Traduction intégrale en français des libellés dans l'espace profil pour une cohérence parfaite.
- **IA & Tarification (PhotoDamageAnalyzer)** :
  - Optimisation du prompt GPT-4o : critères plus stricts pour le niveau "Heavy" (3,00 € TTC), incluant la reconstruction de visages et les zones manquantes.
  - Règle de "Tie-breaker" : instruction à l'IA de privilégier systématiquement le tarif supérieur en cas d'hésitation entre deux niveaux de dommages.

### 🎨 Design & Reporting

- **Branding PDF** :
  - Intégration du logo OmnyRestore (Base64) dans le **Rapport Financier Administrateur** et le **Rapport d'Incident (PRI)**.
- **Dashboard Admin** :
  - Synchronisation des compteurs (KPIs) et des listes actives avec la politique de filtrage des utilisateurs supprimés.
  - Les commandes des comptes anonymisés sont désormais masquées par défaut dans les flux de travail pour éviter toute confusion opérationnelle.

### 🛠️ Maintenance & Robustesse

- **Sécurisation `omnyConfirm`** : Généralisation de la capture de référence `const wire = $wire` dans tous les modales AlpineJS pour garantir la persistance des appels Livewire lors de navigations asynchrones (`wire:navigate`).

---

## [0.18.0] — 2026-05-13 — RGPD Hardening & Admin Robustness

### ✨ Nouvelles fonctionnalités

- **Libre-service RGPD (Droit à l'effacement)** :
  - Ajout d'un accès rapide "Supprimer mes données (RGPD)" dans le footer de l'espace client.
  - Flux sécurisé : vérification mot de passe, case à cocher obligatoire et **popups OmnyStyle**.
  - Alertes contextuelles : popup "Action requise" et popup finale "Suppression Définitive".
- **Anonymisation Radicale (Zéro PII)** :
  - **Identité** : Nom → "Utilisateur supprimé", Email → hash court `@data.deleted`.
  - **Purge de Contenu** : Suppression irréversible des **photos**, des **instructions de commande** et des **témoignages/avis**.
  - **Support** : Suppression automatique des tickets et des messages.
  - **Infrastructure** : Anonymisation des logs d'audit (IP `0.0.0.0`, User Agent et payloads purgés).
  - **Business** : Conservation anonymisée des totaux de commandes (10 ans).

### 🛠️ Maintenance & Robustesse

- **Robustesse Admin** : Fix `Attempt to read property "name" on null` + utilisation de `withTrashed()` + blocage emails anonymisés.
- **Qualité & Tests** : Validation de la purge complète via `ProfileTest` (64/64 PASS).

---

## [0.17.0] — 2026-05-13 — Identité Visuelle & Cellule de Crise (PRI)

### ✨ Nouvelles fonctionnalités

- **Identité Visuelle Officielle** :
  - Intégration du nouveau logo graphique "OmnyRestore" (Variantes Or/Blanc/Noir).
  - Logo déployé sur la Landing Page, le Header Admin, les pages d'Authentification et les Factures PDF.
- **Cellule de Crise (PRI)** :
  - Nouveau poste de commandement sécurisé pour la gestion d'incidents majeurs.
  - Déclencheur de crise avec confirmation et chronomètre légal de 72h (CNIL).
  - Annuaire d'urgence (ANSSI, CNIL, OVHcloud) avec logos officiels.
  - Centre de communication avec modèles de mails (RGPD, Maintenance, Rapport Technique).
  - Preuves de sécurité (RBAC, Chiffrement AES-256) pour audit.
  - **Export PDF Officiel** : Génération d'un rapport de crise structuré pour les autorités.

### 🎨 Design & UI

- **Facturation Premium** : Logo agrandi (180px) sur les factures PDF, encodé en Base64 pour une fiabilité totale du rendu DomPDF.
- **Navigation Admin** : Nouveau bouton "🚨 CRISE" bordeaux harmonisé avec le thème.

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
[Unreleased]: https://github.com/zyrass/OmnyRestore/compare/v0.20.0...HEAD
[0.20.0]: https://github.com/zyrass/OmnyRestore/compare/v0.19.3...v0.20.0
[0.19.3]: https://github.com/zyrass/OmnyRestore/compare/v0.19.2...v0.19.3
[0.19.2]: https://github.com/zyrass/OmnyRestore/compare/v0.19.1...v0.19.2
[0.19.1]: https://github.com/zyrass/OmnyRestore/compare/v0.19.0...v0.19.1
[0.19.0]: https://github.com/zyrass/OmnyRestore/compare/v0.18.0...v0.19.0
[0.18.0]: https://github.com/zyrass/OmnyRestore/compare/v0.17.0...v0.18.0
[0.17.0]: https://github.com/zyrass/OmnyRestore/compare/v0.16.0...v0.17.0
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


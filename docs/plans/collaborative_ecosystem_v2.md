# [STRATÉGIE] Écosystème Collaboratif OmnyRestore v2.0

Ce document définit l'architecture technique et fonctionnelle pour le passage d'OmnyRestore en mode multi-opérateurs (collaborateurs).

---

## 🏗️ Architecture des Pouvoirs (RBAC)

La sécurité repose sur une séparation stricte des rôles pour garantir l'intégrité des données financières et de la configuration système.

### Diagramme d'État : Cycle de Vie d'un Compte Staff
```mermaid
stateDiagram-v2
    [*] --> Invitation : Envoi du lien (Admin)
    Invitation --> Actif : Inscription validée
    Actif --> Suspendu : Action Admin (Quota atteint/Départ)
    Suspendu --> Actif : Réactivation
    Actif --> Anonymisé : Suppression RGPD (Art. 17)
    Anonymisé --> [*]
```

---

## 📈 Workflow de Traitement Collaboratif

L'objectif est d'éviter les conflits entre opérateurs tout en maximisant la vitesse de traitement.

### Diagramme de Séquence : Prise en charge d'une Commande
```mermaid
sequenceDiagram
    participant C as Client
    participant O as Opérateur
    participant S as Système (Audit Log)
    participant A as Admin

    C->>S: Crée une commande (PENDING)
    O->>S: Sélectionne "Me l'assigner"
    S-->>O: Marque operator_id = O.id
    S-->>A: Notifie l'Admin de la prise en charge
    O->>S: Upload les retouches HD
    O->>S: Valide le passage en DONE
    S->>C: Envoie l'email de paiement
    S->>S: Incrémente KPI (Completed) pour Opérateur O
```

---

## 📢 Module Marketing & Fidélisation

Ce module permet de transformer les données de la base en leviers de croissance.

### Flowchart : Processus de Campagne Promo (Mass Mail)
```mermaid
graph TD
    A[Début Campagne] --> B{Filtre Client}
    B -->|High Spend| C[Segment Premium]
    B -->|Inactif 30j| D[Segment Relance]
    C --> E[Appliquer Coupon Spécifique]
    D --> E
    E --> F[Vérifier Consentement RGPD]
    F -->|Oui| G[Envoi via Assistant IA]
    F -->|Non| H[Exclure du listing]
    G --> I[Suivi Conversion CA]
```

---

## 🤖 Assistant de Communication "OmnyScribe"

Un outil transverse pour garantir l'image de marque.

### Fonctionnalités détaillées :
1. **Correction Orthotypographique** : Suppression des fautes de frappe et de grammaire.
2. **Harmonisation du Ton** :
   - *Tone 1 (Conciliant)* : Idéal pour les réclamations clients.
   - *Tone 2 (Expert)* : Pour les explications techniques sur la restauration.
3. **Sécurisation des données** : Masquage automatique des URLs ou identifiants sensibles avant l'envoi.

---

## 📄 Reporting & Performance (PDF)

Génération automatique de documents confidentiels pour le pilotage :
*   **KPI Hebdo** : Rapport automatique envoyé à l'Admin résumant l'activité de la flotte.
*   **Certificat de Performance** : Pour les collaborateurs (valorisation du travail accompli).

---

## 🚀 Plan de Déploiement (Roadmap)

1. **Step 1** : Migration de la table `users` et implémentation du middleware RBAC.
2. **Step 2** : Création du Dashboard de monitoring des opérateurs (Vue Admin).
3. **Step 3** : Activation du module Marketing et déplacement des coupons/avis.
4. **Step 4** : Intégration de l'Assistant IA dans les tickets de support.
5. **Step 5** : Finalisation du moteur de génération de rapports PDF.

# Scripts de Maintenance OmnyRestore

Ce dossier contient des scripts utilitaires permettant de diagnostiquer ou corriger manuellement des commandes dans la base de données. Ces scripts s'exécutent en ligne de commande via l'interpréteur PHP.

## Fichiers disponibles

### 1. `inspect_media.php`
**But** : Diagnostiquer les problèmes liés au calcul du prix ou aux niveaux d'IA (light, medium, heavy) appliqués aux photos.

**Comment ça marche** :
Le script affiche pour la dernière commande :
- Les photos originales (nom, ID, et niveau IA détecté).
- Les photos retouchées (nom, ID, et niveau IA propagé).
Cela permet de vérifier si l'application a correctement associé le bon tarif à la bonne photo lors de l'upload admin.

**Utilisation** :
Ouvrez un terminal à la racine du projet et tapez :
```bash
php docs/scripts/inspect_media.php
```

**Modification** :
Par défaut, le script cible la dernière commande :
```php
$order = App\Models\Order::latest('id')->first();
```
Pour cibler une commande spécifique, éditez le fichier et remplacez cette ligne par :
```php
$order = App\Models\Order::find('uuid-de-la-commande');
```

---

### 2. `fix_order.php`
**But** : Forcer manuellement le prix final TTC d'une commande en base de données. C'est une commande d'urgence si la logique de calcul rencontre un bug bloquant pour la facturation d'un client.

**Comment ça marche** :
Le script met à jour la colonne `total_price_cents` de la commande. Grâce à la sécurité mise en place dans `Order.php`, ce champ agit comme une **vérité absolue**. Si ce champ est défini à `1800` (18,00€), le système l'utilisera partout sans jamais tenter de le recalculer ou de le contredire.

**Utilisation** :
Ouvrez un terminal à la racine du projet et tapez :
```bash
php docs/scripts/fix_order.php
```

**Modification** :
Pour définir le prix souhaité, ouvrez le fichier et modifiez la variable `$nouveauPrixCents`. 
*Attention : la valeur doit être en centimes (ex: 1800 pour 18.00€).*
```php
$nouveauPrixCents = 1800; // Mettre le prix désiré ici
```
Pour cibler une commande spécifique, modifiez la requête comme pour le script d'inspection :
```php
$order = App\Models\Order::find('uuid-de-la-commande');
```

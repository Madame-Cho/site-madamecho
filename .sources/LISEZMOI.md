# Sources du site Madame Cho

Ce dossier contient de quoi refaire tout le site. Son nom commence par un point : le site ne le sert jamais (règle du `.htaccess` à la racine).

Les pages, `.htaccess`, `robots.txt`, `sitemap.xml`, `assets/catalogue.js` et `ma-demande/catalogue.php` sont **générés** par `gen.py`. Une modification faite à la main dans une page est effacée à la génération suivante. On modifie donc toujours les sources de ce dossier, puis on régénère.

## Où modifier quoi

- `donnees.py` : la carte (gâteaux, tailles, prix aux particuliers TTC, parfums, allergènes, délais, conservation), les textes de l'accueil, la maison, les restaurants, les horaires et les jours fermés.
- `gen.py` : la structure des pages, les mentions légales, la confidentialité, les conditions de commande, `.htaccess`, le plan du site. La constante `VERSION` est à changer quand `site.css` ou `site.js` changent (les navigateurs gardent ces fichiers un an).
- `site.css` et `site.js` : l'apparence et le panier.
- `envoyer.php`, `dkim.php`, `menage.php`, `cle-dkim.php` : le formulaire « Ma demande » (email signé DKIM vers contact@madamecho.com). La clé privée DKIM n'est jamais dans le dépôt : elle reste sur le serveur OVH, hors du dossier du site.
- `gestion/` : l'espace de gestion de Madame Cho (clients, commandes, produits), copié tel quel dans `gestion/` à la génération. Voir plus bas.
- `mesure.js` : la mesure d'audience Matomo (sans cookie, exemption CNIL, refus possible sur la page Confidentialité). Elle n'est active que si `MATOMO` est rempli dans `gen.py` ; vide, le site ne mesure rien et ses textes le disent.
- `favicon/`, `commun/` : icônes, police Jost (licence OFL) et logo.
- `photos.json` : la taille de chaque photo de `assets/photos`. Une nouvelle photo s'ajoute en WebP (`nom-grand.webp`, 1 200 px, et `nom-600.webp`), avec sa ligne dans `photos.json`. Le script qui a préparé les photos actuelles est rangé dans le Drive (15/04), avec une copie de ces sources.

## Espace de gestion (`/gestion/`)

Réservé à Youngmi (et aux personnes qu'elle invite), jamais indexé. PHP 8 et SQLite chez OVH, une seule porte d'entrée : `gestion/index.php?p=…`.

- `gestion/inc/base.php` : réglages, base, comptes, sessions ; `modele.php` : registres (clients, commandes, produits, ingrédients, recettes), statistiques, exports, conservation ; `vues.php` et `pages-*.php` : les écrans ; `enregistrer.php` : enregistrement d'une demande du site (appelé par `envoyer.php` après l'email) et indispos de l'agenda pour le formulaire (date et moment refusés, sans le motif) ; `conservation.php` : tâche de nuit (appelée par `menage.php`).
- Les données ne sont jamais dans le dépôt : la base `gestion.sqlite` vit sur le serveur dans `../.madamecho-donnees/` (à côté du dossier du site), sinon dans `gestion/.donnees/` (fermé au web). Les sauvegardes d'OVH la couvrent ; « Télécharger une sauvegarde » (Réglages) en donne une copie à ranger dans le Drive.
- Première connexion : « Première connexion ou mot de passe oublié ? » avec contact@madamecho.com crée le compte de Youngmi (administratrice) et envoie le lien pour choisir le mot de passe. Les autres comptes s'ouvrent par invitation (Réglages).
- Mise en ligne en deux temps : l'espace d'abord ; l'enregistrement automatique des demandes du site ne démarre que quand on l'active dans Réglages. Tant que la base n'existe pas, le formulaire et la tâche de nuit ne font rien de plus qu'avant.
- Essai en local, avec des données fictives : `MC_ESSAI=1 php -S 127.0.0.1:8765 -t .` à la racine, puis http://127.0.0.1:8765/gestion/ (les emails s'affichent à l'écran au lieu de partir ; la base d'essai se crée à côté du dépôt).

## Régénérer

À la racine du dépôt, avec Python 3 et Pillow :

```
python3 -I -B .sources/gen.py . --en-ligne
```

Puis vérifier les pages modifiées dans un navigateur, sur ordinateur et sur téléphone.

## Publier

Jamais sans le feu vert explicite d'Olivier ou de Youngmi sur la version exacte (R-19 et P-15 du Rulebook Madame Cho). Ensuite : pousser sur `main` et `production`, vérifier le site en ligne, puis noter la publication dans le journal des publications (Drive, 15/04) et au Registre. Si le site n'a pas changé au bout de 5 minutes, faire « Déployer Git » dans l'espace client OVH (procédure pas à pas dans P-15).

Jamais dans ce dépôt public : mot de passe, clé, tarif professionnel, nom de client, photo de restaurant (R-08, R-35).

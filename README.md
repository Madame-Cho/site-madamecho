# site-madamecho

Code source du site web de Madame Cho (madamecho.com), pâtisserie de Youngmi Cho à Meudon.

## Organisation

- La racine du dépôt est la racine du site : OVH clone le dépôt dans le dossier du site. `.htaccess` empêche de servir ce README, le dossier `.git` et tout fichier ou dossier caché.
- Hébergement : OVH, offre Perso, compte client « MADAME CHO ».
- Tous les textes visibles du site sont en français (R-22 du Rulebook Madame Cho).

## Contenu

- Pages : accueil (`index.html`), la carte (`carte/` et une fiche par gâteau), la demande de commande (`ma-demande/`), conditions de commande, confidentialité, mentions légales, page introuvable (`404.html`).
- `assets/` : feuille de style, scripts, logo, icônes, police Jost (licence OFL) et photos.
- `ma-demande/` : le formulaire envoie la demande par email à contact@madamecho.com (PHP d'OVH, email signé DKIM avec le sélecteur « site »). La clé privée DKIM et le sel des limites d'envoi restent sur le serveur, hors du dépôt. `menage.php` efface les limites d'envoi périmées (tâche planifiée OVH).
- Les pages, `.htaccess`, `robots.txt` et `sitemap.xml` sont générés par `.sources/gen.py`. Le dossier caché `.sources/` contient toutes les sources du site et n'est jamais servi ; son mode d'emploi est `.sources/LISEZMOI.md`. Une correction se fait dans les sources, puis le site est régénéré (`python3 -I -B .sources/gen.py . --en-ligne`) ; ne pas modifier les pages à la main.
- Les anciennes pages de travail (`atelier-logo/`, `atelier-site/`) ont été retirées à la mise en ligne du 3 novembre 2026 ; elles restent dans l'historique Git.

## Branches et publication

- `main` : branche de travail. Rien de ce qui y est poussé n'est mis en ligne.
- `production` : ce qui est en ligne. OVH déploie cette branche (association Git ; en cas de doute, « Déployer Git » dans l'espace client OVH).
- Publier = pousser la version validée sur `production`. Seuls le Claude d'Olivier et le ChatGPT de Youngmi publient, et uniquement après le feu vert explicite d'Olivier ou de Youngmi sur la version exacte ; chaque publication est notée dans le journal des publications (Drive, 15/04). Voir R-19 et P-15 du Rulebook Madame Cho.
- Aucun mot de passe, clé ou jeton dans ce dépôt (R-08), ni tarif professionnel, nom de client ou photo de restaurant (R-35).

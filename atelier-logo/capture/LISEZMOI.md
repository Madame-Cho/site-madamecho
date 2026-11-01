# Captures des pages d'accueil (PC d'Olivier)

But : une image de la page d'accueil de chaque site du corpus, cookies acceptés, pour le volet de droite de la page « Atelier logo ». À faire **après** la passe sur le corpus (quand la liste des fiches est stable).

## Installation (une fois, 10 min)
1. Installer Node.js (version LTS) : https://nodejs.org — « Windows Installer » ou « macOS Installer », tout par défaut.
2. Extraire le zip sur le disque local, par exemple dans `C:\capture-sites`, **jamais dans Google Drive, OneDrive ou Dropbox** : la synchronisation abîme les fichiers installés par npm (erreur `ERR_INVALID_PACKAGE_CONFIG`).
3. Ouvrir un terminal dans ce dossier `capture` (Windows : clic droit > « Ouvrir dans le Terminal » ; Mac : Terminal, puis `cd` suivi du dossier glissé).
4. Taper : `npm install` puis `npx playwright install chromium` (télécharge un navigateur de test, ~150 Mo).

## Lancer
- `node capture.js` : capture toutes les fiches actives de `../corpus.json` (3 sites à la fois, environ 15 à 25 minutes pour 230 sites). Les captures déjà faites sont gardées : on peut relancer après une coupure.
- `node capture.js L008 L132` : refait seulement ces fiches.
- Résultat : dossier `../captures/` avec un fichier `Lxxx.jpg` par fiche (1280 × 960) et `_rapport.json` (statut, bandeau de cookies trouvé ou non).

## Sites qui affichent « je ne suis pas un robot »
Certains sites (protégés par Cloudflare ou un captcha) montrent une page de vérification à tout navigateur automatique : c'est ce qu'on voit dans les captures mShots de la page. Le script les reconnaît et les marque « vérification » dans `_rapport.json`, sans faire d'image.
- Quand le premier passage est terminé, lancer `node capture.js --manuel` : une fenêtre de navigateur **visible** s'ouvre, un site à la fois, seulement pour ces fiches. Coche la case « je ne suis pas un robot » dans la fenêtre ; le script voit en général seul que le vrai site est affiché, accepte les cookies et fait la capture. S'il continue d'écrire « coche la case… » alors que le vrai site est affiché, appuie sur **Entrée** dans le terminal : la capture se fait tout de suite (2 min au plus par site, sinon il passe au suivant).
- Si le site reste bloqué même à la main, il garde le statut « vérification » : la page affichera la capture mShots, et Youngmi pourra ouvrir le site avec « voir le site ».
- `node capture.js --manuel L008 L132` : mode manuel pour des fiches précises.

## Ensuite
Vérifier quelques images (bandeau fermé ? page chargée ?), puis envoyer à Claude le dossier `captures` zippé dans le fil, ou le déposer dans `atelier-logo/captures/` sur la branche de travail. La page affiche d'abord `captures/<id>.jpg` ; si l'image manque, elle prend la capture mShots.

Note : les captures seront dans le dépôt public du site, comme la page elle-même ; elles sont retirées avec la page à la fin de la phase 1.

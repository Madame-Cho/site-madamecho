# Captures des pages d'accueil (PC d'Olivier)

But : une image de la page d'accueil de chaque site du corpus, cookies acceptés, pour le volet de droite de la page « Atelier logo ». À faire **après** la passe sur le corpus (quand la liste des fiches est stable).

## Installation (une fois, 10 min)
1. Installer Node.js (version LTS) : https://nodejs.org — « Windows Installer » ou « macOS Installer », tout par défaut.
2. Ouvrir un terminal dans ce dossier `capture` (Windows : clic droit > « Ouvrir dans le Terminal » ; Mac : Terminal, puis `cd` suivi du dossier glissé).
3. Taper : `npm install` puis `npx playwright install chromium` (télécharge un navigateur de test, ~150 Mo).

## Lancer
- `node capture.js` : capture toutes les fiches actives de `../corpus.json` (3 sites à la fois, environ 15 à 25 minutes pour 230 sites). Les captures déjà faites sont gardées : on peut relancer après une coupure.
- `node capture.js L008 L132` : refait seulement ces fiches.
- Résultat : dossier `../captures/` avec un fichier `Lxxx.jpg` par fiche (1280 × 960) et `_rapport.json` (statut, bandeau de cookies trouvé ou non).

## Ensuite
Vérifier quelques images (bandeau fermé ? page chargée ?), puis envoyer à Claude le dossier `captures` zippé dans le fil, ou le déposer dans `atelier-logo/captures/` sur la branche de travail. La page affiche d'abord `captures/<id>.jpg` ; si l'image manque, elle prend la capture mShots.

Note : les captures seront dans le dépôt public du site, comme la page elle-même ; elles sont retirées avec la page à la fin de la phase 1.

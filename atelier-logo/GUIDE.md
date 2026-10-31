# Atelier logo – guide de mise en route (phase 1, Tinder des logos)

Sous-page privée de madamecho.com (`/atelier-logo/`), non référencée (`noindex`), non reliée au site.
Rien ici n'est un secret : la liste des votes n'est pas dans ce dépôt, elle part vers la feuille Google « Tinder des logos » (Drive 91/01).

## 1. Activer la collecte des votes (5 min, Olivier)
1. Ouvrir la feuille « Tinder des logos » > Extensions > Apps Script.
2. Remplacer le contenu par `apps-script.gs`, enregistrer.
3. Déployer > Nouveau déploiement > type « Application web » ; exécuter en tant que « moi » ; accès « Tout le monde ». Autoriser.
4. Copier l'URL `https://script.google.com/macros/s/…/exec` et la coller dans `index.html`, ligne `const VOTES_URL = "";`.
   Tant que l'URL est vide, les votes restent dans le navigateur et se téléchargent en CSV à la fin.

## 2. Tester (Olivier d'abord)
- En local : ouvrir le dossier avec `python3 -m http.server` et aller sur http://localhost:8000/atelier-logo/ (un simple double-clic sur le fichier ne charge pas `corpus.json`).
- Ou sur la branche GitHub via un aperçu (Netlify, Pages de la branche) si le site le permet.
- Vérifier : prénom, défilement, 👎👍❤️, « passer », annuler, pause à 50, arrêt et reprise, lignes qui arrivent dans l'onglet « Votes », colonne « Image chargée » (les logos qui ne se chargent pas affichent le nom).

## 3. Mise en ligne (uniquement sur feu vert d'Olivier, R-19)
Fusionner la branche dans production : la page sera à https://madamecho.com/atelier-logo/. Retirer le dossier à la fin de la phase 1.

## 4. Lecture des résultats
Onglet « Votes » : une ligne par geste (haut, bas, coeur, passer, annule). Croiser avec l'onglet « Corpus » par l'Id.
Claude produit ensuite le « Profil des goûts de Youngmi » (91/01) avec une section sur les biais (images manquantes, marques reconnues, ordre).

## Images
Les logos sont chargés depuis des services tiers (Clearbit, logo.dev, DuckDuckGo, Google favicons) côté navigateur, jamais copiés dans le dépôt. Si aucun ne répond, le nom de la maison s'affiche et le vote est marqué « image : non ».

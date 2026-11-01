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
- Cas prévus par la page : logo absent chez logo.dev → fiche passée automatiquement (vote « sans-image ») ; réseau lent (12 s) → fiche passée (« lent ») ; logo blanc sur fond transparent → fond sombre automatique, et bouton « fond sombre / clair » ; logo flou ou qui n'est pas un logo → bouton « logo illisible » ; double clic → un seul vote ; « annuler le dernier » (ou touche Retour arrière) ; lien « voir le site ↗ » dans un nouvel onglet ; réponses gardées par prénom sur l'appareil, reprise automatique ; option « reprendre à une fiche précise » pour changer d'appareil ; « effacer mes réponses sur cet appareil » ; envoi des votes par paquets de 3, et de ce qui reste quand on quitte la page.
- Limites : un même prénom sur deux appareils sans l'option « reprendre à la fiche n° » revote depuis le début (doublons à écarter à l'analyse par Prénom + Session + Position) ; la qualité des logos dépend de logo.dev (sources parfois petites).
- En local : double-clic sur `index.html` suffit (le corpus est intégré à la page ; `corpus.json` et `corpus.csv` restent la source pour la feuille).
- Ou sur la branche GitHub via un aperçu (Netlify, Pages de la branche) si le site le permet.
- Vérifier : prénom, défilement, 👎👍❤️, « passer », annuler, pause à 50, arrêt et reprise, lignes qui arrivent dans l'onglet « Votes », colonne « Image chargée » (les logos qui ne se chargent pas affichent le nom).

## 3. Mise en ligne (uniquement sur feu vert d'Olivier, R-19)
Fusionner la branche dans production : la page sera à https://madamecho.com/atelier-logo/. Retirer le dossier à la fin de la phase 1.

## 4. Lecture des résultats
Onglet « Votes » : une ligne par geste (haut, bas, coeur, passer, illisible, sans-image, annule). Une ligne « annule » annule la ligne de même Session + Position + Horodatage navigateur. La colonne « Image chargée » porte aussi « (site consulté) » si le lien du site a été ouvert. Croiser avec l'onglet « Corpus » par l'Id.
Claude produit ensuite le « Profil des goûts de Youngmi » (91/01) avec une section sur les biais (images manquantes, marques reconnues, ordre).

## Images
Les logos sont chargés côté navigateur depuis logo.dev (compte gratuit Madame Cho, clé publique pk_… dans LOGO_KEY de index.html, option fallback=404 pour ne jamais afficher d'icône passe-partout), jamais copiés dans le dépôt. Sans logo, le nom de la maison s'affiche et le vote est marqué « image : non ».

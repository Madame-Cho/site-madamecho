# Atelier logo – guide de mise en route (phase 1, Tinder des logos ; phase 2, questionnaire ; phase 5, le choix)

Sous-page privée de madamecho.com (`/atelier-logo/`), non référencée (`noindex`), non reliée au site.
Rien ici n'est un secret : la liste des votes n'est pas dans ce dépôt, elle part vers la feuille Google « Tinder des logos » (Drive 91/01).

## 1. Activer la collecte des votes (5 min, Olivier)
1. Ouvrir la feuille « Tinder des logos » > Extensions > Apps Script.
2. Remplacer le contenu par `apps-script.gs`, enregistrer.
3. Déployer > Nouveau déploiement > type « Application web » ; exécuter en tant que « moi » ; accès « Tout le monde ». Autoriser.
4. Copier l'URL `https://script.google.com/macros/s/…/exec` et la coller dans `index.html`, ligne `const VOTES_URL = "";`.
   Tant que l'URL est vide, les votes restent dans le navigateur et se téléchargent en CSV à la fin.

## 2. Tester (Olivier d'abord)
- Cas prévus par la page : logo introuvable (Brandfetch puis logo.dev) → fiche passée automatiquement (vote « sans-image ») ; réseau lent (12 s) → fiche passée (« lent ») ; logo blanc sur fond transparent → fond sombre automatique, et bouton « fond sombre / clair » ; image floue ou qui n'est pas le logo → bouton « pas un logo / illisible » ; double clic → un seul vote ; « annuler le dernier » (ou touche Retour arrière) ; nom de la maison sous le logo, et mention « symbole seul » quand seul le symbole est disponible ; lien « voir le site ↗ » dans un nouvel onglet ; réponses gardées par prénom sur l'appareil, reprise automatique ; option « reprendre à une fiche précise » pour changer d'appareil ; « effacer mes réponses sur cet appareil » ; envoi des votes par paquets de 3, et de ce qui reste quand on quitte la page.
- Passe d'Olivier sur le corpus : avec un prénom qui commence par « Olivier » (« Olivier 3 » pour repartir de zéro), les cinq votes sont remplacés par deux gros boutons « ✓ garder » (vote « garder », touche G) et « ✕ hors corpus » (vote « retirer », touche X) ; Claude lit l'onglet Votes (prénom Olivier, vote retirer) et propose des fiches de remplacement. « ⟲ revenir au début » repasse toutes les fiches depuis la première : sur une fiche déjà vue, « passer » garde le vote, un autre choix le remplace (ligne « annule » + ligne « (modifié) »).
- Ordre des fiches : stable par (prénom, identifiant) ; ajouter ou retirer des fiches ne change pas l'ordre relatif des autres.
- Écran « mes votes » (bouton sous les boutons de vote, et depuis la pause ou la fin) : grille des logos déjà jugés avec le vote, un clic rouvre la fiche pour changer (l'ancien vote est annulé dans la feuille par une ligne « annule », le nouveau porte « (modifié) ») ; liste déroulante « n° · marque » de toutes les fiches pour aller directement à l'une d'elles (déjà votée → modification ; pas encore → on y saute, et les fiches sautées reviennent à la fin). Touche Échap pour revenir à la liste.
- Cinq réponses depuis le 01/11 (page v3.5), parce que Youngmi juge le logo ET le site : `bas` = ni le logo ni le site, `logo` = le logo oui mais pas le site, `site` = le site oui mais pas le logo, `haut` = les deux, `coeur` = coup de cœur ; chaque bouton porte deux pastilles « logo » et « site » cochées ou barrées. Touches 1 à 5, L, S, flèches. Les logos et les sites sont décrits dans l'onglet Caractéristiques (voir plus bas).
- Volet du site : à droite si la largeur atteint 1000 px ; sur téléphone et tablette en hauteur (v3.8), fiche compacte, volet sous la fiche, les cinq boutons avec « annuler » et « passer » fixés en bas de l'écran, et retour en haut à chaque nouvelle fiche. Le volet montre une capture de la page d'accueil : d'abord `captures/<id>.jpg` (faites par Olivier sur son PC avec `capture/capture.js`, cookies acceptés : 224 fiches), sinon mShots (L064). L193 Hermès et L387 Flour Bakery bloquent les captures : message « voir le site » (listes `NOSHOT` et `NOLOCAL` dans la page, à tenir à jour si une capture change). mShots, service de WordPress.com (gratuit, sans compte, `https://s0.wordpress.com/mshots/v1/<adresse>?w=1200`) : la première demande renvoie une image « Generating Preview » le temps de la capture (quelques secondes) ; la page réessaie toutes les 3,5 s avec w+1, w+2… (même capture, simple redimensionnement) et garde l'image dont la largeur vaut exactement w. La page charge d'avance le logo des deux fiches suivantes et la capture des trois suivantes. Une fiche passée faute de logo (réseau lent) est reproposée une fois en fin de liste. Clavier : sans effet dans le champ du prénom ou avec Ctrl, Alt ou Cmd ; la case « je connais » ne bloque pas les touches.
- Limites : un même prénom sur deux appareils sans l'option « reprendre à la fiche n° » revote depuis le début (doublons à écarter à l'analyse par Prénom + Session + Position) ; la qualité des logos dépend des sources (Brandfetch fournit souvent le SVG, net ; logo.dev agrandit parfois de petites images). Brandfetch exige un en-tête Referer : si les images ne s'affichent pas depuis un fichier ouvert en local, tester depuis un petit serveur local ou sur le site.
- En local : double-clic sur `index.html` suffit (le corpus est intégré à la page ; `corpus.json` et `corpus.csv` restent la source pour la feuille).
- Ou sur la branche GitHub via un aperçu (Netlify, Pages de la branche) si le site le permet.
- Vérifier : prénom, défilement, les cinq boutons logo/site, « passer », annuler, pause toutes les 50 fiches jugées, arrêt et reprise, « mes votes » et liste déroulante, volet du site sur grand écran, lignes qui arrivent dans l'onglet « Votes », colonne « Image » (oui · logo complet / oui · symbole / non / lent, puis « (site consulté) », « (modifié) »).

## 3. Mise en ligne (uniquement sur feu vert d'Olivier, R-19)
Fusionner la branche dans main (avance rapide possible au 01/11), puis main dans production, que OVH déploie : la page sera à https://madamecho.com/atelier-logo/. Seuls la page et les captures y sont servies (`atelier-logo/.htaccess` refuse .md, .gs, .csv, .json, .js et ajoute l'en-tête X-Robots-Tag noindex). À la fusion, repointer l'IMPORTDATA de l'onglet Caractéristiques sur main, ou figer l'onglet. Puis vérifier sur le site, sur ordinateur et sur téléphone, que la page s'affiche et qu'un vote arrive dans l'onglet Votes. Retirer le dossier à la fin du chantier ou quand Olivier le décide (l'historique Git garde les fichiers : rien de secret n'y est) ; avant, figer l'onglet Caractéristiques (coller les valeurs).

## 4. Lecture des résultats
Onglet « Votes » : une ligne par geste (haut, bas, coeur, logo, site, passer, illisible, garder, retirer, sans-image, annule). Une ligne « annule » annule la ligne de même Session + Position + Horodatage navigateur ; un vote modifié depuis « mes votes » arrive en ligne « annule » + nouvelle ligne « (modifié) » à la même position. La colonne « Image » dit ce qui a été montré (« oui · logo complet » = logo avec le nom ; « oui · symbole » = symbole seul, sans le nom ; « non » / « lent » = rien) et porte « (site consulté) » si le lien du site a été ouvert. Croiser avec l'onglet « Corpus » par l'Id.
Claude produit ensuite le « Profil des goûts de Youngmi » (91/01) avec une section sur les biais (images manquantes, marques reconnues, ordre).

## 5. Questionnaire (phase 2)
Page `atelier-logo/questionnaire/` (https://madamecho.com/atelier-logo/questionnaire/ une fois publiée) : 23 questions courtes en français et en coréen, tirées de la Spécification (rubriques 2 à 7) et du Profil des goûts de Youngmi ; aucune n'est obligatoire. Les réponses sont gardées sur l'appareil (localStorage `atelier-questionnaire-v1:<prénom>`, prénom prérempli depuis le Tinder) et envoyées au fil de l'eau par le même script que les votes, dans l'onglet Votes : Id = numéro de question (Q01, Q02a… ; Q99 = « terminé »), Vote = réponse (libellés français des choix séparés par « ; », « Autre : … » pour un texte libre, échelles du type « 2 · plutôt Classique »), Image chargée = « questionnaire ». Chaque modification renvoie la question : la dernière ligne par Prénom et Id fait foi. Ouverte depuis un fichier sur disque, ou avec `?test` dans l'adresse, la page n'envoie rien (bandeau « version de test »). Un lien « télécharger mes réponses » sert de secours. Claude tire des réponses le « Portrait de marque » (91/01), avec les réponses originales et leur traduction, que Youngmi vérifie.

## 6. Le choix de Youngmi (phase 5)
Page `atelier-logo/finale/` (https://madamecho.com/atelier-logo/finale/ une fois publiée) : les six pistes gardées après les deux tris d'Olivier (lot 2 : 01a, 04a, 04c, 07a, 08b, 10a), dessins SVG en ligne dans la page. Exception à la règle « jamais d'esquisse dans le dépôt » (R-08), limitée à ces six finalistes, accordée par Olivier le 02/11/2026 (voir le Registre) ; dossier à retirer à la fin du chantier. Trois étapes : chaque logo seul (non / oui / coup de cœur), chaque logo en situation (boîte blanche, étiquette kraft, avatar Instagram, en-tête du site), puis duels deux à deux entre les logos gardés en situation (sinon gardés seuls, sinon les six), classement par victoires, choix final et remarque. Votes dans l'onglet Votes par le même script : Id = « seul 01a », « situation 01a », « duel 01a/04a », « choix », « remarque », « fin » ; Vote = non / oui / coeur, ou le logo préféré ; Temps de réponse et Position remplis ; Image chargée = « finale » ; la dernière ligne par Prénom et Id fait foi. Reprise sur l'appareil (localStorage `atelier-finale-v1:<prénom>`, prénom prérempli depuis le questionnaire ou le Tinder), bouton « revenir », clavier 1/2/3, flèches et retour arrière. `?test` ou fichier sur disque : rien n'est envoyé ; `?planche` : les six pistes seules et en situation sur une seule page (pour Olivier). Rien n'est choisi tant que Youngmi n'a pas touché « J'ai fini » ; Claude lit ensuite les lignes « choix », « duel », « situation » et « remarque » et inscrit le résultat dans la Spécification.


## 7. Le coréen (variantes du logo choisi)
Page `atelier-logo/coreen/` (https://madamecho.com/atelier-logo/coreen/) : 18 variantes du logo choisi 01a avec une inscription coréenne (K01 à K18 : 마담 조 dessous, filet, maison 조, hanok, sceaux 조, 마담 조 en grand, MADAME 조, 과자점, colonne), plus 01a sans coréen pour comparer. Exception à R-08 accordée par Olivier le 02/11/2026 (carte « Oui, publier la page »), limitée à ces dessins ; dossier à retirer à la fin du chantier. Étape 1 : les 19 logos seuls ; étape 2 : en situation, seulement ceux aimés à l'étape 1 ; étape 3 : duels entre les six préférés au plus ; puis choix et remarque. Votes dans l'onglet Votes par le même script, Image chargée = « coreen », mêmes Id qu'en section 6 (« seul K01 », « situation K01 », « duel K01/K08 », « choix », « remarque », « fin »). Reprise sur l'appareil (localStorage `atelier-coreen-v1:<prénom>`). `?test` : rien n'est envoyé ; `?planche` : tout sur une page.
Deuxième partie `atelier-logo/coreen/rond/` (lien « Continuer : le rond » à la fin de la première) : 12 ronds avec écriture coréenne (R01 à R07 avatars Instagram, R08 à R12 tampons) plus l'avatar B3 et le tampon 3 sans coréen pour comparer ; mêmes étapes ; scènes Instagram, sachet kraft, boîte, autocollant ; Image chargée = « coreen-rond », clé locale `atelier-coreen-rond-v1:<prénom>`. Ajoutée à la demande d'Olivier le 02/11/2026 (« Publie et prépare le brouillon »).

## Images
Les logos sont chargés côté navigateur, jamais copiés dans le dépôt, dans cet ordre : Brandfetch logo complet (SVG puis PNG, avec le nom), Brandfetch symbole, logo.dev (symbole carré). Brandfetch : Logo API gratuite (1 000 000 requêtes par mois, sans attribution), compte Madame Cho, client ID public dans BF_ID de index.html, chemin `fallback/404`. logo.dev : compte Community gratuit Madame Cho, clé publique pk_… dans LOGO_KEY, `fallback=404`, mention « Logos provided by Logo.dev » sur la page. Jamais d'icône passe-partout : sans logo, la fiche est passée automatiquement (vote « sans-image »). Les deux comptes sont à fermer à la fin de la phase 1 (P-18). Les captures de pages d'accueil viennent de mShots (WordPress.com), sans compte.

## Onglet « Caractéristiques » (lu par des IA)

Une ligne par fiche (ligne = numéro de la fiche + 1, comme l'onglet Corpus), une colonne par caractéristique, vocabulaire fixe quand c'est possible (les listes sont séparées par « , »). Rempli par Claude à partir des captures des sites et des logos téléchargés sur le PC d'Olivier ; sert au profil des goûts de Youngmi (croisement avec l'onglet Votes par l'Id) et aux consignes de génération de la phase 4. Copie dans `caracteristiques.csv` (avec en-tête) ; l'onglet l'importe par une formule IMPORTDATA (cellule A1) : avant de retirer la page ou de supprimer la branche, figer l'onglet (copier, puis coller les valeurs seulement). Rédigé le 01/11/2026 à partir des 229 captures et des 230 logos du PC d'Olivier (L064 Caffè Florian sans capture : site écrit de mémoire, `C_verifie` = non). Fiches signalées dans `S_particularite` / `L_particularite` : captures bloquées L193 Hermès et L387 Flour Bakery (retirées de `captures/`, à refaire à la main), L038 Café Pouchkine (capture hors sujet), L254 Karamel (site douteux) et L255 (doublon de L034) retirés du corpus le 01/11 à la demande d'Olivier (227 fiches actives) ; logos d'un groupe et non de la maison L176, L333, L336, image non logo L418.

**Commun**

- `id` · Id : L001…
- `nom` · Maison : libre
- `statut` · Statut : actif | retiré
- `categorie` · Catégorie : de l'onglet Corpus
- `pays` · Pays : de l'onglet Corpus

**Logo**

- `L_type` · Type de logo : logotype | monogramme | symbole+nom | symbole | emblème | sceau | mascotte (« symbole » : seule l'image du symbole a pu être récupérée, voir `L_image_montree`)
- `L_composition` · Composition : horizontal | empilé | symbole au-dessus | symbole à gauche | rond | écusson | carré
- `L_ecriture` · Écriture : aucun | serif-didone | serif-garalde | serif-égyptienne | serif-autre | sans-géométrique | sans-grotesque | sans-humaniste | script | manuscrite | calligraphie | gothique | fantaisie | mixte
- `L_casse` · Casse : capitales | bas-de-casse | mixte | script
- `L_graisse` · Graisse : fine | moyenne | grasse | contrastée
- `L_espacement` · Espacement des lettres : serré | normal | large
- `L_symbole` · Symbole : aucun | animal | végétal | objet | figure humaine | architecture | monogramme | forme géométrique | abstrait | aliment | plusieurs
- `L_symbole_detail` · Détail du symbole : libre (ex. renard assis, couronne à cinq pointes)
- `L_couleur_principale` · Couleur principale : noir | blanc | or | rouge | bleu | vert | rose | brun | orange | jaune | violet | gris | beige | multicolore
- `L_couleurs` · Couleurs : liste
- `L_nb_couleurs` · Nombre de couleurs : 1 | 2 | 3+
- `L_fond` · Fond du logo tel que montré : clair | sombre | coloré
- `L_epoque` · Époque évoquée : XIXe | 1900 Art nouveau | Art déco | années 50 | années 70 | années 90 | contemporain | intemporel
- `L_style` · Styles : liste : classique, luxe, minimal, moderne, artisanal, ludique, rétro, pop, brut, romantique, oriental, japonais, coréen, scandinave, institutionnel
- `L_ornements` · Ornements : liste : aucun, filets, cadre, cartouche, couronne, étoiles, date, lieu, devise, soulignement, points, motif
- `L_mentions` · Mentions écrites en plus du nom : libre (ex. Paris · depuis 1862 · pâtisserie)
- `L_densite` · Densité : minimal | équilibré | chargé
- `L_ton` · Ton : liste : élégant, chaleureux, espiègle, sérieux, naturel, luxueux, populaire, doux, puissant
- `L_lisibilite` · Lisibilité : forte | moyenne | faible
- `L_image_montree` · Image montrée par la page : logo complet | symbole seul
- `L_particularite` · Particularité distinctive : libre
- `L_texte` · Description détaillée du logo : libre, 2 à 4 phrases

**Site**

- `S_mise_en_page` · Mise en page : héros plein écran | grille produits | magazine | une colonne | mosaïque | vidéo plein écran | texte seul | diaporama
- `S_hero` · Premier visuel : photo produit | photo lieu | photo personne | vidéo | illustration | texte ou logo | diaporama | mixte
- `S_fond` · Fond dominant : blanc | crème | beige | sombre | coloré | photo
- `S_couleur_accent` · Couleur d'accent : noir | blanc | or | rouge | bleu | vert | rose | brun | orange | jaune | violet | gris | beige | multicolore | aucune
- `S_couleurs` · Couleurs : liste
- `S_typo` · Typographie : serif | sans | script | mixte | condensée
- `S_titres` · Casse des titres : capitales | bas-de-casse | mixte
- `S_photo_ton` · Ton des photos : lumineux | sombre | pastel | saturé | naturel | studio | aucune photo
- `S_densite` · Densité : aéré | moyen | dense
- `S_logo_place` · Place du logo sur le site : centré | gauche | grand | discret | absent
- `S_boutique` · Boutique en ligne visible : oui | non
- `S_langue` · Langue de la page : FR | EN | KO | JA | DE | IT | ES | PT | autre
- `S_style` · Styles : liste : luxe, classique, minimal, moderne, chaleureux, ludique, rétro, artisanal, brut, éditorial, boutique, pop, institutionnel
- `S_ton` · Ton : liste : élégant, chaleureux, espiègle, sobre, gourmand, institutionnel, naturel, dynamique
- `S_gene` · Gêne visible sur la capture : aucune | bandeau cookies | fenêtre surgissante | bandeau promo | page de blocage | page vide ou partielle
- `S_capture` · Image que Youngmi verra : locale | mShots | aucune
- `S_particularite` · Particularité distinctive : libre
- `S_texte` · Description détaillée du site : libre, 2 à 4 phrases

**Croisé**

- `C_coherence` · Cohérence logo / site : forte | moyenne | faible
- `C_mots_cles` · Mots-clés : liste libre
- `C_verifie` · Vérifié sur image : oui | non (écrit de mémoire)

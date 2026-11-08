# Tasks : écran « Obligations », lot 1 (À faire et Échéances)

**Input** : [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md)
**Tests** : données fictives, scripts hors du dépôt ([quickstart.md](./quickstart.md)). « [X] » = fait sur la
branche, pas publié. Rien n'est publié sans l'audit n° 7 et le feu vert d'Olivier sur la version exacte.

## Phase 1 : Socle

- [X] T001 Tables à part (marqueur `schema_obligations`, R1) : tables `demarches`, `echeances`, `demarches_historique`, index, UNIQUE(echeance_id, periode) et UNIQUE(declencheur) ; étape indépendante de l'étape 11 (R1), dans `base.php`
- [X] T002 [P] `obligations.php` : lecture, couleur, texte d'état et ordre (R3), création, modification, fait (lien Drive vérifié + note ; obligatoire si importante), en attente, sans objet (raison), rouvrir, historique

## Phase 2 : US1, voir ce qui reste à faire (P1)

- [X] T003 [US1] Écran « Obligations » : À faire (rouge, orange, autres), filtre « les miennes » / « toutes », Fait et Sans objet repliés
- [X] T004 [US1] Fiche d'une démarche : quoi, où (outil et endroit), pour quand, pourquoi, preuve, historique ; boutons C'est fait, Sans objet, Modifier, Rouvrir
- [X] T005 [US1] Formulaire d'ajout et de modification (Youngmi et Olivier), aide « jamais de nom de client »
- [X] T006 [US1] Menu Plus › Obligations, lien direct sur grand écran, icône ; routes dans `index.php`
- [X] T007 [US1] Pastilles : rouge pour la personne, ambre pour ce que l'autre a ajouté ou changé (genre `demarche`)

## Phase 3 : US5, échéances (P2)

- [X] T008 [US5] Rubrique Échéances : règles (titre, où, pour qui, fréquence, prochaine date), ajouter, modifier, arrêter
- [X] T009 [US5] Occurrences idempotentes (R8) : calcul des périodes (fin de mois, 29 février, premier lundi), préavis par règle, à la tâche de nuit et à l'ouverture de l'écran ; « date à confirmer »
- [X] T010 [US5] Déclencheur 1re commande confirmée d'un nouveau client pro → « Vérifier la dérogation (Cerfa 13982) avant de livrer », importante, pour la veille de la livraison, une seule fois (R6)

## Phase 4 : Reprise, récap, finitions

- [X] T011 Obligations › Échéances › Reprise d'une liste (CSV : démarches et règles), sans doublon, compte rendu
- [X] T012 [P] L'assistant lit les démarches ouvertes (lecture seule)
- [X] T013 Récap du lundi (R4, choix A d'Olivier) : la tâche de nuit du lundi envoie un mail interne de site@ à contact@ seulement (titres et dates, aucune donnée de client), signé DKIM « site », une fois par semaine au plus ; exception R-01 à inscrire au rulebook
- [X] T019 [P] Conservation : démarches closes effacées 10 ans après leur clôture (tâche de nuit) ; bandeau « Liste de départ à valider » jusqu'à la relecture d'Olivier et Youngmi
- [X] T014 [P] Coréen (`coreen.php`), manuel FR/KO (`manuel.php`), Quoi de neuf
- [X] T015 Régénérer ; essais unitaires (dont migrations dans les deux ordres avec les factures, deux passages de nuit simultanés) et parcours ; captures FR/KO et téléphone
- [X] T016 Commande d'audit n° 7 pour le ChatGPT d'Olivier (P-26), passe 1 sur spec, plan et tâches ; passe 2 sur la version construite
- [X] T020 Corrections de la passe 2 (P2-01 à P2-07, P2-09, P2-10, P2-13) et alerte « nouveau client professionnel » (P2-14), choix d'Olivier « Tout, avec l'alerte client pro » ; essais des cas de l'audit
- [X] T021 Passe 3 de l'audit n° 7 sur 8ae744b, puis corrections P3-01 à P3-10
- [x] T022 ~~Passe 4 de l'audit n° 7 sur la version corrigée~~ : annulée, Olivier a arrêté les audits le 08/11 à 20:43
- [ ] T017 Liste initiale vérifiée dans le Drive et la Mémoire (CM2C, AXA T-01, impots.gouv T-02, Workspace, DDPP, extincteur, électricité, calendrier 20/02, registre RGPD, factures dues…), CSV dans les fichiers du projet, jamais dans le dépôt
- [ ] T018 Feu vert d'Olivier sur la version exacte ; fetch et rebase sur main ; publication ; Journal des publications, Registre (FAIT : 24bda5e publiée le 08/11 à 20:44) ; reste : reprise par Olivier, fichier supprimé


## Lot 2 : Hygiène au quotidien (US2), « Go lot 2 » d'Olivier du 2026-11-08

- [X] T023 [US2] Tables et repère `schema_hygiene` ; appareils préremplis aux seuils réglementaires, « à confirmer » (carte d'Olivier)
- [X] T024 [US2] Froid : relevé par appareil, hors plage → action obligatoire (page et serveur) ; 1 relevé par jour (carte d'Olivier)
- [X] T025 [US2] Réceptions : produit, lot, date limite ou photo d'étiquette (réduite, gardée hors du site, servie après contrôle)
- [X] T026 [US2] Fournées : numéro de lot, date limite reprise de la durée fixée par Youngmi, réceptions utilisées
- [X] T027 [US2] Livraisons : commande ou restaurant, lots livrés, départ et température, arrivée une seule fois
- [X] T028 [US2] Corrections avec motif, versions visibles ; « pas de production » et son annulation
- [X] T029 [US2] Relevé de froid manquant (démarche figée, une par jour), tâche de nuit ; conservation 5 ans avec les photos (carte d'Olivier)
- [X] T030 [US2] Historique 30 jours, export CSV ; rappel sur Aujourd'hui ; Plus › Hygiène ; coréen, manuel, Quoi de neuf
- [X] T031 Essais hors dépôt `tests-gestion/hygiene/` (parcours téléphone FR/KO, unitaires) et non-régression (Obligations, pastilles, rappel)
## Lot 3 : Étiquettes et problème sur un lot (US3, US4), « Enchaîne lot 3 » d'Olivier du 2026-11-08

- [X] T035 [US3] Textes d'étiquette par gâteau (`hygiene_durees` : conservation, ingrédients, relu le / par) ; ingrédients proposés depuis la recette ; adresse de l'exploitant dans les réglages
- [X] T036 [US3] Étiquettes A4 3 x 8 et rouleau 55 x 30 (cartes d'Olivier), impression sans rien d'autre, refus motivé s'il manque quelque chose, texte toujours en français
- [X] T037 [US4] Table `hygiene_problemes` ; ouverture depuis la fiche du lot, périmètre, étapes avec note, clôture avec bilan ; démarche importante `probleme:<id>`
- [X] T038 [US4] Brouillon DDPP copiable, sans nom de particulier (R-01 : Youngmi l'envoie) ; lot marqué « Problème » ; coréen, manuel
- [X] T039 Essais `tests-gestion/hygiene/lot3.js` (26/26) et non-régression

## Lot 4 : Volumes et Documents (US5 scénario 3, US6), « Lot 4 » d'Olivier du 2026-11-08

- [X] T040 [US5] Tables `volumes_poids`, `volumes_restaurants`, `documents` (repère `schema_hygiene` = 2) ; plafonds préremplis « à confirmer »
- [X] T041 [US5] Volumes par semaine (kilos B2B et total, part, plafond plein ou réduit), poids d'une pièce proposé depuis la recette, distances des restaurants actifs
- [X] T042 [US5] Alerte de plafond (proche / dépassé) par la tâche de nuit, textes figés traduits
- [X] T043 [US6] Documents : 9 pièces attendues (carte d'Olivier), lien Drive seulement, validité et alerte 30 jours avant, retirer sans effacer ; liens proposés par Olivier (collés une fois), vérifiés par Youngmi
- [X] T044 Coréen, manuel, essais `hygiene/lot4.js` (21/21) et `lot4-unitaires.php` (30/30), non-régression

## Audit n° 8 (lots 2 à 4), « Tout, catégories après la DDPP » d'Olivier du 2026-11-08

- [X] T045 C1 traçabilité : fournée sans réception ou livraison sans lot seulement avec la case « aucune » et une note (colonne `trace`) ; périmètre avec les livraisons aux lots non notés et les commandes sur mesure ; brouillon DDPP sans « aucun » non vérifié
- [X] T046 C2, C8 : RappelConso obligatoire dès qu'un rappel aux consommateurs est décidé ; chaque étape faite ou « sans objet » avec raison (jamais pour retirer) ; clôture = toutes les étapes + bilan + lien de preuve, la démarche se clôt avec le problème et pas avant
- [X] T047 C3, C14 : ingrédients obligatoires, mention DLC ou DDM, poids net facultatif ; impression bloquée si une étiquette déborde
- [X] T048 C4 (sans les catégories) : « Non vérifié » tant que la DDPP n'a pas confirmé, plafond choisi sur la part en kilos (réduit si inconnue), part en euros indicative
- [X] T049 C5, C6, C9, C13, C15 : contrôle des relevés manquants depuis le dernier passage (120 jours par nuit), « pas de production » ne cache pas un jour avec commandes, purge qui garde les relevés d'un problème et jamais sous 5 ans, numéro de lot unique (index partiel), export CSV sans formule
- [X] T050 C7, C10, C11, C12 : validité d'un lien proposé, démarche « distance à vérifier », commandes sur mesure pesées à la main (`volumes_commandes`), alerte aggravée quand le plafond est dépassé ; repère `schema_hygiene` = 3
- [X] T052 Contre-expertise de l'audit n° 9 (ChatGPT d'Olivier) : un lot sous un problème ouvert ne se coche plus dans une livraison (refus côté serveur, sauf correction d'une livraison qui le portait) et ses étiquettes ne s'impriment plus ; « Sous le seuil saisi » au lieu de « Sous le plafond », rappel du plafond par catégorie (annexe III) ; distance du restaurant vérifiée au départ (« Je livre quand même » + raison) et signalée sur la commande ; RappelConso pense aux clients servis par un restaurant ; plus de réglage caché qui retire les ingrédients
- [ ] T051 C4 suite : catégorie sanitaire par gâteau et plafond par catégorie, après la réponse de la DDPP

- [ ] T032 Saisie hors ligne (cas limite), l'assistant lit les relevés : plus tard
- [ ] T033 Audit unique des lots 1 à 4 (P-26) après le lot 4 (choix d'Olivier) ; registre des traitements (T8) ; pilote avec Youngmi
- [ ] T034 Feu vert d'Olivier sur la version exacte ; fetch et rebase ; publication ; Journal des publications, Registre

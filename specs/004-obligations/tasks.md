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
- [ ] T032 Saisie hors ligne (cas limite), l'assistant lit les relevés : plus tard
- [ ] T033 Audit unique des lots 1 à 4 (P-26) après le lot 4 (choix d'Olivier) ; registre des traitements (T8) ; pilote avec Youngmi
- [ ] T034 Feu vert d'Olivier sur la version exacte ; fetch et rebase ; publication ; Journal des publications, Registre

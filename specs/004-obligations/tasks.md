# Tasks : écran « Obligations », lot 1 (À faire et Échéances)

**Input** : [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md)
**Tests** : données fictives, scripts hors du dépôt ([quickstart.md](./quickstart.md)). « [X] » = fait sur la
branche, pas publié. Rien n'est publié sans l'audit n° 7 et le feu vert d'Olivier sur la version exacte.

## Phase 1 : Socle

- [ ] T001 Schéma 12 : tables `demarches`, `echeances`, `demarches_historique`, index ; étape indépendante de l'étape 11 (R1), dans `base.php`
- [ ] T002 [P] `obligations.php` : lecture, couleur et ordre (R3), création, modification, fait (lien Drive vérifié + note), sans objet (raison), rouvrir, historique

## Phase 2 : US1, voir ce qui reste à faire (P1)

- [ ] T003 [US1] Écran « Obligations » : À faire (rouge, orange, autres), filtre « les miennes » / « toutes », Fait et Sans objet repliés
- [ ] T004 [US1] Fiche d'une démarche : quoi, où (outil et endroit), pour quand, pourquoi, preuve, historique ; boutons C'est fait, Sans objet, Modifier, Rouvrir
- [ ] T005 [US1] Formulaire d'ajout et de modification (Youngmi et Olivier), aide « jamais de nom de client »
- [ ] T006 [US1] Menu Plus › Obligations, lien direct sur grand écran, icône ; routes dans `index.php`
- [ ] T007 [US1] Pastilles : rouge pour la personne, ambre pour ce que l'autre a ajouté ou changé (genre `demarche`)

## Phase 3 : US5, échéances (P2)

- [ ] T008 [US5] Rubrique Échéances : règles (titre, où, pour qui, fréquence, prochaine date), ajouter, modifier, arrêter
- [ ] T009 [US5] Tâche de nuit : démarche créée à 30 jours ; la règle avance d'une période quand la démarche est faite
- [ ] T010 [US5] Déclencheur 2e commande confirmée d'un nouveau restaurant → « Mettre à jour la dérogation (Cerfa 13982) », une seule fois (R6)

## Phase 4 : Reprise, récap, finitions

- [ ] T011 Paramètres › Reprise des démarches (CSV : démarches et règles), sans doublon, compte rendu
- [ ] T012 [P] L'assistant lit les démarches ouvertes (lecture seule)
- [ ] T013 Récap du lundi (R4, choix A d'Olivier) : la tâche de nuit du lundi envoie un mail interne de site@ à contact@ seulement (titres et dates, aucune donnée de client), signé DKIM « site », une fois par semaine au plus ; exception R-01 à inscrire au rulebook
- [ ] T014 [P] Coréen (`coreen.php`), manuel FR/KO (`manuel.php`), Quoi de neuf
- [ ] T015 Régénérer ; essais unitaires et parcours ; captures FR/KO et téléphone
- [ ] T016 Commande d'audit n° 7 pour le ChatGPT d'Olivier (P-26), passe 1 sur spec, plan et tâches ; passe 2 sur la version construite
- [ ] T017 Liste initiale vérifiée dans le Drive et la Mémoire (CM2C, AXA T-01, impots.gouv T-02, Workspace, DDPP, extincteur, électricité, calendrier 20/02, registre RGPD, factures dues…), CSV dans les fichiers du projet, jamais dans le dépôt
- [ ] T018 Feu vert d'Olivier sur la version exacte ; fetch et rebase sur main ; publication ; Journal des publications, Registre ; reprise par Olivier, fichier supprimé

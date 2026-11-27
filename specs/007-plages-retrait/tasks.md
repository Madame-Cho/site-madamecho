---
description: "Tâches de la spécification 007, plages de retrait à capacité"
---

# Tasks : plages de retrait à capacité

**Input** : [spec.md](./spec.md), [plan.md](./plan.md), [research.md](./research.md), [data-model.md](./data-model.md),
[contracts/plages-du-jour.md](./contracts/plages-du-jour.md), [quickstart.md](./quickstart.md)

Toutes les modifications se font dans `.sources/`, puis on régénère (principe VII).

## Phase 1 : fondations (bloque tout le reste)

- [X] T001 `PLAGES`, `PLAGES_COURTES`, `JOURS_SEMAINE_FERMES` et `MOMENTS_PLAGES` élargi dans `.sources/gestion/inc/base.php`
- [X] T002 Marqueur `schema_plages` : table `plages_reglages` dans `.sources/gestion/inc/base.php`
- [X] T003 `plage_de_commande()`, `plages_du_jour()`, `plage_regler()`, `plage_maximum_defaut()`, `jour_ferme()` dans `.sources/gestion/inc/modele.php`
- [X] T004 [P] `PLAGES` et `JOURS_SEMAINE_FERMES` dans `.sources/donnees.py` ; le catalogue du site et celui de la gestion les reprennent (`.sources/gen.py`)

## Phase 2 : US1, le client choisit une plage libre (P1)

- [X] T005 [US1] `plages_pour_demande()` (indispos, réglages, comptes ; sans motif ni nombre) dans `.sources/gestion/inc/enregistrer.php`
- [X] T006 [US1] `envoyer.php` : question `verifier=1&date=…` → plages ; champ `plage` ; refus du dimanche, d'un jour fermé, d'une plage complète
- [X] T007 [US1] Formulaire : « Plage horaire » au lieu de « Moment », aide du retrait (`.sources/gen.py`)
- [X] T008 [US1] `site.js` : plages d'un jour demandées au changement de date, « complet » non choisissable, phrase du dimanche, revérification à l'envoi ; `site.css` si besoin

## Phase 3 : US2, Youngmi voit et règle ses plages (P1)

- [X] T009 [US2] Agenda : bloc « Plages » du jour choisi (« n sur max », fermer, rouvrir, maximum) et actions POST dans `.sources/gestion/inc/pages-agenda.php`
- [X] T010 [US2] Paramètres : « Maximum par plage » (1 à 20, 5 par défaut) dans `.sources/gestion/inc/pages-autres.php`
- [X] T011 [P] [US2] Styles du bloc Plages dans `.sources/gestion/gestion.css`

## Phase 4 : US3 et US4, plage dans les mails, commandes hors plage (P2)

- [X] T012 [US3] `mail_quand()`, `retrait_texte()`, `heure_agenda()`, `creneau()` : la plage (« entre 10 h 30 et 12 h ») dans `.sources/gestion/inc/vues.php` et `pages-agenda.php`
- [X] T013 [US4] Saisie et modification d'une commande : choix de la plage (ancien moment gardé), heure précise facultative, avertissements dimanche et plage complète dans `.sources/gestion/inc/pages-commandes.php`
- [X] T014 [US4] Confirmation d'une demande : plage modifiable, avertissement si complète (`pages-commandes.php`)
- [X] T015 [US3] Assistant : plage lisible, codes de plage acceptés pour `retrait_moment` dans `.sources/gestion/inc/assistant-donnees.php`

## Phase 5 : US5, textes et acompte (P1)

- [X] T016 [US5] Site : « du lundi au samedi », dimanche par téléphone, plage horaire ; horaires des moteurs du lundi au samedi (`.sources/donnees.py`, `.sources/gen.py`)
- [X] T017 [US5] Site : acompte retiré (formulaire, fiches, Questions, Conditions de commande renumérotées)
- [X] T018 [US5] Gestion : acompte retiré des formulaires de proposition, de confirmation et de paiement, sauf commande qui en a déjà un (`pages-commandes.php`)

## Phase 6 : finitions

- [X] T019 Coréen de tous les nouveaux textes (`.sources/gestion/inc/coreen.php`)
- [X] T020 Manuel FR/KO et « Quoi de neuf » (`.sources/gestion/inc/manuel.php`)
- [X] T021 `VERSION` du site changée ; régénération ; Confidentialité : la question au site ne porte plus que sur la date (phrase mise à jour, page datée du 27 novembre 2026)
- [X] T022 Essais `plages.php` et `plages.js` (quickstart), captures dans les fichiers du projet
- [ ] T023 Consigne d'audit pour le ChatGPT d'Olivier (P-26), rapport vide dans 92/02

## Dépendances

Phase 1 avant tout ; US1 et US2 indépendantes ensuite ; US3/US4 après T003 ; US5 indépendante ; Phase 6 en dernier.

## Essais (27/11/2026)

- `plages.php` (ligne de commande, base d'essai) : 43 vérifications réussies.
- `plages.js` (Playwright : site et gestion, ordinateur et téléphone, français et coréen) : 52 vérifications réussies.
- Essai existant des rappels de retrait : 30 vérifications réussies ; relevé coréen de toute la gestion (263 pages) : aucun
  nouveau texte resté en français (restent les noms, gâteaux, ingrédients et textes saisis, voulus).
- Ajouté en cours de route : la date « au plus tôt » du formulaire saute le dimanche et le 24 décembre ; l'assistant lit
  les plages fermées ou complètes (outil agenda) et peut proposer une plage à la confirmation.

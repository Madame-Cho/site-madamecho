# Implementation Plan: Espace de gestion, lot « audit n° 2 » (écritures de l'assistant, prix, plafond)

**Branch**: `002-espace-gestion` (branche de travail `claude/project-thread-ccofn5`) | **Date**: 2026-11-07 | **Spec**: [spec.md](./spec.md)

**Input**: spécification `specs/002-espace-gestion/spec.md`, clarifications « Session 2026-11-07 (soir,
audit n° 2) » ; audit n° 2 du ChatGPT d'Olivier sur la version en ligne 1684d72 ; décision d'Olivier
« Tout retenir, C1-C3 et C8 d'abord » (2026-11-07).

## Summary

L'outil existe et est en ligne. Ce plan couvre le lot de corrections de l'audit n° 2 : une
modification préparée par l'assistant montre tout ce qu'elle écrira et ne change jamais un
paiement en silence (C1, FR-027) ; changer une date ne reprend jamais le prix actuel de la carte
(C2, FR-029) ; une proposition dont la fiche a changé est refusée (C3, FR-027) ; le plafond du mois
est réservé avant chaque appel au modèle (C8, FR-019). Les autres constats retenus (C4 à C7, C9 à
C15) sont planifiés en lots suivants dans `tasks.md`, sans code dans ce lot.

## Technical Context

**Language/Version**: PHP 8 (hébergement mutualisé OVH), JavaScript sans bibliothèque, Python 3 pour la génération du site.

**Primary Dependencies**: aucune bibliothèque extérieure ; API d'Anthropic en curl ; Web Speech API du navigateur.

**Storage**: SQLite (`gestion.sqlite`, hors du dossier servi) ; schéma passé en version 6 (colonne `empreinte` de `assistant_actions`).

**Testing**: scripts d'essai sur données fictives (Playwright et PHP en ligne de commande), hors du dépôt pour l'instant (AM-42) : `assistant/donnees.js` (97 vérifications), `audit2/corrections.php` (15), `audit2/parcours.js` (11).

**Target Platform**: Chrome du Galaxy de Youngmi d'abord, Safari, ordinateur d'Olivier.

**Project Type**: application web rendue par le serveur, dans le dépôt du site.

**Performance Goals**: une page en moins d'une seconde ; l'assistant n'est pas visé par ce lot (C7 à mesurer ensuite).

**Constraints**: aucun nouveau service payant (principe V), français et coréen (VI), sources puis régénération (VII), feu vert d'Olivier avant publication (II).

**Scale/Scope**: quelques commandes par jour, deux ou trois comptes.

## Constitution Check

*GATE : vérifié avant et après la conception.*

| Principe | Vérification | État |
|----------|--------------|------|
| I. Dépôt public | Aucun secret ni donnée réelle dans le code, la spécification ou les essais versionnés ; essais sur base fictive | OK |
| II. Feu vert | Rien publié sans le feu vert d'Olivier sur la version exacte | OK (à demander) |
| III. Fiche produit | Le prix enregistré à la commande est gardé ; la carte ne change que les gâteaux ajoutés | OK |
| IV. Données personnelles | Aucune donnée nouvelle collectée ; l'empreinte est un condensé, pas une copie | OK |
| V. Sobre | Pas de nouvel outil, pas de nouvelle dépense | OK |
| VI. Langue | Nouveaux messages ajoutés au dictionnaire coréen | OK |
| VII. Preuves | Essais listés ci-dessus, tous réussis | OK |
| VIII. Écritures de l'IA (nouveau, 1.1.0) | C'est l'objet du lot | OK |

## Project Structure

### Documentation (this feature)

```text
specs/002-espace-gestion/
├── spec.md              # spécification (mise à jour après l'audit n° 2)
├── plan.md              # ce plan
├── research.md          # décisions du lot
├── data-model.md        # ce qui change dans les données
├── quickstart.md        # comment rejouer les essais
├── checklists/
│   ├── requirements.md
│   └── audit-2.md       # contrôle du lot
└── tasks.md             # tâches du lot et des suivants
```

Pas de dossier `contracts/` : l'outil n'expose aucune interface à d'autres systèmes ; le seul point
d'accès public (`ma-demande/envoyer.php`, vérification d'une date) est traité dans un lot suivant (C11).

### Source Code (repository root)

```text
.sources/gestion/inc/
├── assistant-donnees.php   # propositions : paiement par défaut, carte complète, empreinte, refus
├── assistant.php           # plafond réservé avant chaque appel (assistant_cout_max)
├── base.php                # schéma 6 : colonne empreinte
├── pages-commandes.php     # prix d'origine gardés à la modification d'une commande
└── coreen.php              # nouveaux messages en coréen
gestion/inc/                # copie générée par .sources/gen.py (jamais modifiée à la main)
```

**Structure Decision**: on modifie `.sources/gestion/`, on régénère `gestion/` (principe VII).

## Complexity Tracking

Aucun écart à justifier.

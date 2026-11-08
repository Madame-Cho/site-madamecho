# Data Model : écran « Obligations », lot 1 (schéma 12)

## Table `demarches`

| Colonne | Type | Rôle |
|---------|------|------|
| id | INTEGER PK | |
| titre | TEXT | ce qu'il faut faire, court |
| explication | TEXT | pourquoi |
| ou | TEXT | outil, page et endroit exacts (consigne d'Olivier du 07/11) |
| pour_qui | TEXT | `youngmi`, `olivier`, `deux` |
| echeance | TEXT | `AAAA-MM-JJ` ou '' (sans date) |
| priorite | INTEGER | 1 haute, 2 normale, 3 basse |
| origine | TEXT | chantier ou fil d'origine (texte libre) |
| etat | TEXT | `a_faire`, `attente` (fait, réponse attendue), `fait`, `sans_objet` |
| importante | INTEGER | 1 : preuve obligatoire pour clore |
| preavis | INTEGER | jours avant l'échéance où elle passe orange (30 par défaut) |
| a_confirmer | INTEGER | 1 : date pas encore connue (jamais devinée) |
| periode | TEXT | pour une démarche née d'une règle : la date d'échéance de la période ; UNIQUE(echeance_id, periode) |
| ref_genre, ref_id | TEXT | référence facultative (lots 2-4) |
| preuve_lien | TEXT | lien Drive (https://drive.google.com/… ou docs.google.com/…) |
| preuve_note | TEXT | note libre |
| raison | TEXT | raison d'un « sans objet » |
| echeance_id | INTEGER | règle d'origine, sinon NULL |
| declencheur | TEXT | ex. `restaurant:<client_id>`, sinon '' ; UNIQUE quand non vide |
| cree_le, cree_par | TEXT | |
| modifie_le, modifie_par | TEXT | |
| fait_le, fait_par | TEXT | |

Jamais supprimée par une personne ; effacée par la tâche de nuit 10 ans après sa clôture. Index : `(etat, echeance)`.

## Table `echeances` (règles récurrentes)

| Colonne | Type | Rôle |
|---------|------|------|
| id | INTEGER PK | |
| titre, explication, ou, pour_qui, priorite | | recopiés dans chaque démarche créée |
| frequence | TEXT | `annuelle`, `trimestrielle`, `mensuelle`, `premier_lundi` |
| depart | TEXT | date de la première échéance (ou '' si à confirmer) |
| preavis | INTEGER | jours (30 par défaut) |
| importante | INTEGER | recopié dans chaque démarche |
| active | INTEGER | 1 ou 0 |
| cree_le, cree_par, modifie_le, modifie_par | TEXT | |

Les occurrences se calculent depuis `depart` (R8) ; une démarche par période, créée quand son préavis
commence, que la précédente soit close ou non.

## Table `demarches_historique`

| Colonne | Type | Rôle |
|---------|------|------|
| id | INTEGER PK | numéro croissant (comparé par les pastilles) |
| le, auteur | TEXT | |
| demarche_id, echeance_id | INTEGER | |
| type | TEXT | `creation`, `modification`, `fait`, `sans_objet`, `rouverte`, `regle` |
| champ, ancien, nouveau | TEXT | |

## Pastilles

- Rouge (chiffre) sur Obligations : démarches `a_faire` de la personne (`pour_qui` = elle ou `deux`)
  en retard ou dans leur préavis (les « en attente » comptent aussi).
- Ambre : historique postérieur au « vu » de la personne (`vus`, genre `demarche`), fait par l'autre.

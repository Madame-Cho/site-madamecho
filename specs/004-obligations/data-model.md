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
| etat | TEXT | `a_faire`, `fait`, `sans_objet` |
| preuve_lien | TEXT | lien Drive (https://drive.google.com/… ou docs.google.com/…) |
| preuve_note | TEXT | note libre |
| raison | TEXT | raison d'un « sans objet » |
| echeance_id | INTEGER | règle d'origine, sinon NULL |
| declencheur | TEXT | ex. `restaurant:<client_id>`, sinon '' |
| cree_le, cree_par | TEXT | |
| modifie_le, modifie_par | TEXT | |
| fait_le, fait_par | TEXT | |

Jamais supprimée. Index : `(etat, echeance)`.

## Table `echeances` (règles récurrentes)

| Colonne | Type | Rôle |
|---------|------|------|
| id | INTEGER PK | |
| titre, explication, ou, pour_qui, priorite | | recopiés dans chaque démarche créée |
| frequence | TEXT | `annuelle`, `mensuelle`, `trimestrielle` |
| prochaine | TEXT | date de la prochaine échéance |
| active | INTEGER | 1 ou 0 |
| cree_le, cree_par, modifie_le, modifie_par | TEXT | |

La tâche de nuit crée la démarche quand `prochaine` est à 30 jours ou moins et qu'aucune démarche
ouverte de cette règle n'existe ; quand elle est faite, `prochaine` avance d'une période (depuis la date
d'échéance, pas la date du jour, pour garder le rythme).

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
  en retard ou à 30 jours ou moins.
- Ambre : historique postérieur au « vu » de la personne (`vus`, genre `demarche`), fait par l'autre.

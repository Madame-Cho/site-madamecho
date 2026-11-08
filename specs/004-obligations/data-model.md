# Data Model : écran « Obligations », lot 1 (tables à part, marqueur `schema_obligations`, voir research R1)

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
| auteur_id | INTEGER | compte de l'auteur (NULL pour la gestion elle-même) ; sert au « a changé » (P2-09) |
| demarche_id, echeance_id | INTEGER | |
| type | TEXT | `creation`, `modification`, `fait`, `sans_objet`, `rouverte`, `regle` |
| champ, ancien, nouveau | TEXT | |

## Pastilles

- Rouge (chiffre) sur Obligations : démarches `a_faire` de la personne (`pour_qui` = elle ou `deux`)
  en retard ou dans leur préavis. Les « en attente d'une réponse » ne comptent pas : elles restent
  visibles dans leur rubrique, sans faire monter le chiffre.
- Ambre : historique postérieur au « vu » de la personne (`vus`, genre `demarche`), fait par l'autre.


# Lot 2 : relevés d'hygiène (repère `schema_hygiene` = 1)

## Table `hygiene_appareils`
id, nom, genre (`frigo`, `congelateur`, `vitrine`, `caisson`), tmin et tmax (REAL, NULL = sans borne), a_confirmer
(1 tant que Youngmi n'a pas validé la plage préremplie), actif, cree_le/par, modifie_le/par. Un caisson n'attend pas de relevé du matin.

## Table `hygiene_releves`
Un relevé par ligne, genre `froid`, `reception`, `fournee`, `livraison` ou `sans_production` ; jour (AAAA-MM-JJ), le (heure
réelle), par et par_id. Froid : appareil_id, temperature, tmin/tmax recopiés, hors_plage, action (obligatoire hors plage).
Réception : fournisseur, produit, lot, dlc, photo, temperature facultative. Fournée : produit_id, produit, quantite,
numero_lot (AAMMJJ-GATEAU-n), dlc. Livraison : client_id, commande_id, depart_le, arrivee_le (une seule fois), temp_depart.
Corrections : la nouvelle ligne porte `remplace_id` et `motif` ; l'ancienne `remplace_par` (0 : « pas de production » annulé).
Jamais supprimée par une personne ; effacée par la tâche de nuit après la durée choisie (5 ans), avec sa photo.

## Table `hygiene_liens`
(de, vers) : une fournée vers les réceptions utilisées, une livraison vers les fournées livrées. Une correction reprend les liens.

## Table `hygiene_durees`
produit_id, jours (durée de conservation fixée par Youngmi), maj_le, maj_par.

## Lot 3 : colonnes ajoutées à `hygiene_durees`

`conservation` (texte de l'étiquette), `ingredients` (relu par Youngmi), `etiquette_le`, `etiquette_par` (relu le / par). Paramètre `etiquette_exploitant` (adresse, jamais dans le dépôt).

## Table `hygiene_problemes`

`id`, `fournee_id`, `numero_lot`, `ouvert_le`, `ouvert_par`, `description`, `etapes` (JSON : retirer, clients, ddpp, rappelconso, chacune le / par / note), `demarche_id`, `clos_le`, `clos_par`, `bilan`. Jamais supprimée ; pas de purge automatique pour l'instant (durée à trancher à l'audit unique).

# Data Model : paiement en ligne des commandes

Base SQLite de la gestion (hors du dossier servi). Montants en centimes, dates en heure de Paris
(`Y-m-d H:i:s`), comme le reste de la base.

## Table `liens_paiement` (neuve)

| Colonne | Type | Règle |
| --- | --- | --- |
| id | INTEGER PK | |
| commande_id | INTEGER NOT NULL | → `commandes.id` |
| qonto_id | TEXT NOT NULL UNIQUE | identifiant du lien chez Qonto |
| url | TEXT NOT NULL | page de paiement ; jamais montrée à l'assistant |
| montant | INTEGER NOT NULL | centimes, TTC ; égal au montant renvoyé par Qonto, sinon le lien est désactivé |
| origine | TEXT NOT NULL | `gestion` (temps 1, toucher de Youngmi) ou `site` (temps 2, choix du client) |
| etat | TEXT NOT NULL | `open`, `processing`, `paid`, `expired`, `canceled` (valeurs de Qonto) |
| cree_le, cree_par | TEXT | `cree_par` = nom de l'utilisateur, ou « site » |
| expire_le | TEXT | `expiration_date` de Qonto |
| retenue_jusqua | TEXT NULL | temps 2 : fin de la retenue de place |
| suivi | TEXT NULL UNIQUE | temps 2 : jeton aléatoire de 32 caractères pour la page de suivi du client |
| paiement_qonto_id | TEXT NULL UNIQUE | identifiant du paiement réussi ; garde-fou contre le double comptage |
| paye_le | TEXT NULL | `paid_at` de Qonto |
| moyen_detail | TEXT NOT NULL DEFAULT '' | `credit_card` ou `apple_pay` |
| payeur_email | TEXT NOT NULL DEFAULT '' | `debitor_email` ; effacé avec la commande |
| frais_estimes | INTEGER NULL | centimes, barème des Paramètres |
| recu_envoye_le | TEXT NULL | posé dans la même transaction que l'état payé ; jamais deux reçus |
| maj_le | TEXT NOT NULL | dernière relecture chez Qonto |

Index : `liens_paiement_commande (commande_id, etat)`. Règle : au plus un lien `open` ou
`processing` par commande.

Transitions : `open` → `processing` → `paid` ; `open` → `canceled` (prix changé, commande
annulée ou remise, retenue échue, Youngmi annule) ; `open` → `expired` (Qonto). `paid` est final.

## Table `retenues` (neuve, temps 2)

| Colonne | Type | Règle |
| --- | --- | --- |
| commande_id | INTEGER PK | une retenue par commande |
| jour | TEXT NOT NULL | `Y-m-d` |
| plage | TEXT NOT NULL | code de plage (spécification 007) |
| jusqua | TEXT NOT NULL | échéance |

Comptée comme une place prise par `plages_pour_demande` (le site) tant que `jusqua` n'est pas
passée ; jamais par `plages_du_jour` (la gestion). Effacée au paiement ou à l'échéance.

## Table `commandes` (existante)

- `moyen` : nouvelle valeur `en_ligne` (« En ligne »), ajoutée à `MOYENS`.
- `paiement` : `paye` et `rembourse` existent ; nouvel état d'affichage « À rembourser » calculé
  (commande annulée + lien payé + paiement ≠ `rembourse`), pas une colonne.
- `statut` : au temps 2, une commande payée naît `confirmee` ; une commande du temps 2 non payée reste
  `demande` avec l'événement `paiement_non_abouti`.
- `acompte` : plus jamais rempli (spécification 007) ; les anciennes valeurs restent lisibles.

## Table `evenements` (existante) : nouveaux types

`lien_envoye`, `lien_annule`, `paiement_en_ligne` (montant, moyen), `paiement_non_abouti`,
`recu_envoye`, `rembourse`. Ils alimentent l'historique de la commande et les pastilles
« Nouveau ».

## Table `parametres` (existante) : nouvelles clés

| Clé | Défaut | Sens |
| --- | --- | --- |
| `paiement_temps1` | `0` | bouton « Payer en ligne » dans la confirmation |
| `paiement_temps2` | `0` | « Commander et payer » sur le site |
| `paiement_minimum` | `500` | centimes ; en dessous, pas de paiement en ligne |
| `paiement_retenue_minutes` | `30` | durée de la retenue de place |
| `paiement_bareme` | `140,25` | frais estimés : 1,40 % + 0,25 € |
| `qonto_notification_id`, `qonto_notification_secret` | | abonnement aux notifications ; secret jamais exporté ni montré |

Les jetons OAuth restent dans `qonto_espaces` (existant) ; ils couvrent les nouvelles portées après
la nouvelle liaison par Youngmi.

## Conservation

Le lien et le paiement suivent la commande : effacés avec elle (12 mois sans suite, 3 ans sans
commande pour le client), sauf ce que la comptabilité garde déjà (montant, date, moyen). Le registre
des traitements gagne la ligne « paiement en ligne » (Qonto, Mollie).

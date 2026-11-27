# Data Model : plages de retrait à capacité

## Plage (constante, `PLAGES` dans `base.php` et `donnees.py`)

| Code | Début | Fin | Libellé du site | Libellé court |
| --- | --- | --- | --- | --- |
| `0900` | 09:00 | 10:30 | De 9 h à 10 h 30 | 9 h – 10 h 30 |
| `1030` | 10:30 | 12:00 | De 10 h 30 à 12 h | 10 h 30 – 12 h |
| `1200` | 12:00 | 14:00 | De 12 h à 14 h | 12 h – 14 h |
| `1400` | 14:00 | 16:00 | De 14 h à 16 h | 14 h – 16 h |
| `1600` | 16:00 | 18:00 | De 16 h à 18 h | 16 h – 18 h |
| `1800` | 18:00 | 19:00 | De 18 h à 19 h | 18 h – 19 h |

Jours ouverts : lundi à samedi. Jours fermés : dimanche (`JOURS_SEMAINE_FERMES = [0]`) et `JOURS_FERMES`
(« 12-24 »).

## Réglage d'une plage (table `plages_reglages`, marqueur `schema_plages` = 1)

| Champ | Type | Règle |
| --- | --- | --- |
| `jour` | TEXT AAAA-MM-JJ | clé avec `plage` |
| `plage` | TEXT | un code de `PLAGES` |
| `maximum` | INTEGER NULL | 1 à 20 ; NULL = maximum par défaut |
| `fermee` | INTEGER | 0 ou 1 |
| `maj_le` | TEXT | date et heure |
| `maj_par` | TEXT | nom de la personne |

Une ligne revenue à l'état par défaut (`maximum` NULL et `fermee` 0) est supprimée.

## Paramètre `plage_maximum` (table `parametres`)

Entier de 1 à 20 ; 5 si absent.

## Commande (table `commandes`, inchangée)

- `retrait_moment` : un code de `PLAGES` (nouvelles commandes) ou un ancien moment (`matin`, `midi`,
  `apres-midi`, `soir`), ou vide.
- `retrait_heure` : heure précise facultative (HH:MM), prioritaire dans les mails.
- Compte dans la plage `plage_de_commande(k)` si `mode = 'retrait'`, `statut IN ('confirmee', 'remise')`,
  `efface_le IS NULL` (voir [research.md](./research.md), R2).

## État d'une plage pour un jour (calculé)

`{code, debut, fin, maximum, n, fermee, indispo, complete}` avec `complete = fermee || indispo || n >= maximum`.
Le site n'en reçoit que « libre » ou « complet ».

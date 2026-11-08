# Data Model : factures des particuliers (schéma 11)

## Table `factures` (factures et avoirs)

| Colonne | Type | Rôle |
|---------|------|------|
| id | INTEGER PK | |
| genre | TEXT | `facture` ou `avoir` |
| numero | TEXT UNIQUE | `P-2026-0001`, `AV-2026-0001` |
| prefixe, annee, seq | TEXT, INT, INT | UNIQUE(prefixe, annee, seq) : ni trou ni doublon |
| commande_id, client_id | INT | sans clé étrangère : la facture survit à l'effacement |
| facture_id | INT | pour un avoir : la facture corrigée |
| emise_le, emise_par | TEXT | date et personne |
| total_ttc, total_ht, total_tva | INT | centimes (négatifs pour un avoir) |
| donnees | TEXT | JSON figé : vendeur, client, lignes, TVA, paiement, mentions |
| pdf | BLOB | le PDF figé |
| empreinte | TEXT | SHA-256 du PDF |
| chaine | TEXT | SHA-256 chaîné (R6) |
| jeton | TEXT | lien privé ; '' quand désactivé ou expiré |
| motif | TEXT | avoir : raison de la correction |

## Table `factures_remises`

| Colonne | Type | Rôle |
|---------|------|------|
| id | INTEGER PK | |
| facture_id | INT | |
| le, par | TEXT | |
| canal | TEXT | `email`, `lien` (SMS, WhatsApp), `qr`, `papier` |
| detail | TEXT | adresse mail, « SMS », « WhatsApp »… |
| mid | TEXT | Message-ID du mail |

## Commandes

`commandes.facture` reçoit le numéro à l'émission (comme aujourd'hui pour un numéro Qonto). Une
commande a au plus une facture non annulée ; un avoir total la rend « à refaire ».

## Paramètres

`facture_prefixe` (défaut `P-`), `avoir_prefixe` (défaut `AV-`), `factures_controle` (1 tant que la
phase de contrôle dure).

## États d'une commande de particulier, côté facture

`pas encore payée` → `à émettre` → `émise, pas remise` → `remise` ; `corrigée par un avoir` →
de nouveau `à émettre` si l'avoir est total.

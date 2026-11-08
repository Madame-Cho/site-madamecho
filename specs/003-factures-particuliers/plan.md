# Implementation Plan : Factures des particuliers faites par la gestion

**Branch** : `003-factures-particuliers` (branche de travail `claude/project-thread-0bqc8g`) | **Date** : 2026-11-08 | **Spec** : [spec.md](./spec.md)

**Input** : spécification `specs/003-factures-particuliers/spec.md` ; plan validé par Olivier le 2026-11-08
(« ok », comptabilité hors périmètre, choix « 2 » pour la caisse, phase de contrôle) ; réponses d'Olivier
pour Youngmi (A : elle choisit lien, QR code ou impression ; B : plus de factures de particuliers dans Qonto).

## Summary

La gestion prépare la facture d'une commande payée d'un particulier, Youngmi la relit et l'émet d'un
toucher : numéro donné à l'émission dans une série propre, PDF figé et gardé dans la base, journal
chaîné. Elle la remet ensuite au client par mail (PDF joint, circuit des mails de la spécification 002),
par un lien privé (texte prêt pour SMS ou WhatsApp, QR code à montrer) ou sur papier. Une erreur se
corrige par un avoir. L'accueil signale ce qui reste à faire. Pendant la phase de contrôle, tout part au
toucher de Youngmi ; l'automatique éventuel viendra plus tard, sur exception d'Olivier (R-01).

Trois réponses de l'expert-comptable (FR-003, FR-012, FR-013) sont attendues : le code les prend comme
réglages ou comme choix prudents provisoires (voir [research.md](./research.md)), et rien n'est publié
avant ses réponses et le feu vert d'Olivier.

## Technical Context

**Language/Version** : PHP 8 (OVH mutualisé), JavaScript sans bibliothèque, Python 3 pour la génération.

**Primary Dependencies** : aucune nouvelle. PDF écrit en PHP pur (police Helvetica standard du PDF,
encodage WinAnsi, sans fichier de police), QR code dessiné en PHP pur (SVG). Pas de bibliothèque
extérieure, pas de service payant.

**Storage** : SQLite ; schéma 11 : tables `factures` (factures et avoirs, PDF figé en BLOB, empreinte,
chaîne) et `factures_remises` (comment chaque document a été remis).

**Testing** : essais Playwright et PHP en ligne de commande sur base fictive, hors du dépôt (AM-42) :
`tests-gestion/factures/` (prep.php, unitaires.php, test.js), captures FR/KO, téléphone 390 px.

**Target Platform** : Chrome du Galaxy de Youngmi ; Safari et Chrome pour le client qui ouvre le lien.

**Project Type** : application web rendue par le serveur, dans le dépôt du site.

**Performance Goals** : émettre en moins d'une seconde ; PDF de moins de 20 ko.

**Constraints** : principes I à VIII de la constitution ; documents au client toujours en français
(FR-016) ; aucun envoi sans toucher (R-01) ; mail signé DKIM (P-07).

**Scale/Scope** : quelques factures par jour, deux ou trois comptes.

## Constitution Check

| Principe | Vérification | État |
|----------|--------------|------|
| I. Dépôt public | Identité de la société seulement (déjà publique sur les mentions légales) ; aucune donnée de client dans le code ou les essais versionnés | OK |
| II. Feu vert | Rien publié avant les réponses de Florent et le feu vert d'Olivier sur la version exacte | OK (à demander) |
| III. Fiche produit | La facture reprend les lignes et prix enregistrés dans la commande, jamais la carte du jour | OK |
| IV. Données personnelles | Copie du nom (et de l'adresse si le client la demande) dans la facture, gardée 10 ans par obligation légale ; lien privé désactivable, expiré après 12 mois ; registre T2 et Confidentialité mis à jour | OK |
| V. Sobre | Ni bibliothèque ni service : PDF et QR code écrits à la main | OK |
| VI. Langue | Écrans FR/KO ; facture, mail et page du lien en français (FR-016) | OK |
| VII. Preuves | Essais listés, sources puis régénération | OK |
| VIII. IA | L'assistant ne peut ni émettre ni envoyer une facture (aucun outil ne lui est ajouté) | OK |

## Project Structure

```text
specs/003-factures-particuliers/
├── spec.md · plan.md · research.md · data-model.md · quickstart.md · tasks.md
└── checklists/requirements.md

.sources/gestion/inc/
├── factures.php         # nouveau : préparation, émission, numéro, chaîne, avoirs, remises, alertes
├── pdf.php              # nouveau : petit écrivain PDF (Helvetica, WinAnsi, texte, traits)
├── qr.php               # nouveau : QR code (octets, correction M) en SVG
├── pages-factures.php   # nouveau : bloc Facture de la commande, Plus › Factures, PDF, page publique du lien
├── base.php             # schéma 11, constantes
├── email.php            # pièce jointe (multipart/mixed)
├── vues.php             # mail « FACTURE »
├── pages-commandes.php  # appel du bloc Facture ; alertes de l'accueil
├── pages-autres.php     # Plus › Factures
├── manuel.php · coreen.php
.sources/gen.py          # réécriture courte /facture/<id>-<jeton>
```

## Complexity Tracking

| Choix | Pourquoi | Alternative écartée |
|-------|----------|---------------------|
| PDF écrit à la main | Pas de Composer chez OVH, principe V | FPDF/TCPDF : bibliothèque de plus à suivre |
| QR code écrit à la main | Le client sans mail scanne sur le téléphone de Youngmi | Service en ligne de QR : fuite du lien privé |
| PDF gardé en base | Facture figée (FR-004), 10 ans, même si la gestion change de mise en page | Régénérer à la demande : le document changerait |

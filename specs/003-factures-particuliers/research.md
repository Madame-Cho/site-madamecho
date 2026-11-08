# Research : factures des particuliers

## R1. Facture ou reçu ?

**Décision** : une facture « acquittée » pour toute commande payée d'un particulier, préparée par la
gestion, émise au toucher. **Pourquoi** : pour un particulier, la facture n'est due que sur demande (vente
de biens), et les tickets papier systématiques sont interdits depuis le 01/08/2023 ; mais Youngmi veut un
document propre pour chaque commande payée, et une facture en règle sert de reçu. On ne crée pas de
second type de document (« reçu ») : un seul circuit, un seul numéro.

## R2. Numérotation (FR-003, provisoire)

**Décision provisoire** : série propre aux particuliers `P-AAAA-NNNN`, avoirs `AV-AAAA-NNNN`, numéro
donné à l'émission seulement, dans une transaction exclusive (`BEGIN IMMEDIATE`) avec unicité
`(serie, annee, seq)`. Les préfixes sont des réglages (`facture_prefixe`, `avoir_prefixe`) figés dès la
première facture de l'année. **À confirmer par Florent (question 2)**. Plusieurs séries sont admises si
chacune est continue et chronologique ; la série Qonto `F-2026-…` reste celle des restaurants.

## R3. Acompte (FR-012, provisoire)

**Décision provisoire** : une seule facture, quand la commande est payée en entier ; l'acompte déjà reçu
est rappelé (« dont acompte reçu »). Pas de facture d'acompte. **À confirmer (question 3)**.

## R4. Caisse (FR-013, provisoire)

**Décision d'Olivier (« 2 »)** : la gestion n'est pas une caisse ; Tap to Pay (relevé Qonto) pour la
carte, livre des recettes à part pour les espèces. La facture dit « acquittée le … par … » à partir de ce
que Youngmi a noté. **À confirmer (question 1)** : si Florent juge que cela entre dans l'article 286,
on retire la mention du moyen et on garde seulement « acquittée ».

## R5. PDF sans bibliothèque

**Décision** : écrivain minimal (une page A4, police standard Helvetica et Helvetica-Bold, encodage
WinAnsi qui contient « € » et les accents français, largeurs des caractères en table pour aligner à
droite). Le texte coréen n'entre jamais dans la facture (FR-016). Le PDF est figé en base avec son
empreinte SHA-256.

## R6. Journal chaîné (FR-015)

**Décision** : chaque document porte `chaine = sha256(chaine précédente | numéro | date | total | empreinte du PDF)`.
Plus › Factures recalcule la chaîne et les empreintes : une ligne changée, effacée ou un PDF retouché est
signalé. Les remises sont aussi chaînées à leur facture via le journal des événements.

## R7. Lien privé et QR code (FR-008)

**Décision** : `madamecho.com/facture/<id>-<jeton>` (jeton aléatoire de 24 caractères hexadécimaux,
gardé en base, comparé en temps constant), page sans index (`noindex`, `X-Robots-Tag`), qui montre le
résumé et le PDF à télécharger ; désactivable d'un toucher ; expire 12 mois après l'émission (le client
garde le PDF). Le QR code de ce lien est dessiné en SVG par la gestion, jamais par un service extérieur.

## R8. Mail avec pièce jointe

**Décision** : `envoyer_email_client` reçoit une liste de pièces jointes : `multipart/mixed` qui contient
le `multipart/alternative` existant puis le PDF en base64. Signature DKIM inchangée (en-têtes signés).

## R9. Conservation (FR-014)

**Décision** : la table `factures` ne dépend pas de la fiche client (copie figée) ; l'effacement d'un
client ou l'anonymisation d'une demande n'y touche pas. Purge des documents de plus de 10 ans par la
tâche de nuit ; jetons des liens vidés après 12 mois.

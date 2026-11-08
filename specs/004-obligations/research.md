# Research : écran « Obligations », lot 1

## R1. Numéro de schéma (essais dans les deux ordres, audit n° 7, constat 11)

La branche des factures (spec 003, non publiée) prend le schéma 11. Décision (révisée en construisant) :
le lot 1 ne prend PAS de numéro de schéma. Un numéro 12 aurait fait sauter l'étape 11 de la branche des
factures (son garde « déjà en 11 ou plus »). Les tables du lot 1 se créent dans `obligations_schema()`,
écrite avec `CREATE TABLE IF NOT EXISTS`, appelée par `gestion_schema` AVANT le garde du numéro de
version, et marquée à part dans `parametres` (`schema_obligations` = 1). Ainsi l'ordre de publication
est libre : lot 1 puis factures, ou factures puis lot 1. Celui des deux qui arrive second sur main
rebase et refait les essais de migration.

## R2. Historique : table propre plutôt que `evenements`

`evenements` est lu par les pastilles des commandes et des fiches, l'assistant et la corbeille.
Y mêler les démarches compliquerait ces requêtes (audit n° 5). Décision : `demarches_historique`
(création, modification, fait, sans objet, rouverte), avec auteur et ancien/nouveau texte.

## R3. Couleurs et ordre

- Rouge : échéance dépassée et pas faite.
- Orange : échéance dans son préavis (30 jours par défaut, `OBLIGATIONS_JOURS = 30` ; préavis propre
  à chaque règle et chaque démarche, audit n° 7, constat 2).
- « Date à confirmer » : ni rouge ni orange, rubrique à part en haut de « Échéances ».
- Le texte dit toujours l'état : « En retard depuis le 3 novembre », « Avant le 1er décembre ».
- Sans couleur : sans date ou plus loin.
- Ordre : rouge, orange, puis par date, puis sans date, puis priorité, puis plus ancienne.

## R4. Le récap du lundi (FR-014) : à trancher par Olivier

Ferdinand travaille dans la boîte d'Olivier et ne voit pas la base. Trois façons :

| | Comment | Pour | Contre |
|--|--|--|--|
| A | La tâche de nuit du lundi envoie un mail interne court de site@ à contact@ (copie à Olivier par le transfert existant) : « Obligations de la semaine », titres et dates, sans donnée de client. Ferdinand le reprend dans son récap. | Rien à installer ; marche dès la mise en ligne | Envoi automatique de la gestion : exception à R-01 (v0.45 : la gestion envoie au toucher de Youngmi), à accorder par Olivier ; signé DKIM « site » (P-07) |
| B | Le miroir existant (`page_miroir`, POST avec clé) gagne un jeu « obligations » ; un script Google dans contact@ le copie dans une feuille du Drive que Ferdinand lit. | Aucun mail | Script et clé à installer par Olivier ; le miroir n'est pas encore branché |
| C | Pas de lien : Ferdinand rappelle seulement « ouvrir Obligations ». | Rien à faire | Ne dit pas ce qui est en retard |

Décision d'Olivier (carte du 2026-11-08, 19:25 heure de Paris) : A, mail interne de site@ vers contact@
seulement, jamais à un client, signé DKIM « site », exception R-01 limitée à ce mail (à inscrire au
rulebook à la publication, avec son accord).

## R5. Liste initiale (FR-003)

Jamais dans le dépôt. Le Claude d'Olivier prépare, après vérification de chaque point dans le Drive
et la Mémoire, un fichier CSV dans les fichiers du projet (hors dépôt) ; Olivier l'importe par
Obligations › Échéances › Reprise d'une liste (compte administrateur, même parcours que la reprise des factures du 07/11), puis le fichier
est supprimé. La reprise refuse les doublons (même titre et même échéance) et dit ce qu'elle a fait.

## R6. Déclencheur « nouveau restaurant » (corrigé, audit n° 7, constat 1)

Quand une commande d'un client professionnel passe « confirmée » et que c'est sa première commande
confirmée, une démarche importante « Vérifier la dérogation (Cerfa 13982) avant de livrer » est créée
pour Youngmi, échéance la veille de la livraison, sauf si une démarche du même déclencheur existe
déjà pour ce client (clé unique `declencheur`). La dérogation se déclare avant de fournir un nouveau
commerce et se met à jour à tout changement important (destinataires, produits, quantités). Le texte de la démarche ne
reprend que le nom commercial du restaurant (donnée de la société cliente, pas d'une personne).

## R8. Récurrence et tâche de nuit (audit n° 7, constats 3 et 4)

Chaque règle a une date de départ, une fréquence (annuelle, trimestrielle, mensuelle, ou « premier
lundi du mois ») et un préavis. La période d'une occurrence est sa date d'échéance calculée (jour du
mois ramené au dernier jour si besoin : 31 → 30 ou 28/29 ; 29 février → 28 février). Une démarche par
(règle, période), contrainte UNIQUE en base : la création est idempotente (`INSERT OR IGNORE`). La
tâche de nuit, et aussi l'ouverture de l'écran, créent toutes les occurrences dont le préavis est
commencé, sans limite de rattrapage au-delà de 12 périodes. La date du dernier calcul est gardée.

## R9. Preuve, conservation, références (constats 7, 8, 10)

- `importante` (0/1) : « C'est fait » demande alors un lien Drive ; « Envoyé, j'attends la réponse »
  donne l'état `attente` (orange tant qu'il dure, sans échéance).
- Conservation : `fait` et `sans_objet` effacés 10 ans après `fait_le` (tâche de nuit), historique
  compris ; aide sous les champs libres : jamais de nom ni de donnée d'un client particulier.
- `ref_genre` + `ref_id` facultatifs, vides au lot 1.
- Registre des traitements : T8 (gestion) complété d'une ligne « démarches internes » à la publication.

## R7. Qui peut faire quoi

Les deux comptes actuels ont les mêmes droits (choix d'Olivier, Q1). L'historique dit qui a fait quoi ;
la pastille ambre signale à l'un ce que l'autre a ajouté ou changé (système « vu par personne » des
pastilles, genre `demarche`).

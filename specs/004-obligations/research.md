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

## R6. Nouveau client professionnel (corrigé, audit n° 7 passe 1 constat 1, passe 2 P2-02 et P2-14)

Dès qu'un client est professionnel (fiche « professionnel » ou commande « pro » ni annulée ni sans suite),
à la création d'une commande, à sa confirmation, et au calcul de nuit ou à l'ouverture de l'écran (rattrapage
d'une fiche passée « professionnel »), une démarche importante « Nouveau client professionnel : vérifier les
obligations » est créée pour Youngmi, échéance la veille de sa prochaine livraison (sinon dans 7 jours), une
seule fois par client (déclencheur `pro:<id>`). Elle n'affirme pas que le Cerfa 13982 est toujours obligatoire :
elle fait vérifier l'usage des gâteaux (restaurant ou commerce qui sert ou revend, ou entreprise pour ses
équipes), les produits, quantités et distance, les déclarations déjà faites, la facture pro ; « Sans objet »
demande la raison et sa source. Une fois cette vérification close, chaque gâteau que ce client n'a jamais eu
redemande une vérification (déclencheur `pro:<id>:<produit>`), jamais de rappel répété. Le titre ne contient
aucun nom : le client est relié par `ref_genre` = client et `ref_id`, et son nom ne s'affiche que dans la
fiche de la gestion. Les textes écrits par la gestion se traduisent en coréen ; ceux tapés jamais.

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

## R10. Corrections de la passe 2 de l'audit n° 7 (version 828f56a)

- P2-01 : le plafond de 12 porte sur les périodes manquantes (les plus récentes), avec un curseur : jamais
  de retour avant la dernière période créée (hors « sans objet »).
- P2-03 : le mail du lundi ne recopie que des titres contrôlés (écrits par la gestion, venus d'une échéance
  ou de la liste de départ) ; une démarche tapée à la main y figure comme « Démarche ajoutée à la main ».
  Jamais d'explication, d'origine, de note ni de nom.
- P2-04 : l'écran est réservé à contact@ et aux comptes administrateurs actifs (`obligations_acces`).
- P2-05 : une échéance modifiée met à jour ses démarches ouvertes ; si la date ou la fréquence change,
  celles pas encore dues hors calendrier passent « sans objet » (la nouvelle date les remplace).
- P2-06 : une démarche en attente dont l'échéance est passée le dit en rouge, sans compter dans le chiffre.
- P2-07 : la semaine du mail est réservée en base avant l'envoi (`obligations_mail_AAAA-SNN`).
- P2-09 : l'historique garde `auteur_id` ; « Ajoutée » / « A changé » par démarche et par échéance, vus
  seulement une fois affichés.
- P2-10 : une date impossible est refusée avec un message (jamais effacée en silence).
- P2-13 : le filtre « Les miennes » passe avant la limite des 200.
- P2-08, P2-11, P2-12 : hors code (liste de départ T017, registre des traitements, recette avec Youngmi).

## R11. Corrections de la passe 3 de l'audit n° 7 (version 8ae744b)

- P3-01 : le mail du lundi ne contient plus aucun texte tapé : seuls les libellés figés de la gestion
  (`obligations_textes_figes`) gardent leur titre ; toute autre démarche y figure par « Démarche n° … »,
  sa date et pour qui.
- P3-02 : vérification aussi quand une fiche passe « professionnel » (`client_modifier`), quand une
  commande pro est modifiée (gâteau, type, mode, sur mesure) même après confirmation, pour un gâteau sans
  fiche ou sur mesure (`pro:<id>:mesure:<commande>`) et pour la première livraison (`pro:<id>:livraison`).
  Les quantités déclarées (volumes de la dérogation) relèvent du lot 4 « Volumes ».
- P3-03 : seul le compte d'Olivier (le plus ancien administrateur actif autre que contact@) a accès ;
  un autre administrateur non.
- P3-04 : repère `schema_obligations` = 2 ; une base au repère 1 reçoit `auteur_id` (ALTER idempotent).
- P3-05 : la traduction dépend du texte (libellé figé ou non), plus du dernier auteur.
- P3-06 : l'état du mail de la semaine se voit dans Paramètres › Conservation ; après un échec, un bouton
  le renvoie à la main, une seule fois (réservation repassée « en cours » d'un seul geste).
- P3-07 : plus de clôture automatique après un changement de calendrier : les démarches d'avant restent
  ouvertes avec « Date d'avant la modification » ; le curseur ne compte que le calendrier en vigueur.
- P3-08 : tant que la liste de départ n'est pas reprise et relue, le mail le dit (« aucune conclusion
  possible ») et ne dit jamais « rien à faire » sans réserve.
- P3-09 : les gâteaux des commandes déjà reçues quand la première vérification est close ne redemandent rien.
- P3-10 : « premier lundi » ne tombe jamais avant la date donnée (sinon le mois suivant).

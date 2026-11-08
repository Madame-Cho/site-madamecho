# Research : écran « Obligations », lot 1

## R1. Numéro de schéma

La branche des factures (spec 003, non publiée) prend le schéma 11. Décision : les tables du lot 1 se
créent dans une étape propre, « schéma 12 », écrite avec `CREATE TABLE IF NOT EXISTS` et indépendante
de l'étape 11 ; `gestion_schema` ne s'arrête plus à 10. Si le lot 1 est publié avant les factures,
l'étape 11 s'appliquera quand même ensuite (les étapes testent chacune leur propre présence). Celui des
deux qui arrive second sur main rebase et refait les essais de migration.

## R2. Historique : table propre plutôt que `evenements`

`evenements` est lu par les pastilles des commandes et des fiches, l'assistant et la corbeille.
Y mêler les démarches compliquerait ces requêtes (audit n° 5). Décision : `demarches_historique`
(création, modification, fait, sans objet, rouverte), avec auteur et ancien/nouveau texte.

## R3. Couleurs et ordre

- Rouge : échéance dépassée et pas faite.
- Orange : échéance dans 30 jours ou moins (choix d'Olivier, Q5), valeur unique `OBLIGATIONS_JOURS = 30`.
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
Paramètres › Reprise des démarches (même parcours que la reprise des factures du 07/11), puis le fichier
est supprimé. La reprise refuse les doublons (même titre et même échéance) et dit ce qu'elle a fait.

## R6. Déclencheur « nouveau restaurant »

Quand une commande d'un client professionnel passe « confirmée » et que c'est sa deuxième commande
confirmée, une démarche « Mettre à jour la dérogation (Cerfa 13982) » est créée pour Youngmi, sauf si
une démarche ouverte du même déclencheur existe déjà pour ce client. Le texte de la démarche ne
reprend que le nom commercial du restaurant (donnée de la société cliente, pas d'une personne).

## R7. Qui peut faire quoi

Les deux comptes actuels ont les mêmes droits (choix d'Olivier, Q1). L'historique dit qui a fait quoi ;
la pastille ambre signale à l'un ce que l'autre a ajouté ou changé (système « vu par personne » des
pastilles, genre `demarche`).

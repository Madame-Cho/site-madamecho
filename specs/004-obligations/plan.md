# Implementation Plan : écran « Obligations », lot 1 (À faire et Échéances)

**Branch** : `004-obligations` (branche de travail `claude/project-thread-q75hfy`) | **Date** : 2026-11-08 | **Spec** : [spec.md](./spec.md)

**Input** : spécification `specs/004-obligations/spec.md` ; « Go lot 1 » d'Olivier (2026-11-08) ; ses cinq
réponses du 2026-11-08 (19:01-19:17, heure de Paris) : Youngmi et Olivier ajoutent, démarches d'Olivier aussi avec un
filtre, preuve = lien Drive et note, rappels dans la gestion et le récap du lundi, alerte 30 jours avant.

## Summary

Le lot 1 couvre US1 (voir ce qui reste à faire) et US5 sans les volumes (échéances récurrentes et rappel
de la dérogation à chaque nouveau restaurant). Un nouvel écran « Obligations » (의무) dans le menu Plus
liste les démarches de la personne connectée, les plus urgentes d'abord : rouge en retard, orange à
moins de 30 jours, puis les autres. Chaque démarche dit quoi faire, dans quel outil et à quel endroit,
pour quand et pourquoi. On la marque faite avec un lien Drive et une note, ou « sans objet » avec une
raison ; rien n'est jamais effacé. Les échéances récurrentes (extincteur, électricité, assurance…)
créent leur démarche 30 jours avant. Le lundi, la liste des démarches en retard ou proches rejoint le
récap de Ferdinand dans la boîte d'Olivier (mécanisme : voir [research.md](./research.md), R4, à
trancher par Olivier). Les démarches réelles entrent dans la base par une reprise faite par Olivier,
jamais par le dépôt.

Hors lot 1 : relevés d'hygiène (lot 2), étiquettes et lots (lot 3), volumes et documents (lot 4).

## Technical Context

**Language/Version** : PHP 8 (OVH mutualisé), JavaScript sans bibliothèque, Python 3 pour la génération.

**Primary Dependencies** : aucune nouvelle.

**Storage** : SQLite ; trois tables neuves `demarches`, `echeances`, `demarches_historique`
([data-model.md](./data-model.md)). Pas de numéro de schéma : marqueur à part `schema_obligations`, voir research R1 (la branche des factures prend 11).

**Testing** : essais PHP en ligne de commande et Playwright sur base fictive, hors du dépôt (AM-42) :
`tests-gestion/obligations/` (prep.php, unitaires.php, test.js), captures FR/KO, téléphone 390 px.

**Target Platform** : Chrome du Galaxy de Youngmi ; Chrome et Edge d'Olivier sur ordinateur.

**Project Type** : application web rendue par le serveur, dans le dépôt du site.

**Performance Goals** : écran affiché en moins de 300 ms avec 200 démarches.

**Constraints** : principes I à VIII de la constitution ; consigne d'Olivier du 07/11 (outil, page,
endroit exact dans chaque démarche) ; aucun envoi à Youngmi (R-01) ; rien de privé dans le dépôt.

**Scale/Scope** : quelques dizaines de démarches par an, deux comptes.

## Constitution Check

| Principe | Vérification | État |
|----------|--------------|------|
| I. Dépôt public | Aucune démarche réelle, adresse ou nom de restaurant dans le code, les essais versionnés ou la spec ; la liste initiale vit dans la base (reprise par Olivier) | OK |
| II. Feu vert | Rien publié sans le feu vert d'Olivier sur la version exacte, après l'audit n° 7 | OK (à demander) |
| III. Fiche produit | Sans objet au lot 1 | OK |
| IV. Données personnelles | Démarches internes à la société ; le texte libre peut citer un nom de fournisseur ou d'organisme, jamais de client par principe (aide sous le champ) ; registre des traitements : pas de nouveau traitement de clients (T1 inchangé), à confirmer dans l'audit | OK |
| V. Sobre | Ni bibliothèque ni service ; pas de stockage de pièces (lien Drive seulement) | OK |
| VI. Langue | Libellés FR/KO via `coreen.php` ; textes saisis jamais traduits (`translate="no"`) | OK |
| VII. Preuves | Essais listés, sources puis régénération, captures | OK |
| VIII. IA | L'assistant lit les démarches (résumé) mais ne peut ni en créer ni en fermer au lot 1 | OK |

## Project Structure

```text
specs/004-obligations/
├── spec.md · plan.md · research.md · data-model.md · quickstart.md · tasks.md

.sources/gestion/inc/
├── obligations.php        # nouveau : démarches, échéances, état et couleur, historique, reprise, résumé du lundi
├── pages-obligations.php  # nouveau : écran Obligations (À faire, Fait, Échéances), fiche d'une démarche, formulaires
├── base.php               # schéma : trois tables
├── pastilles.php          # pastille rouge (retard ou < 30 j, pour la personne) et ambre (ajout ou changement par l'autre)
├── pages-autres.php       # Plus › Obligations (la reprise d'une liste est dans Obligations › Échéances)
├── vues.php               # icône, lien direct sur grand écran
├── conservation.php       # tâche de nuit : créer les démarches des échéances à 30 jours ; le lundi, le résumé (R4)
├── modele.php             # déclencheur « 2e commande d'un nouveau restaurant » → démarche 13982
├── assistant-donnees.php  # lecture seule des démarches ouvertes
├── manuel.php · coreen.php
index.php                  # routes obligations, demarche, demarche-edition
```

## Phases

- **Phase 0** : [research.md](./research.md) (R1 à R7).
- **Phase 1** : [data-model.md](./data-model.md), [quickstart.md](./quickstart.md).
- **Phase 2** : [tasks.md](./tasks.md).
- **Audit** : commande d'audit n° 7 pour le ChatGPT d'Olivier (P-26) sur la spec, le plan et les tâches
  avant le code, puis une seconde passe sur la version construite avant la publication.


---

# Lot 2 : Hygiène au quotidien (US2), 2026-11-08

**Input** : « Go lot 2 » d'Olivier (2026-11-08, 20:51) ; ses trois cartes de 21:00 (voir spec, Clarifications, lot 2).

**Summary** : un écran Plus › Hygiène (위생), réservé comme Obligations à Youngmi et à Olivier, en trois rubriques :
Aujourd'hui (froid, réceptions, fournées, livraisons, « pas de production »), Historique (30 jours, export CSV) et
Appareils (frigos et plages, durées de conservation des gâteaux, réglages). Un rappel sur Aujourd'hui (accueil) dit où en
sont les relevés de froid du jour. Un jour de production sans relevé complet crée le lendemain la démarche figée
« Relevé de froid manquant » dans Obligations (tâche de nuit et ouverture). Corrections : nouvelle version, l'original reste.

**Storage** : tables à part `hygiene_appareils`, `hygiene_releves`, `hygiene_liens`, `hygiene_durees`, repère
`schema_hygiene` = 1 (même principe que R1 : le numéro de schéma ne bouge pas). Photos d'étiquettes dans le dossier des
données (`hygiene-photos/`, hors du site), servies seulement par la gestion après contrôle d'accès ; type vérifié sur le contenu.

**Hors lot 2** : saisie hors ligne (cas limite de la spec), reporté ; l'assistant ne lit pas encore les relevés ;
étiquettes et « Problème sur ce lot » (lot 3) ; volumes et documents (lot 4).

**Constitution Check (lot 2)** : I dépôt public (aucun relevé, nom ou photo dans le dépôt) OK ; II feu vert d'Olivier avant
publication, audit unique après le lot 4 (choix d'Olivier) OK ; III les durées de conservation viennent de Youngmi OK ;
IV données personnelles : le nom du restaurant (client professionnel) est relié par son identifiant, export réservé, registre
des traitements à compléter (T8) ; V sobre, sans bibliothèque OK ; VI FR/KO, textes tapés jamais traduits OK ; VII essais
hors dépôt `tests-gestion/hygiene/` OK ; VIII l'IA ne touche pas aux relevés OK.

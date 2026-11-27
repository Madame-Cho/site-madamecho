# Implementation Plan : plages de retrait à capacité

**Branch** : `007-plages-retrait` (branche de travail `claude/project-thread-ng5b6m`) | **Date** : 2026-11-27 | **Spec** : [spec.md](./spec.md)

**Input** : spécification `specs/007-plages-retrait/spec.md` ; feu vert d'Olivier pour la spécification et le code
(2026-11-27, 23:26, heure de Paris), avec un audit par son ChatGPT ensuite (P-26) ; publication sur feu vert (R-19).

## Summary

Les quatre « moments » du formulaire (matin, midi, après-midi, soir) deviennent six plages fixes, du lundi au
samedi. Une plage compte les retraits confirmés ou remis ; elle est complète à son maximum (5 par défaut,
réglable dans les Paramètres et pour une plage d'un jour dans l'Agenda) ou quand Youngmi la ferme. Le site
demande l'état des plages d'un jour et ne laisse choisir qu'une plage libre ; le serveur revérifie à l'envoi.
Le dimanche est refusé par le site, accepté dans la gestion avec un avertissement. Les mails et le lien
d'agenda donnent la plage. Dans la même version : les textes du site disent « du lundi au samedi » et
l'acompte disparaît du site et des formulaires de la gestion.

## Technical Context

**Language/Version** : PHP 8 (OVH mutualisé), JavaScript sans bibliothèque, Python 3 pour la génération.

**Primary Dependencies** : aucune nouvelle.

**Storage** : SQLite. Une table neuve `plages_reglages` ; un paramètre `plage_maximum` dans `parametres` ;
la plage d'une commande va dans la colonne existante `commandes.retrait_moment` (codes neufs), sans migration
des anciennes commandes ([research.md](./research.md), R1).

**Testing** : scripts PHP en ligne de commande sur une base d'essai, et parcours Playwright (site et gestion,
téléphone et ordinateur, français et coréen), données fictives seulement.

**Target Platform** : navigateur du téléphone de Youngmi (Galaxy S25) et ordinateur ; visiteurs du site.

**Project Type** : site statique généré + formulaire PHP + application PHP (gestion).

**Performance Goals** : la réponse « plages d'un jour » en moins d'une demi-seconde (une requête sur les
commandes d'un seul jour).

**Constraints** : CSP inchangée, aucun script tiers ; le formulaire reste utilisable sans réponse du serveur
(il revérifie à l'envoi) et sans base de la gestion ; rien de privé dans le dépôt public.

**Scale/Scope** : quelques commandes par jour ; six plages ; une personne qui règle.

## Constitution Check

| Principe | Vérification | État |
| --- | --- | --- |
| I. Dépôt public | Aucun client, montant réel ni réglage réel dans le dépôt ; essais sur données fictives | OK |
| II. Rien d'irréversible | Pas de mail nouveau ; les mails existants partent toujours au toucher (R-01) ; publication sur feu vert | OK |
| III. Produits | Aucun texte de produit inventé ; seule la mention d'acompte est retirée des fiches | OK |
| IV. Données personnelles | La réponse publique ne dit que « libre » ou « complet » par plage, jamais un nombre ni un motif | OK |
| V. Simple et sobre | Aucune bibliothèque ni service ; une table et un paramètre | OK |
| VI. Youngmi, téléphone, coréen | Nouveaux textes dans `coreen.php` ; manuel et « Quoi de neuf » FR/KO ; 44 px, 320 px | OK |
| VII. Sources, régénération, preuves | Modifications dans `.sources/`, régénération, essais listés avant le feu vert | OK |
| VIII. Écritures de l'IA | L'assistant lit les plages ; il peut proposer une plage pour une commande (carte de confirmation inchangée), jamais régler une plage | OK |

Après la conception : inchangé.

## Project Structure

```text
specs/007-plages-retrait/
├── spec.md, plan.md, research.md, data-model.md, quickstart.md, tasks.md
├── contracts/plages-du-jour.md      # la question du formulaire au serveur
└── checklists/requirements.md

.sources/
├── donnees.py          # PLAGES (au lieu de MOMENTS), jours fermés de la semaine, textes du dimanche, acompte retiré
├── gen.py              # formulaire « Plage horaire », pages Retrait, Conditions, pied de page, horaires lus par les moteurs
├── site.js, site.css   # liste des plages d'un jour, dimanche refusé
├── envoyer.php         # question « plages d'un jour », refus d'une plage complète ou d'un dimanche
└── gestion/inc/
    ├── base.php        # PLAGES, schema_plages (table plages_reglages), paramètre plage_maximum
    ├── modele.php      # plage d'une commande, plages d'un jour, réglage d'une plage
    ├── enregistrer.php # plages d'un jour pour le site (indispos comprises), sans motif
    ├── pages-agenda.php    # plages du jour choisi : « n sur max », fermer, rouvrir, maximum
    ├── pages-commandes.php # choix de la plage, avertissements, acompte retiré des formulaires
    ├── pages-autres.php    # Paramètres : maximum par défaut
    ├── vues.php        # textes « entre 10 h 30 et 12 h » (mails, cartes, agenda du client)
    ├── assistant-donnees.php # plage lisible et proposable par l'assistant
    ├── manuel.php, coreen.php
```

**Structure Decision** : on garde l'architecture existante ; la plage remplace le moment là où le moment
était lu, et les anciennes commandes gardent leur moment, lu comme avant.

## Complexity Tracking

Aucune violation de la constitution à justifier.

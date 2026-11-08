# Checklist : pastilles « nouveau » et « a changé »

**Purpose** : vérifier que FR-041 est clair, borné et testé avant le feu vert.
**Created** : 2026-11-08
**Feature** : [spec.md](../spec.md)

## Clarté des exigences

- [x] CHK001 FR-041 dit ce qui est rouge (à agir), ambre (a changé) et chocolat (outil) [Spec §FR-041, Clarifications 2026-11-08]
- [x] CHK002 Le « vu » est propre à chaque personne (choix d'Olivier) et se fait en ouvrant l'élément [Spec §FR-041]
- [x] CHK003 Ce qu'une personne fait elle-même ne lui est jamais signalé [Spec §FR-041, SC-014]
- [x] CHK004 Rien d'ancien ne s'allume au premier passage (point de départ posé à la migration) [data-model]
- [ ] CHK005 Les libellés et les couleurs sont validés par Olivier, puis par Youngmi pendant le pilote

## Accessibilité et langues

- [x] CHK006 La couleur n'est jamais seule : un mot l'accompagne, et un texte pour les lecteurs d'écran
- [x] CHK007 Rien ne clignote ; tout existe en coréen (menu, bloc, étiquettes, encadré, Quoi de neuf, manuel)

## Cas limites

- [x] CHK008 Commande supprimée (corbeille) : plus de pastille ; restaurée : signalée « Restaurée »
- [x] CHK009 Réponse d'un client rattachée à la main : signalée comme une réponse
- [x] CHK010 Au-delà de 30 jours, une pastille s'éteint seule
- [ ] CHK011 Audit du ChatGPT d'Olivier (P-26) rendu et traité

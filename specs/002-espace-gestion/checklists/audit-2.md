# Checklist : lot « audit n° 2 » (écritures de l'assistant, prix, plafond)

**Purpose** : vérifier que les exigences du lot sont écrites clairement et testées avant le feu vert.
**Created** : 2026-11-07
**Feature** : [spec.md](../spec.md)

## Clarté des exigences

- [x] CHK001 FR-027 dit ce qui est montré avant « Confirmer », y compris ce que la proposition ne dit pas [Spec §FR-027]
- [x] CHK002 FR-027 dit quand une proposition est refusée parce que la fiche a changé, et ce qui ne compte pas (commentaire) [Spec §FR-027, Clarifications]
- [x] CHK003 FR-029 dit quelles lignes gardent leur prix et lesquelles prennent celui de la carte [Spec §FR-029]
- [x] CHK004 FR-019 dit que le plafond est réservé avant chaque appel [Spec §FR-019]

## Mesurabilité

- [x] CHK005 SC-009 et SC-010 sont vérifiables sur données fictives [Spec §SC-009, §SC-010]
- [x] CHK006 SC-007 est tenu par construction (réservation) et vérifié par un essai [Spec §SC-007]

## Cas limites

- [x] CHK007 Remise d'une commande avec acompte reçu : l'acompte reste [Edge Cases]
- [x] CHK008 Création d'une commande déjà remise sans paiement dit [Edge Cases]
- [x] CHK009 Question qui ferait dépasser le plafond [Edge Cases]
- [ ] CHK010 Proposition faite en mode vocal : la carte reste lisible et complète au téléphone (à vérifier au pilote T-22)

## Langue et constitution

- [x] CHK011 Les nouveaux messages ont leur coréen [Principe VI]
- [x] CHK012 Principe VIII ajouté à la constitution (1.1.0), à ratifier par Olivier [Constitution]
- [ ] CHK013 Relecture coréenne humaine des nouveaux messages (T-23)

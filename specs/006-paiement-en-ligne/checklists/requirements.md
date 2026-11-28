# Specification Quality Checklist: Paiement en ligne des commandes

**Purpose**: Validate specification completeness and quality before proceeding to planning
**Created**: 2026-11-21
**Feature**: [spec.md](../spec.md)

## Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

## Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Success criteria are technology-agnostic (no implementation details)
- [x] All acceptance scenarios are defined
- [x] Edge cases are identified
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

## Feature Readiness

- [x] All functional requirements have clear acceptance criteria
- [x] User scenarios cover primary flows
- [x] Feature meets measurable outcomes defined in Success Criteria
- [x] No implementation details leak into specification

## Notes

- 2026-11-28 : plus aucun marqueur. FR-007 levé le 2026-11-21 ; FR-013 (reçu automatique) et
  FR-018 (rien n'est gardé après annulation) tranchés le 2026-11-27 ; l'acompte est retiré (FR-002) ;
  le temps 2 entre dans la même version, avec son interrupteur, les plages étant en ligne.
- Le prestataire (lien de paiement de Qonto) est nommé dans le constat et les hypothèses parce que
  c'est le système de paiement de Madame Cho ; les exigences restent indépendantes de l'outil.
- Vérifié le 2026-11-28 (aide Qonto et Mollie) : remboursement dans Mollie, liens valables 6 mois,
  versement chaque jour ouvré, réserve glissante d'environ 20 % ; plafond par lien encore inconnu.
- Rien de privé : aucun nom de client, aucun tarif professionnel, aucun identifiant de compte.

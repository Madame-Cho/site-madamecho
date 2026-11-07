# Constitution du site et de l'espace de gestion Madame Cho

Ce texte résume, pour les compétences Spec Kit (`/speckit-*`), les règles du Rulebook Madame Cho
qui s'appliquent au code de ce dépôt. Il ne remplace pas le rulebook et n'en recopie pas les
règles : il les cite par leur numéro (P-25). En cas d'écart, le rulebook fait foi (R-02, R-23).

## Core Principles

### I. Dépôt public : aucun secret, aucune donnée privée

Ce dépôt est public. Rien de ce qui suit n'y est jamais écrit, ni dans le code, ni dans une
spécification, un plan, une tâche, un test ou un message de commit (R-08, R-35, R-38) :
mot de passe, code, clé, jeton ou identifiant de connexion ; tarif professionnel ; nom, email,
téléphone ou adresse d'un client ; recette ; donnée de santé ; photo d'un restaurant client ;
réponse privée de Youngmi. Les données vivent sur le serveur OVH, hors du dossier servi
(`.madamecho-donnees/`), et les secrets dans le gestionnaire de mots de passe. Une spécification
décrit le comportement avec des exemples fictifs. Les noms des restaurants que le site publie
déjà avec leur accord ne sont pas recopiés dans les spécifications : on renvoie à la page Pros.

### II. Rien d'irréversible sans le feu vert de la bonne personne

- Publier, c'est pousser une version exacte sur `main` et `production` (P-15). Cela n'arrive
  qu'après le feu vert explicite d'Olivier ou de Youngmi sur cette version, dans la conversation
  (R-07, R-19). Ensuite : vérification en ligne, journal des publications, Registre.
- Aucun email n'est envoyé par une IA (R-01). Un outil prépare un brouillon ou un texte, et une
  personne l'envoie, ou clique elle-même sur le bouton qui l'envoie.
- Tout outil qui envoie des emails en @madamecho.com est signé DKIM (rulebook v0.38) ; un email
  qui ne peut pas être signé ne part pas.
- Paiement, suppression, signature, publication sur les réseaux sociaux : jamais par une IA ni
  par le code sans la personne compétente (R-07, R-24). Aucune IA n'a de compte dans
  l'espace de gestion.

### III. L'information sur un produit vient de la fiche validée par Youngmi

Composition, allergènes, conservation, prix : le site et la gestion ne les affichent qu'à partir
de la fiche produit validée par Youngmi, jamais inventés ni devinés (R-39, partie 4.2). Les
14 allergènes majeurs sont donnés pour chaque produit avant la demande ; « inconnu » n'est jamais
lu comme « absent ». Une image, même retouchée avec une IA, reste fidèle au produit réel ; une
image entièrement générée par IA qui est publiée est signalée comme telle (R-39). Un gâteau dont
une information manque n'est pas proposé.

### IV. Données personnelles : le strict nécessaire, le temps nécessaire

Chaque donnée collectée sert un but écrit (R-38, partie 4.4). Pas de donnée de santé, ni
d'allergie d'un client nommé, ni d'adresse complète dans la gestion. Durées tenues par le
code lui-même : demande sans suite effacée 12 mois après le dernier échange, client sans
commande 3 ans, journal des connexions et conversations de l'assistant 6 mois, rapports 12 mois.
Tout nouveau destinataire, outil, formulaire ou sous-traitant met à jour, dans la même version,
le registre des traitements (20/01) et la page Confidentialité. Aucun traceur soumis au
consentement : la mesure d'audience reste en exemption CNIL.

### V. Simple, sobre, sans nouveau service payant

Site statique généré (`.sources/gen.py`), un petit PHP pour le formulaire, PHP 8 et SQLite pour
la gestion, chez l'hébergeur actuel. Aucune bibliothèque ni script tiers chargé par les pages,
polices servies par le site, politique de sécurité (CSP) inchangée. Aucun nouveau service, payant
ou non, sans la décision d'Olivier et sa ligne dans « Outils digitaux » (R-20) ; ce qui touche
la gestion de l'entreprise est payé par Madame Cho, jamais par Olivier.

### VI. Pour Youngmi, sur son téléphone, dans sa langue

Le site public est en français (R-22). L'espace de gestion est en français et en coréen :
tout nouveau texte visible entre dans le dictionnaire `coreen.php`, et ce qui est saisi
(noms, adresses, commentaires) porte `translate="no"`. Tout est pensé d'abord pour le téléphone :
aucun défilement horizontal à 320 px, zones à toucher d'environ 44 px, contraste d'au moins
4,5:1 (WCAG 2.2), parcours possible au clavier. Le manuel de la gestion décrit l'état réel de
l'outil, jamais une promesse, et change dans la même version que l'écran.

### VII. On modifie les sources, on régénère, on prouve

On modifie `.sources/`, jamais une page générée ; on régénère
(`python3 -I -B .sources/gen.py . --en-ligne`) et on commite les deux. Avant de demander un feu
vert, on dit quels essais ont tourné et avec quel résultat : parcours en français et en coréen,
téléphone et ordinateur, formulaire, tâche de nuit, sur des données fictives seulement.

### VIII. Une IA qui prépare une écriture en montre tous les effets

Quand l'assistant de la gestion prépare une modification, la carte de confirmation montre tout ce
que le formulaire écrira, même ce que la proposition ne dit pas (un paiement, un moyen, un prix) ;
un paiement ou un montant ne change jamais en silence ; une proposition dont la fiche a changé
avant le toucher sur « Confirmer » est refusée ; changer une date ne recalcule jamais des prix
déjà enregistrés ; un plafond de coût est réservé avant chaque appel au modèle (audit n° 2 du
ChatGPT d'Olivier, 2026-11-07, décision « Tout retenir, C1-C3 et C8 d'abord »).

## Contraintes techniques

- Hébergement OVH (Apache) ; la racine du dépôt est la racine du site. Les dossiers et fichiers
  dont le nom commence par un point ne sont jamais servis (règle du `.htaccess` racine) ;
  `specs/` est fermé par son propre `.htaccess`.
- `production` est ce qui est en ligne, `main` la suit ; chaque fil de travail a sa branche
  `claude/project-thread-…`, et Spec Kit ne crée pas de branche.
- La gestion n'a qu'une porte d'entrée (`gestion/index.php?p=…`), des comptes nominatifs, des
  mots de passe d'au moins 12 caractères, un jeton anti-CSRF par formulaire, des sessions
  fermées après un changement de mot de passe et une base hors du dossier servi.
- Les spécifications sont écrites en français, comme tout ce que produit le système (R-22).

## Démarche

1. La spécification de référence reste dans le Drive (91/03 pendant un chantier, puis le dossier
   du domaine, P-08). Le dossier `specs/` en est la version technique, sans rien de privé.
2. Spécifier, clarifier, planifier, découper en tâches (`/speckit-specify`, `/speckit-clarify`,
   `/speckit-plan`, `/speckit-tasks`) ; un audit par une autre IA éclaire la décision sans rien
   modifier (P-26).
3. Construire sur la branche de travail, essayer (principe VII), montrer à Olivier.
4. Publier seulement après le feu vert sur la version exacte (principe II), puis tracer.
5. Un problème rencontré devient une ligne de l'onglet « Améliorations » (R-44) ; ce qui touche
   le rulebook est signalé à Olivier (R-11), qui seul le modifie (R-16).

## Governance

- Le rulebook prime sur cette constitution, et cette constitution prime sur les plans et les
  tâches de `specs/`. Chaque plan passe la vérification « Constitution Check » ; un écart y est
  justifié ou corrigé.
- Seul Olivier, par son Claude, modifie cette constitution, comme le rulebook (R-16). Une
  modification du rulebook qui la contredit la met à jour dans la même session.
- Version au format MAJEUR.MINEUR.CORRECTIF : MAJEUR quand un principe est retiré ou
  redéfini, MINEUR quand un principe ou une section est ajouté, CORRECTIF pour la forme.

**Version**: 1.1.0 (principe VIII ajouté le 2026-11-07 après l'audit n° 2) | **Ratified**: TODO(RATIFICATION_DATE) : proposée le 2026-11-07 par Claude, IA maître d'Olivier Dion, à ratifier par Olivier | **Last Amended**: 2026-11-07

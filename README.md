# site-madamecho

Code source du site web de Madame Cho (madamecho.com), pâtisserie de Youngmi Cho à Meudon.

## Organisation

- `public/` : les fichiers mis en ligne (HTML, CSS, images). C'est ce dossier qui est servi par l'hébergement.
- Hébergement : OVH, offre Perso, compte client « MADAME CHO ».
- Tous les textes visibles du site sont en français (R-22 du Rulebook Madame Cho).

## Branches et publication

- `main` : branche de travail. Rien de ce qui y est poussé n'est mis en ligne.
- `production` : ce qui est en ligne. OVH déploie automatiquement cette branche (association Git et webhook).
- Publier = fusionner `main` dans `production`. Seuls le Claude d'Olivier et le ChatGPT de Youngmi publient, et uniquement après le feu vert explicite d'Olivier ou de Youngmi sur la version exacte ; chaque publication est notée dans le journal des publications (Drive, 15/04). Voir R-19 et P-15 du Rulebook Madame Cho.
- Aucun mot de passe, clé ou jeton dans ce dépôt (R-08).

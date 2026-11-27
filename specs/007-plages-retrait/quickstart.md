# Quickstart : vérifier les plages de retrait

Données fictives seulement, sur la machine de travail (jamais en ligne).

1. Régénérer : `python3 -I -B .sources/gen.py . --en-ligne` à la racine du dépôt.
2. Base d'essai : copier la base d'essai de la gestion dans `../.madamecho-donnees/gestion.sqlite`, puis lancer
   `MC_ESSAI=1 php -S 127.0.0.1:8767 -t .` à la racine.
3. Script `plages.php` (ligne de commande) : crée 5 retraits confirmés sur 10 h 30 – 12 h un samedi, ferme
   16 h – 18 h un jeudi, met une indispo de 14 h 30 à 15 h un vendredi, puis vérifie : états des plages,
   comptes (demandes, propositions, livraisons et annulées non comptées ; anciennes commandes rangées dans la
   bonne plage), réponse publique (aucun nombre), refus du dimanche, du 24 décembre et d'une plage complète.
4. Parcours `plages.js` (Playwright) :
   - site, téléphone 360 px et ordinateur : la liste des plages d'un samedi montre « complet » sur 10 h 30 – 12 h ;
     un dimanche affiche la phrase du dimanche ; l'envoi forcé sur la plage complète est refusé ;
   - gestion, français et coréen : Agenda d'un samedi (« 5 sur 5 »), fermer puis rouvrir une plage, changer le
     maximum d'une plage et le maximum par défaut (Paramètres) ; confirmer une demande sur une plage complète
     (avertissement) ; aperçu de la confirmation (« entre 10 h 30 et 12 h ») ; formulaires sans acompte.
5. Textes publiés : aucune occurrence de « tous les jours » pour le retrait ni d'« acompte » dans les pages
   générées ; horaires des moteurs du lundi au samedi.

Résultat attendu : toutes les vérifications passent ; captures rangées dans les fichiers du projet
(`madame-cho/clients/plages-retrait/`).

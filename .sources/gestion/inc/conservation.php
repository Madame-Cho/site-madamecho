<?php
/*
 * Madame Cho : tâche de nuit de l'espace de gestion, lancée par ma-demande/menage.php (tâche planifiée OVH, chaque nuit vers 4 h).
 * Applique les durées de conservation de la politique de confidentialité ; ne crée jamais la base si l'espace n'est pas encore ouvert.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/modele.php';
require_once __DIR__ . '/reponses.php';
require_once __DIR__ . '/photos.php';     // photos_purger() : photos des fiches disparues ou dont l'adresse a changé   // reponses_purger() : sans lui, la tâche de nuit s'arrêtait en chemin (trouvé le 08/11/2026)
require_once __DIR__ . '/email.php';        // le mail interne du lundi (écran Obligations)
require_once __DIR__ . '/obligations.php';  // démarches des échéances, conservation 10 ans, mail du lundi (spécification 004)
require_once __DIR__ . '/qonto.php';        // le rapprochement automatique avec Qonto (13/11/2026) : une lecture fraîche chaque nuit
require_once __DIR__ . '/rapprochement.php';

function tache_de_nuit(): array {
    if (!gestion_base_existante()) return [];
    $cr = conservation_executer('tâche de nuit');
    // Accusés de réception (09/11/2026) : l'empreinte d'une adresse ne sert que 24 heures (politique de confidentialité)
    q("DELETE FROM parametres WHERE cle LIKE 'accuse:%' AND CAST(valeur AS INTEGER) < ?", [time() - 86400]);
    noms_majuscules_reprise();   // une seule fois : majuscules oubliées dans les noms déjà enregistrés (demande d'Olivier du 08/11/2026)
    // Écran Obligations : une erreur ici ne doit pas arrêter la tâche de nuit (leçon du 08/11/2026)
    try { $cr['obligations'] = obligations_nuit(); }
    catch (\Throwable $e) { error_log('madamecho gestion : obligations : ' . $e->getMessage()); $cr['obligations'] = ['erreur' => $e->getMessage()]; }
    // Rapprochement Qonto : une erreur de Qonto n'arrête pas la tâche de nuit ; Aujourd'hui prévient après 3 jours sans lecture
    try { $cr['rapprochement'] = rapprochement_nuit(); }
    catch (\Throwable $e) { error_log('madamecho gestion : rapprochement : ' . $e->getMessage()); $cr['rapprochement'] = ['erreur' => $e->getMessage()]; }
    parametre_ecrire('derniere_nuit', maintenant());
    return $cr;
}

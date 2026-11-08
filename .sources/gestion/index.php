<?php
/*
 * Madame Cho : espace de gestion (clients, commandes, produits), https://madamecho.com/gestion/
 * Seule porte d'entrée : index.php?p=… Réservé à Youngmi et aux personnes qu'elle invite ; jamais indexé.
 * Spécification v1 du 06/11/2026 (dossier clients du projet). Aucun secret dans ce dépôt public (R-08).
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

require_once __DIR__ . '/inc/base.php';
require_once __DIR__ . '/inc/modele.php';
require_once __DIR__ . '/inc/vues.php';
require_once __DIR__ . '/inc/langue.php';
require_once __DIR__ . '/inc/email.php';
require_once __DIR__ . '/inc/reponses.php';
require_once __DIR__ . '/inc/matomo.php';
require_once __DIR__ . '/inc/manuel.php';
require_once __DIR__ . '/inc/pastilles.php';
require_once __DIR__ . '/inc/assistant.php';
require_once __DIR__ . '/inc/assistant-donnees.php';
require_once __DIR__ . '/inc/pages-connexion.php';
require_once __DIR__ . '/inc/pages-commandes.php';
require_once __DIR__ . '/inc/pages-agenda.php';
require_once __DIR__ . '/inc/pages-clients.php';
require_once __DIR__ . '/inc/pages-produits.php';
require_once __DIR__ . '/inc/pages-autres.php';
require_once __DIR__ . '/inc/obligations.php';
require_once __DIR__ . '/inc/pages-obligations.php';

$pages_publiques = ['connexion' => 'page_connexion', 'oubli' => 'page_oubli', 'mot-de-passe' => 'page_mot_de_passe', 'deconnexion' => 'page_deconnexion', 'miroir' => 'page_miroir', 'langue' => 'page_langue', 'rdv' => 'page_rdv', 'reponses' => 'page_reponses'];
$pages = ['accueil' => 'page_accueil', 'commandes' => 'page_commandes', 'commande' => 'page_commande', 'commande-edition' => 'page_commande_edition', 'apercu-mail' => 'page_apercu_mail', 'corriger' => 'page_corriger',
          'agenda' => 'page_agenda', 'production' => 'page_production', 'clients' => 'page_clients', 'client' => 'page_client', 'client-edition' => 'page_client_edition', 'releve' => 'page_releve',
          'produits' => 'page_produits', 'produit' => 'page_produit', 'ingredients' => 'page_ingredients', 'ingredient' => 'page_ingredient',
          'statistiques' => 'page_statistiques', 'taxe' => 'page_taxe', 'plus' => 'page_plus', 'rapprochement' => 'page_rapprochement', 'corbeille' => 'page_corbeille', 'manuel' => 'page_manuel', 'aide' => 'page_manuel', 'assistant' => 'page_assistant', 'olivier' => 'page_olivier', 'reglages' => 'page_reglages', 'export' => 'page_export',
          'nouveautes' => 'page_nouveautes', 'pastilles' => 'page_pastilles', 'obligations' => 'page_obligations', 'demarche' => 'page_demarche', 'echeance' => 'page_echeance'];

$p = is_string($_GET['p'] ?? null) ? $_GET['p'] : 'accueil';
try {
    db();
} catch (\Throwable $e) {
    error_log('madamecho gestion : base indisponible : ' . $e->getMessage());
    page_indisponible();
}
try {
    if (isset($pages_publiques[$p])) {
        (__NAMESPACE__ . '\\' . $pages_publiques[$p])();
    } elseif (!utilisateur_courant()) {
        $retour = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($pages[$p]) && $p !== 'accueil' ? '?' . (string)($_SERVER['QUERY_STRING'] ?? '') : '';
        rediriger('connexion', $retour !== '' ? ['retour' => $retour] : []);
    } else {
        proposition_avant(isset($pages[$p]) ? $p : 'accueil');   // « Confirmer » sous une réponse de l'assistant : le formulaire habituel, rempli maintenant
        (__NAMESPACE__ . '\\' . ($pages[$p] ?? 'page_accueil'))();
    }
} catch (\Throwable $e) {
    error_log('madamecho gestion : ' . get_class($e) . ' : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    if (!headers_sent()) http_response_code(500);
    $detail = GESTION_ESSAI ? '<pre class="corps">' . h($e->getMessage() . "\n" . basename($e->getFile()) . ':' . $e->getLine()) . '</pre>' : '';
    mise_en_page('Erreur', titre_page('Un souci technique', '') . '<p>L\'action n\'a pas pu se faire. Rien n\'est perdu : revenez en arrière et réessayez. Si cela recommence, prévenez Olivier en lui disant ce que vous faisiez.</p>'
        . $detail . '<p>' . lien_bouton(u('accueil'), 'Revenir à l\'accueil', 'principal') . '</p>', '');
}

<?php
/*
 * Madame Cho : enregistrement d'une demande du formulaire du site dans l'espace de gestion.
 * Appelé par ma-demande/envoyer.php APRÈS l'envoi de l'email à contact@ (même référence MC-…) :
 * si l'enregistrement échoue, l'email est parti quand même et le visiteur ne voit aucune différence.
 * Le formulaire y demande aussi, avant l'envoi, l'état des plages de retrait du jour choisi (spécification 007) : libre ou complète.
 * Aucun cookie, aucune session : le visiteur du site n'a pas de compte (GESTION_CONTEXTE_SITE).
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

if (!defined('GESTION_CONTEXTE_SITE')) define('GESTION_CONTEXTE_SITE', true);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/modele.php';
require_once __DIR__ . '/accuse.php';   // l'accusé de réception au client (09/11/2026)
require_once __DIR__ . '/paiement.php'; // « Commander et payer » et les places retenues pendant un paiement (spécification 006)

/**
 * $d : ref, prenom, nom, email, telephone, date (AAAA-MM-JJ), plage (code de PLAGES ; « moment » pour une ancienne page), lignes ([p, c, q, prix (total de la ligne), plaque]),
 * perso, pro (bool), societe, besoin, via (facultatif : campagne Matomo de la visite, « qr-code packaging » après un scan du QR code,
 * « instagram bio » par le lien de la page Instagram), depuis (facultatif : le bouton de l'accueil par lequel la demande a commencé, en clair),
 * connu (facultatif : la réponse à « Comment avez-vous connu Madame Cho ? », un code de CONNU_PAR), paiement (facultatif : true pour une
 * commande de la carte que le client paie en ligne tout de suite, spécification 006 : notée « paiement en ligne en cours »).
 * Renvoie le numéro interne de la commande (0 : rien d'enregistré).
 * Ne fait rien tant que l'espace n'est pas ouvert, ni avant que l'enregistrement soit activé dans Paramètres (mise en ligne en deux temps).
 */
function enregistrer_demande(array $d): int {
    if (!gestion_base_existante() || parametre('demandes_site') !== '1') return 0;
    $pdo = db();
    if (val("SELECT id FROM commandes WHERE reference = ?", [$d['ref']])) return (int)val("SELECT id FROM commandes WHERE reference = ?", [$d['ref']]);
    $quand = maintenant();
    $pdo->beginTransaction();
    try {
        // Prénom et nom séparés depuis le 07/11/2026 ; une page du formulaire restée ouverte avant envoie encore « Prénom et nom » en un seul champ
        [$prenom, $nom] = isset($d['prenom']) ? [(string)$d['prenom'], (string)$d['nom']] : prenom_nom((string)$d['nom']);
        // Provenance (07/11/2026) : après un scan du QR code d'un emballage, le site transmet la campagne Matomo « qr-code » ; la demande
        // (et la fiche d'un nouveau client) est alors « reçue par » le QR code, ce qui la compte dans « D'où viennent les commandes »
        $via = is_string($d['via'] ?? null) ? trim($d['via']) : '';
        $qr = str_starts_with($via, 'qr-code');
        $canal = $qr ? 'qr' : 'site';
        $support = $qr ? trim(substr($via, 7)) : '';
        // 14/11/2026 : le lien de la page Instagram (madamecho.com/insta) et le bouton de l'accueil par lequel la demande a commencé sont notés
        // dans l'historique ; « Vous a connue par » prend la réponse du client, sinon ce que l'arrivée sur le site dit déjà
        $insta = str_starts_with($via, 'instagram');
        $depuis = is_string($d['depuis'] ?? null) ? trim($d['depuis']) : '';
        $connu = is_string($d['connu'] ?? null) && isset(CONNU_PAR[$d['connu']]) ? $d['connu'] : ($qr ? 'qr' : ($insta ? 'instagram' : ''));
        $cid = client_trouver_ou_creer((string)$d['email'], $prenom, $nom, (string)$d['telephone'], $canal, (bool)$d['pro'], (string)$d['societe'], $quand);
        $lignes = [];
        foreach ($d['lignes'] as $l) {
            $q = max(1, (int)$l['q']);
            $lignes[] = ['v' => $l['p'] . '|' . $l['c'], 'q' => $q, 'prix' => intdiv((int)$l['prix'], $q), 'plaque' => (string)($l['plaque'] ?? '')];
        }
        $type = $d['pro'] ? 'pro' : (($d['perso'] !== '' || !$lignes) ? 'perso' : 'carte');
        $id = commande_creer(['reference' => $d['ref'], 'client_id' => $cid, 'canal' => $canal, 'type' => $type,
                              'retrait_date' => $d['date'], 'retrait_moment' => (string)($d['plage'] ?? $d['moment'] ?? ''), 'lignes' => $lignes,
                              'perso' => (string)$d['perso'], 'besoin' => (string)$d['besoin'], 'recue_le' => $quand, 'connu' => $connu,
                              'origine' => (!empty($d['paiement']) ? 'Commande de la carte passée sur le site, paiement en ligne en cours' : 'Demande envoyée par le formulaire du site') . ($qr ? ' après un scan du QR code' . ($support !== '' ? ' (' . $support . ')' : '') : ($insta ? ' après une arrivée par le lien de la page Instagram' : ''))
                                         . ($depuis !== '' ? ', commencée par ' . $depuis : ''), 'auteur' => 'site']);
        $pdo->commit();
        return $id;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Les plages de retrait d'un jour, pour le formulaire du site (spécification 007, 27/11/2026) : ['plages' => [code => 'libre'|'complet'],
 * 'message' => phrase quand aucune plage n'est libre]. « complet » vaut pour une plage pleine, fermée par Youngmi ou touchée par une indispo :
 * jamais de nombre de clients ni de motif. Le dimanche et les jours fermés sont refusés par envoyer.php avant d'arriver ici.
 * Tant que l'espace de gestion n'est pas ouvert, toutes les plages sont libres : la base n'est jamais créée ici.
 */
function plages_pour_demande(string $date): array {
    $libres = ['plages' => array_fill_keys(array_map('strval', array_keys(PLAGES)), 'libre'), 'message' => ''];
    if (!date_valide($date) || !gestion_base_existante()) return $libres;
    $etats = plages_du_jour($date);
    $retenues = retenues_du_jour($date);   // les places retenues pendant un paiement en ligne comptent pour le site (spécification 006)
    $r = ['plages' => [], 'message' => ''];
    foreach ($etats as $c => $p) $r['plages'][(string)$c] = ($p['complete'] || $p['n'] + ($retenues[(string)$c] ?? 0) >= $p['maximum']) ? 'complet' : 'libre';
    if (!in_array('libre', $r['plages'], true)) $r['message'] = jour_complet_texte($date);
    return $r;
}
/** La phrase d'un jour sans plage libre : le jour demandé seulement, jamais toute la période d'une indispo ni son motif (audit n° 10,
 *  C10-13), sinon « toutes les plages sont complètes ». */
function jour_complet_texte(string $date): string {
    foreach (indispos_entre($date, $date) as $i) {
        if (indispo_journee($i)) return 'Madame Cho n\'est pas disponible le ' . date_fr($date, 'jour') . ' : choisissez une autre date.';
    }
    return 'Le ' . date_fr($date, 'jour') . ', toutes les plages de retrait sont complètes : choisissez une autre date.';
}
/** La plage choisie sur le site est-elle refusée ? La phrase pour le visiteur (sans motif), ou '' si elle est libre. */
function plage_refusee(string $date, string $plage): string {
    if (!isset(PLAGES[$plage])) return 'Choisissez une plage horaire de retrait.';
    $r = plages_pour_demande($date);
    if (($r['plages'][$plage] ?? 'libre') === 'libre') return '';
    if ($r['message'] !== '') return $r['message'];
    return 'Le ' . date_fr($date, 'jour') . ', la plage ' . mb_strtolower(PLAGES[$plage]) . ' est complète : choisissez-en une autre, ou une autre date.';
}

<?php
/*
 * Madame Cho : espace de gestion. Le rapprochement automatique avec Qonto (demande d'Olivier du 13/11/2026 : « que Youngmi ait le moins
 * à faire possible et que les rapprochements soient automatiques, que ça ne lui affiche que les problèmes potentiels »).
 *
 * Chaque passage (la nuit, à l'ouverture d'Aujourd'hui, de Plus et du Rapprochement, après chaque toucher) reprend les encaissements lus
 * dans Qonto (inc/qonto.php : comptes Qonto et compte du Crédit Mutuel relié) et les paiements notés dans la gestion, puis :
 *  - note « rapproché » tout seul quand une seule explication tient : un versement Tap to Pay égal aux ventes par carte notées la veille
 *    (le lundi : du vendredi au dimanche), un virement qui porte le numéro de la facture, ou le nom du client et exactement le montant ;
 *  - ne montre à Youngmi que ce qui cloche, avec un bouton pour chaque cas : carte jamais versée, virement jamais arrivé, moyen de
 *    paiement pas noté, versement Tap to Pay plus gros que les ventes notées, virement d'un client sans commande payée, virement du même
 *    montant qu'une commande (à confirmer d'un toucher, jamais noté tout seul).
 * Jamais : changer un paiement ou un moyen de paiement sans un toucher de Youngmi, créer une vente, écrire dans Qonto (R-24, FR-030).
 * Un rapprochement retiré par une personne n'est jamais refait tout seul (paires refusées). Les paiements notés avant le 7 novembre 2026
 * (début du suivi des paiements, audit n° 2) et les encaissements d'avant ne sont jamais signalés : ce qui se retrouve est noté, le reste
 * attend dans une liste repliée. Les espèces ne se cherchent pas dans Qonto.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

const RAPPROCHEMENT_SUIVI_DEPUIS = '2026-11-07';   // avant : jamais signalé (paiements notés sans date ni auteur, encaissements anciens)
const RAPPROCHEMENT_CARTE_DELAI = 2;               // jours ouvrés après le versement Tap to Pay attendu, avant de signaler une carte
const RAPPROCHEMENT_VIREMENT_DELAI = 7;            // jours après le paiement noté, avant de signaler un virement (ou « Autre ») pas arrivé
const RAPPROCHEMENT_MOYEN_DELAI = 2;               // jours avant de demander le moyen de paiement qui n'a pas été noté
const RAPPROCHEMENT_SILENCE = 3;                   // jours sans lecture complète de Qonto : on prévient, et l'absence n'est plus signalée
const RAPPROCHEMENT_RECENTS = 30;                  // jours des rapprochements faits tout seuls, montrés (et annulables) au Rapprochement

/* ---------- Jours ouvrés : un versement Tap to Pay arrive le jour ouvré suivant (calendrier des banques européennes) ---------- */
/** Le dimanche de Pâques (calcul grégorien de Meeus). */
function paques(int $a): string {
    $n = $a % 19; $b = intdiv($a, 100); $c = $a % 100; $d = intdiv($b, 4); $e = $b % 4; $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3);
    $h = (19 * $n + $b - $d - $g + 15) % 30; $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($n + 11 * $h + 22 * $l, 451);
    $x = $h + $l - 7 * $m + 114;
    return sprintf('%04d-%02d-%02d', $a, intdiv($x, 31), $x % 31 + 1);
}
/** Jour ouvré des banques : ni samedi, ni dimanche, ni 1er janvier, Vendredi saint, lundi de Pâques, 1er mai, 25 et 26 décembre
 *  (les autres jours fériés français sont ouvrés : le versement du 11 novembre 2026 est bien arrivé). */
function jour_ouvre(string $j): bool {
    $t = strtotime($j . ' 12:00:00');
    if ($t === false || (int)date('N', $t) >= 6 || in_array(date('m-d', $t), ['01-01', '05-01', '12-25', '12-26'], true)) return false;
    $ecart = (int)round(($t - (int)strtotime(paques((int)date('Y', $t)) . ' 12:00:00')) / 86400);
    return $ecart !== -2 && $ecart !== 1;
}
function jour_ouvre_apres(string $j, int $n = 1): string {
    static $memo = [];
    if (isset($memo[$j . '|' . $n])) return $memo[$j . '|' . $n];
    $t = (int)strtotime($j . ' 12:00:00');
    for ($reste = $n; $reste > 0;) { $t += 86400; if (jour_ouvre(date('Y-m-d', $t))) $reste--; }
    return $memo[$j . '|' . $n] = date('Y-m-d', $t);
}
function date_decalee(string $j, int $jours): string { return date('Y-m-d', (int)strtotime($j . ' 12:00:00') + $jours * 86400); }

/* ---------- Comparer ---------- */
/** La seule combinaison de montants qui fait la somme (à $tol centimes près) : ses clés ; null s'il n'y en a aucune ou plusieurs (16 montants au plus). */
function sous_ensemble_unique(array $montants, int $cible, int $tol = 0): ?array {
    $montants = array_filter($montants, fn($m) => $m > 0);
    if ($cible <= 0 || !$montants || count($montants) > 16) return null;
    arsort($montants);
    $cles = array_keys($montants); $v = array_values($montants); $n = count($v);
    $suffixe = array_fill(0, $n + 1, 0);
    for ($i = $n - 1; $i >= 0; $i--) $suffixe[$i] = $suffixe[$i + 1] + $v[$i];
    $trouves = []; $choix = [];
    $chercher = function (int $de, int $s) use (&$chercher, &$trouves, &$choix, $v, $n, $suffixe, $cible, $tol): void {
        for ($j = $de; $j < $n && count($trouves) < 2; $j++) {
            if ($s + $suffixe[$j] < $cible - $tol) return;   // même tout le reste ne suffit plus
            $s2 = $s + $v[$j];
            if ($s2 > $cible + $tol) continue;
            $choix[] = $j;
            if (abs($s2 - $cible) <= $tol) $trouves[] = $choix;
            if ($s2 < $cible + $tol) $chercher($j + 1, $s2);
            array_pop($choix);
        }
    };
    $chercher(0, 0);
    return count($trouves) === 1 ? array_map(fn($i) => $cles[$i], $trouves[0]) : null;
}
/** Un nom comparable, sans forme juridique (« S.A.S. LUNE ET CIE » → « LUNE ET CIE »), entouré d'espaces. */
function rapprochement_sans_forme(string $normalise): string {
    $s = preg_replace('/ (?:S A S U|S A S|S A R L|E U R L|SASU|SAS|SARL|EURL|SA|SNC|SCI|STE|SOCIETE|ETS|ETABLISSEMENTS)(?= )/', '', $normalise) ?? $normalise;
    return ' ' . trim(preg_replace('/ +/', ' ', $s) ?? $s) . ' ';
}
/** Des mots des libellés de virement qui ne sont jamais, seuls, le nom d'un client. */
const RAPPROCHEMENT_MOTS_VIDES = ['VIR', 'VIREMENT', 'INST', 'SEPA', 'FACTURE', 'FACT', 'PAIEMENT', 'REGLEMENT', 'REMISE', 'CHEQUE', 'CARTE', 'COMMANDE', 'MADAME', 'MONSIEUR'];
/**
 * Les façons dont un client peut apparaître dans le libellé d'un virement : [sûres, faibles]. Sûres : la société, le nom dans Qonto,
 * « prénom nom » et « nom prénom » ; faible : le nom de famille seul (5 lettres au moins), qui ne sert que s'il désigne un seul client.
 */
function rapprochement_noms_client(array $c): array {
    $forts = []; $faibles = [];
    $garder = function (array &$l, string $s, int $min): void {
        $x = rapprochement_sans_forme(qonto_normaliser($s));
        if (mb_strlen(trim($x)) >= $min && !in_array(trim($x), RAPPROCHEMENT_MOTS_VIDES, true)) $l[$x] = true;
    };
    if (trim((string)($c['societe'] ?? '')) !== '') $garder($forts, (string)$c['societe'], 3);
    if (trim((string)($c['qonto'] ?? '')) !== '') $garder($forts, (string)$c['qonto'], 3);
    $p = trim((string)($c['prenom'] ?? '')); $f = trim((string)($c['nom'] ?? ''));
    if ($p !== '' && $f !== '') { $garder($forts, $p . ' ' . $f, 5); $garder($forts, $f . ' ' . $p, 5); }
    if ($f !== '') { if (str_contains(trim(qonto_normaliser($f)), ' ')) $garder($forts, $f, 6); else $garder($faibles, $f, 5); }   // « Dupont Marie » écrit dans le nom : sûr
    return [array_keys($forts), array_keys(array_diff_key($faibles, $forts))];
}
function rapprochement_nomme(string $texte, array $noms): bool { foreach ($noms as $x) if (str_contains($texte, $x)) return true; return false; }
/**
 * Les numéros de facture lus dans un texte, sous une forme commune : « F-2026-001 », « F2026-001 », « F2026001 », « 2026-001 » donnent
 * tous « 2026-1 » (la série P- des factures aux particuliers garde sa lettre : « P2026-1 »). Une date (« 2026-11-12 ») n'en est pas une.
 */
function rapprochement_cles_facture(string $s): array {
    $t = qonto_normaliser($s);
    preg_match_all('/(?<= )([A-Z]{1,3})? ?((?:19|20)\d{2}) ?(\d{1,5})(?= )(?! \d{1,2} )/', $t, $m, PREG_SET_ORDER);
    $cles = [];
    foreach ($m as $x) $cles[(($x[1] ?? '') === 'P' ? 'P' : '') . $x[2] . '-' . (int)$x[3]] = true;
    return array_keys($cles);
}
function rapprochement_cle_facture(string $f): string { $c = rapprochement_cles_facture($f); return $c[0] ?? ''; }
/** Le moyen de paiement écrit dans l'historique (« Acompte reçu · Carte (Tap to Pay) · acompte 20 € ») ; '' s'il n'était pas noté. */
function rapprochement_moyen_texte(string $t): string {
    foreach (explode(' · ', $t) as $x) { $m = array_search(trim($x), MOYENS, true); if ($m !== false && $m !== '') return (string)$m; }
    return '';
}

/* ---------- Ce qu'il faut retrouver dans Qonto ---------- */
/** Le jour du paiement : noté par une personne depuis le 7 novembre 2026, sinon la remise, le retrait ou la demande. */
function rapprochement_jour_paiement(array $k): string {
    foreach (['paiement_le', 'remise_le', 'retrait_date', 'recue_le'] as $c) if (!empty($k[$c]) && date_valide(substr((string)$k[$c], 0, 10)) !== null) return substr((string)$k[$c], 0, 10);
    return aujourdhui();
}
/** Un paiement suivi (noté dans la gestion depuis le début du suivi) peut être signalé ; les autres jamais. */
function rapprochement_suivi(array $k): bool {
    return !empty($k['paiement_le']) && substr((string)$k['paiement_le'], 0, 10) >= RAPPROCHEMENT_SUIVI_DEPUIS;
}
/** Les montants à retrouver, dans l'ordre de qonto_ref : l'acompte d'abord quand il a été reçu à part (avec son jour et son moyen), puis le reste. */
function rapprochement_pieces(array $k): array {
    $jour = rapprochement_jour_paiement($k);
    $total = $k['prix_final'] !== null ? (int)$k['prix_final'] : (int)$k['total_indicatif'];
    $acompte = (int)$k['acompte'];
    $moyen = (string)$k['moyen'];
    if ($k['paiement'] === 'acompte_recu') return $acompte > 0 ? [['montant' => $acompte, 'jour' => $jour, 'moyen' => $moyen]] : [];
    if ($acompte > 0 && $acompte < $total) {
        $ev = ligne("SELECT le, texte FROM evenements WHERE commande_id = ? AND type = 'paiement' AND nouveau = 'acompte_recu' ORDER BY id DESC LIMIT 1", [(int)$k['id']]);
        if ($ev) return [['montant' => $acompte, 'jour' => substr((string)$ev['le'], 0, 10), 'moyen' => rapprochement_moyen_texte((string)$ev['texte'])],
                         ['montant' => $total - $acompte, 'jour' => $jour, 'moyen' => $moyen]];
    }
    return $total > 0 ? [['montant' => $total, 'jour' => $jour, 'moyen' => $moyen]] : [];
}
/** Les commandes avec leur client, comme commande(). */
function rapprochement_commandes(string $ou, array $p = []): array {
    return lignes("SELECT k.*, c.numero AS client_numero, c.nom AS client_nom, c.prenom AS client_prenom, c.societe AS client_societe, c.type AS client_type,
                          c.email AS client_email, c.telephone AS client_tel, c.statut AS client_statut, c.ville AS client_ville, c.contact AS client_contact, c.qonto AS client_qonto
                   FROM commandes k JOIN clients c ON c.id = k.client_id WHERE $ou ORDER BY k.id", $p);
}
/** Ce qui reste à payer d'une commande pas encore payée (l'acompte reçu déduit). */
function rapprochement_reste_du(array $k): int {
    $t = $k['prix_final'] !== null ? (int)$k['prix_final'] : (int)$k['total_indicatif'];
    return $t - ($k['paiement'] === 'acompte_recu' ? (int)$k['acompte'] : 0);
}
/** Les encaissements mis de côté par une personne (« vente sans commande », « ce n'est pas une vente ») : identifiant => centimes, motif, par, le. */
function rapprochement_ecartes(): array { $l = json_decode((string)parametre('qonto_ecartes', ''), true); return is_array($l) ? $l : []; }
function rapprochement_ecarter(string $op, int $centimes, string $motif): void {
    $l = rapprochement_ecartes();
    $l[$op] = ['centimes' => (int)($l[$op]['centimes'] ?? 0) + $centimes, 'motif' => $motif, 'par' => auteur(), 'le' => maintenant()];
    if (count($l) > 1000) $l = array_slice($l, -1000, null, true);
    parametre_ecrire('qonto_ecartes', json_encode($l, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    evenement('rapprochement', 'Encaissement Qonto mis de côté : ' . $motif . ' (' . euros($centimes) . ')', [], '', $op);
}
function rapprochement_remettre(string $op): void {
    $l = rapprochement_ecartes();
    if (!isset($l[$op])) return;
    unset($l[$op]);
    parametre_ecrire('qonto_ecartes', json_encode($l, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    evenement('rapprochement', 'Encaissement Qonto remis à vérifier', [], $op, '');
}
/** Un encaissement en quelques mots, tel que l'historique et l'écran le montrent ($libelle = false : sans le libellé de la banque, pour l'assistant). */
function rapprochement_op_court(array $o, bool $libelle = true): string {
    $j = date('d/m/Y', (int)strtotime((string)$o['jour'] . ' 12:00:00'));
    $m = euros((int)$o['centimes']);
    return match ($o['genre'] ?? '') {
        'tap' => 'Tap to Pay du ' . $j . ' (' . $m . ')',
        'cheque' => 'remise de chèques du ' . $j . ' (' . $m . ')',
        default => 'virement du ' . $j . ' (' . $m . ($libelle && $o['libelle'] !== '' ? ', ' . $o['libelle'] : '') . ')',
    };
}
/** Les encaissements d'une référence, en texte simple et sans libellé ('' s'ils ne sont plus dans la dernière lecture). */
function rapprochement_ref_clair(string $ref): string {
    $g = rapprochement_ops_gardees();
    $l = [];
    foreach (array_unique(explode(' + ', strtolower($ref))) as $x) if (isset($g[trim($x)])) $l[] = rapprochement_op_court($g[trim($x)], false);
    return implode(' + ', $l);
}
/** Les encaissements de la dernière lecture gardée, par identifiant (sans appeler Qonto). */
function rapprochement_ops_gardees(): array {
    static $o = null;
    if ($o !== null) return $o;
    $o = [];
    foreach (qonto_encaissements(false, false)[0] as $x) if (is_array($x) && isset($x['id'])) $o[(string)$x['id']] = $x;
    return $o;
}

/* ---------- Le passage ---------- */
/**
 * Rapproche ce qui est sûr et renvoie ce qui est à vérifier. $lire : relire Qonto si la lecture gardée a plus de 10 minutes
 * ($frais : tout de suite) ; sinon seulement la dernière lecture gardée (Aujourd'hui, Plus, après un toucher : aucun appel à Qonto).
 */
function rapprochement_passe(bool $lire = false, bool $frais = false, bool $neuf = false): array {
    static $memo = null;
    if (!$lire && !$neuf && $memo !== null) return $memo;   // une fois par page ; $neuf : après un changement
    $vide = ['relie' => false, 'frais' => false, 'lu' => 0, 'lu_ok' => 0, 'erreurs' => [], 'problemes' => [], 'anciens' => [], 'faits' => [], 'ops' => [], 'libres' => []];
    if (!qonto_relie()) return $memo = $vide;
    $lu = qonto_encaissements($frais, $lire);
    [$ops, $erreurs, $le] = $lu;
    $okTap = (bool)($lu[3] ?? !$erreurs);
    $luOk = (int)parametre('qonto_lu_ok', '0');
    $luOkTap = max($luOk, (int)parametre('qonto_lu_ok_tap', '0'));
    // L'absence d'un paiement ne se signale que sur une lecture complète et récente (sinon : « Qonto n'a pas pu être lu ») ; une carte
    // ne se cherche que dans les comptes Qonto : le compte du Crédit Mutuel illisible n'empêche pas de signaler une carte pas versée
    $recent = ['tout' => $le > 0 && !$erreurs && $luOk > time() - RAPPROCHEMENT_SILENCE * 86400,
               'tap' => $le > 0 && $okTap && $luOkTap > time() - RAPPROCHEMENT_SILENCE * 86400];
    $verrou = @fopen(gestion_dossier() . '/rapprochement.verrou', 'c');   // deux passages en même temps ne rapprochent pas deux fois
    if ($verrou) flock($verrou, LOCK_EX);
    try {
        $r = rapprochement_calculer($ops, $recent);
    } finally {
        if ($verrou) { flock($verrou, LOCK_UN); fclose($verrou); }
    }
    return $memo = ['relie' => true, 'frais' => $recent['tout'], 'lu' => $le, 'lu_ok' => $luOk, 'erreurs' => $erreurs] + $r;
}
/**
 * $recent : [« tap » => la dernière lecture des comptes Qonto est complète et récente, « tout » => celle de tous les comptes, Crédit Mutuel
 * compris]. Sans elle, l'absence d'un paiement n'est pas signalée (une carte se cherche dans Qonto, un virement aussi au Crédit Mutuel).
 */
function rapprochement_calculer(array $ops, array $recent): array {
    $auj = aujourdhui();
    $refus = rapprochement_refus();
    $ecartes = rapprochement_ecartes();
    $O = [];
    foreach ($ops as $o) {
        if (!is_array($o) || empty($o['id']) || !in_array($o['genre'] ?? '', ['tap', 'virement', 'cheque'], true)) continue;
        // Mis de côté : un virement « pas une vente » ne se rapproche plus ; la « vente sans commande » d'un versement Tap to Pay se déduit
        // à la fin, pour qu'une vente notée après coup s'y retrouve quand même
        $o['ecarte'] = (int)($ecartes[$o['id']]['centimes'] ?? 0);
        $o['reste'] = (int)$o['centimes'] - ($o['genre'] === 'tap' ? 0 : $o['ecarte']);
        $O[(string)$o['id']] = $o;
    }
    // Les paiements notés : ceux déjà rapprochés consomment leurs encaissements ; les autres sont à retrouver, montant par montant
    // (un acompte par carte et un solde en espèces : seul l'acompte se cherche)
    $libres = []; $K = [];
    foreach (rapprochement_commandes("k.paiement IN ('paye', 'acompte_recu') AND k.statut <> 'annulee' AND k.efface_le IS NULL") as $k) {
        $id = (int)$k['id'];
        $pieces = rapprochement_pieces_a_chercher($k);
        $ref = trim((string)$k['qonto_ref']);
        if ($ref !== '') {
            $x = array_map(fn($t) => strtolower(trim($t)), explode(' + ', $ref));
            $prendre = function (string $op, int $m) use (&$O): int { if (!isset($O[$op])) return 0; $v = min($m, max(0, $O[$op]['reste'])); $O[$op]['reste'] -= $v; return $v; };
            if (count($x) === count($pieces)) foreach ($pieces as $i => $p) $prendre($x[$i], $p['montant']);
            else { $m = array_sum(array_column($pieces, 'montant')); foreach ($x as $op) $m -= $prendre($op, $m); }   // ancienne référence, ou collée à la main
            continue;
        }
        if (!$pieces) continue;
        $k['cle_facture'] = rapprochement_cle_facture((string)$k['facture']);
        $K[$id] = $k;
        $suivi = rapprochement_suivi($k);
        foreach ($pieces as $i => $p) $libres[$id . '#' . $i] = $p + ['commande' => $id, 'n' => $i, 'suivi' => $suivi, 'op' => null, 'client' => (int)$k['client_id'], 'pro' => commande_est_pro($k)];
    }
    $lier = function (array $cles, string $op) use (&$libres, &$O): void {
        foreach ($cles as $c) { $libres[$c]['op'] = $op; $O[$op]['reste'] -= $libres[$c]['montant']; }
    };
    $libre = fn(array $p, string $op): bool => $p['op'] === null && !isset($refus[$p['commande'] . '|' . $op]);
    $dansLOrdre = function (array $l): array { uasort($l, fn($a, $b) => strcmp($a['le'], $b['le'])); return $l; };
    $montants = fn(array $l): array => array_map(fn($p) => $p['montant'], $l);

    // 1. Les versements Tap to Pay, du plus ancien au plus récent : les ventes par carte de la veille (le lundi : du vendredi au dimanche)
    $taps = $dansLOrdre(array_filter($O, fn($o) => $o['genre'] === 'tap'));
    foreach (array_keys($taps) as $op) {
        $c = $O[$op]['reste'];
        if ($c <= 0) continue;
        $J = (string)$O[$op]['jour'];
        $carte = array_filter($libres, fn($p) => $p['moyen'] === 'carte' && $libre($p, $op));
        if (!$carte) continue;
        $veille = array_filter($carte, fn($p) => jour_ouvre_apres($p['jour']) === $J);
        $sv = array_sum(array_column($veille, 'montant'));
        if ($veille && $sv === $c) { $lier(array_keys($veille), $op); continue; }
        // Une vente notée plus tôt, ou le matin du versement : la seule combinaison des ventes de la semaine qui fait le montant
        $fenetre = array_filter($carte, fn($p) => $p['jour'] >= date_decalee($J, -7) && $p['jour'] <= date_decalee($J, 1));
        $sub = sous_ensemble_unique($montants($fenetre), $c);
        if ($sub !== null) { $lier($sub, $op); continue; }
        // Les ventes de la veille n'expliquent qu'une partie : elles y sont ; le reste sera montré s'il ne s'explique pas
        if ($veille && $sv < $c) {
            $lier(array_keys($veille), $op);
            $sub = sous_ensemble_unique($montants(array_diff_key($fenetre, $veille)), $c - $sv);
            if ($sub !== null) $lier($sub, $op);
        }
    }

    // 2. Les virements et remises de chèques : d'abord le numéro de facture, puis le nom du client avec le montant exact ; jamais au seul montant
    $virs = $dansLOrdre(array_filter($O, fn($o) => $o['genre'] !== 'tap'));
    $noms = [];
    foreach (lignes("SELECT id, type, nom, prenom, societe, qonto FROM clients WHERE statut <> 'efface'") as $cl) { $n = rapprochement_noms_client($cl); if ($n[0] || $n[1]) $noms[(int)$cl['id']] = $n; }
    $nommes = []; $ambigu = [];
    foreach ($virs as $op => $o) {
        $nommes[$op] = []; $ambigu[$op] = false;
        if ($o['genre'] !== 'virement') continue;   // une remise de chèques ne porte que la référence de la banque
        $texte = rapprochement_sans_forme(qonto_normaliser($o['libelle'] . ' ' . $o['reference']));
        $forts = array_keys(array_filter($noms, fn($l) => rapprochement_nomme($texte, $l[0])));
        $faibles = $forts ? [] : array_keys(array_filter($noms, fn($l) => rapprochement_nomme($texte, $l[1])));
        $nommes[$op] = $forts ?: $faibles;
        // Plusieurs clients nommés (le même nom de famille, ou deux clients au même prénom et nom) : jamais noté tout seul (audit global du 13/11/2026, B-01)
        $ambigu[$op] = count($nommes[$op]) > 1;
    }
    $cand = fn(string $op): array => array_filter($libres, fn($p) => $p['moyen'] !== 'carte' && $libre($p, $op)
        && $p['jour'] >= date_decalee((string)$O[$op]['jour'], -180) && $p['jour'] <= date_decalee((string)$O[$op]['jour'], 45));
    $tol = fn(array $l): int => array_filter($l, fn($p) => $p['pro']) ? max(2, count(array_unique(array_column($l, 'commande')))) : 0;   // arrondis de TVA des factures pro
    $somme = fn(array $l): int => array_sum(array_column($l, 'montant'));
    // a) Le numéro de la facture : toutes les factures citées, ou une seule d'entre elles
    foreach ($virs as $op => $o) {
        if ($o['genre'] !== 'virement' || $O[$op]['reste'] <= 0 || !($cles = rapprochement_cles_facture($o['libelle'] . ' ' . $o['reference']))) continue;
        $c = $O[$op]['reste'];
        $fact = [];
        foreach ($cand($op) as $cle => $p) { $f = $K[$p['commande']]['cle_facture']; if ($f !== '' && in_array($f, $cles, true)) $fact[$f][$cle] = $p; }
        if (!$fact) continue;
        $toutes = [];
        foreach ($fact as $g) $toutes += $g;
        if (abs($somme($toutes) - $c) <= $tol($toutes)) { $lier(array_keys($toutes), $op); continue; }
        $une = array_filter($fact, fn($g) => abs($somme($g) - $c) <= $tol($g));
        if (count($une) === 1) $lier(array_keys(reset($une)), $op);
    }
    // b) Le nom du client, seulement quand une seule explication tient : une seule commande entière du montant, sinon un seul acompte ou
    //    solde du montant, sinon la seule combinaison de montants ; deux commandes du même montant restent à vérifier (audit global du 13/11/2026, B-01)
    foreach ($virs as $op => $o) {
        if (!$nommes[$op] || $ambigu[$op] || ($c = $O[$op]['reste']) <= 0) continue;
        $pc = array_filter($cand($op), fn($p) => in_array($p['client'], $nommes[$op], true));
        if (!$pc) continue;
        uasort($pc, fn($a, $b) => strcmp($a['jour'], $b['jour']) ?: $a['commande'] <=> $b['commande'] ?: $a['n'] <=> $b['n']);
        $parK = [];
        foreach ($pc as $cle => $p) $parK[$p['commande']][$cle] = $p;
        $entieres = array_values(array_filter($parK, fn($l) => abs($somme($l) - $c) <= $tol($l)));                 // une commande entière
        if ($entieres) { if (count($entieres) === 1) $lier(array_keys($entieres[0]), $op); continue; }
        $parts = array_filter($pc, fn($p) => abs($p['montant'] - $c) <= $tol([$p]));                                // l'acompte, ou le solde
        if ($parts) { if (count($parts) === 1) $lier(array_keys($parts), $op); continue; }
        if (($sub = sous_ensemble_unique($montants($pc), $c, $tol($pc) ? 2 : 0)) !== null) $lier($sub, $op);      // plusieurs commandes réglées d'un coup
    }
    // Ce qui reste à expliquer de chaque encaissement, une fois déduit ce qui a été mis de côté
    foreach ($O as $op => $o) $O[$op]['net'] = max(0, $o['reste'] - ($o['genre'] === 'tap' ? $o['ecarte'] : 0));

    // 3. Ce qui est à vérifier
    $pb = [];
    $suiviOp = fn(array $o): bool => (string)$o['jour'] >= RAPPROCHEMENT_SUIVI_DEPUIS;
    $candidatsPris = [];   // commandes déjà proposées pour un encaissement : pas montrées une deuxième fois à part
    // Les versements Tap to Pay plus gros que les ventes par carte notées
    foreach ($taps as $op => $_) {
        $o = $O[$op];
        if ($o['net'] <= 0 || !$suiviOp($o)) continue;
        $J = (string)$o['jour'];
        $memes = array_filter($libres, fn($p) => $p['moyen'] === 'carte' && $libre($p, $op) && $p['montant'] === $o['net'] && $p['jour'] >= date_decalee($J, -10) && $p['jour'] <= date_decalee($J, 7));
        $nonPayees = array_values(array_filter(rapprochement_commandes("k.paiement IN ('aucun', 'acompte_demande', 'acompte_recu') AND k.statut IN ('confirmee', 'remise') AND k.efface_le IS NULL
                                                                        AND COALESCE(substr(k.remise_le, 1, 10), k.retrait_date) BETWEEN ? AND ?", [date_decalee($J, -5), $J]),
                                               fn($k) => rapprochement_reste_du($k) === $o['net']));
        foreach ($memes as $p) $candidatsPris[$p['commande']] = true;
        $pb[] = ['type' => 'versement', 'cle' => 'versement-' . $op, 'jour' => $J, 'op' => $o, 'montant' => $o['net'], 'trouve' => (int)$o['centimes'] - $o['reste'],
                 'payees' => array_slice(array_values(array_unique(array_map(fn($p) => $p['commande'], $memes))), 0, 3), 'non_payees' => array_slice($nonPayees, 0, 3)];
    }
    foreach ($virs as $op => $_) {
        $o = $O[$op];
        if ($o['net'] <= 2 || !$suiviOp($o)) continue;
        $J = (string)$o['jour'];
        // Un virement qui nomme un client (le même nom de famille que d'autres, ou un rapprochement retiré) avec un paiement noté du même montant : à confirmer
        if ($nommes[$op]) {
            $siens = array_filter($libres, fn($p) => $p['moyen'] !== 'carte' && $libre($p, $op) && in_array($p['client'], $nommes[$op], true) && abs($p['montant'] - $o['net']) <= ($p['pro'] ? 2 : 0));
            if ($siens) {
                $l = array_slice(array_values(array_unique(array_map(fn($p) => $p['commande'], $siens))), 0, 3);
                foreach ($l as $kid) $candidatsPris[$kid] = true;
                $pb[] = ['type' => 'confirmer', 'cle' => 'confirmer-' . $op, 'jour' => $J, 'op' => $o, 'montant' => $o['net'], 'payees' => $l, 'groupe' => []];
                continue;
            }
        }
        // Un virement qui nomme un client, ou porte le numéro d'une facture, sans paiement noté qui corresponde : ses commandes pas encore notées payées
        $cles = $o['genre'] === 'virement' ? rapprochement_cles_facture($o['libelle'] . ' ' . $o['reference']) : [];
        $np = $nommes[$op] || $cles ? array_filter(rapprochement_commandes("k.paiement IN ('aucun', 'acompte_demande', 'acompte_recu') AND k.statut NOT IN ('annulee', 'sans_suite') AND k.efface_le IS NULL
                                                         AND COALESCE(substr(k.remise_le, 1, 10), k.retrait_date, substr(k.recue_le, 1, 10)) BETWEEN ? AND ?", [date_decalee($J, -180), date_decalee($J, 45)]),
                                                   fn($k) => in_array((int)$k['client_id'], $nommes[$op], true) || ($cles && in_array(rapprochement_cle_facture((string)$k['facture']), $cles, true))) : [];
        if ($nommes[$op] || $np) {
            $groupes = [];
            foreach ($np as $k) if (abs(rapprochement_reste_du($k) - $o['net']) <= (commande_est_pro($k) ? 2 : 0)) $groupes[] = [(int)$k['id']];
            if (!$groupes && count($np) > 1 && ($sub = sous_ensemble_unique(array_combine(array_map(fn($k) => (int)$k['id'], $np), array_map(fn($k) => rapprochement_reste_du($k), $np)), $o['net'], 2)) !== null) $groupes[] = array_map('intval', $sub);
            $pb[] = ['type' => 'client', 'cle' => 'client-' . $op, 'jour' => $J, 'op' => $o, 'montant' => $o['net'], 'clients' => $nommes[$op],
                     'groupes' => array_slice($groupes, 0, 3), 'commandes' => array_column($np, null, 'id')];
            continue;
        }
        // Au seul montant : une commande payée par virement (ou « Autre », ou moyen pas noté) du même montant, à confirmer d'un toucher ;
        // une remise de plusieurs chèques : la seule combinaison des paiements « Autre » qui fait le montant
        if ($o['net'] !== (int)$o['centimes'] - $o['ecarte']) continue;   // déjà en partie expliqué
        $memes = array_filter($libres, fn($p) => $p['suivi'] && $p['moyen'] !== 'carte' && $libre($p, $op) && $p['montant'] === $o['net'] && $p['jour'] >= date_decalee($J, -30) && $p['jour'] <= date_decalee($J, 7));
        $groupe = [];
        if (!$memes && $o['genre'] === 'cheque') {
            $pool = array_filter($libres, fn($p) => $p['suivi'] && in_array($p['moyen'], ['autre', ''], true) && $libre($p, $op) && $p['jour'] >= date_decalee($J, -30) && $p['jour'] <= $J);
            $sub = count($pool) > 1 ? sous_ensemble_unique($montants($pool), $o['net']) : null;
            if ($sub !== null && count($sub) > 1) $groupe = array_values(array_unique(array_map(fn($c) => $libres[$c]['commande'], $sub)));
        }
        if (!$memes && !$groupe) continue;
        $l = $groupe ?: array_slice(array_values(array_unique(array_map(fn($p) => $p['commande'], $memes))), 0, 3);
        foreach ($l as $kid) $candidatsPris[$kid] = true;
        $pb[] = ['type' => 'confirmer', 'cle' => 'confirmer-' . $op, 'jour' => $J, 'op' => $o, 'montant' => $o['net'], 'payees' => $groupe ? [] : $l, 'groupe' => $groupe];
    }
    // Les paiements suivis pas retrouvés, une fois le délai passé
    $parK = [];
    foreach ($libres as $p) if ($p['op'] === null && $p['suivi']) $parK[$p['commande']][] = $p;
    $anciens = [];
    foreach ($libres as $p) if (!$p['suivi'] && !isset($anciens[$p['commande']]) && $p['op'] === null) $anciens[$p['commande']] = $K[$p['commande']];
    foreach ($parK as $kid => $ps) {
        if (isset($candidatsPris[$kid])) continue;
        $k = $K[$kid];
        $montant = array_sum(array_column($ps, 'montant'));
        $jour = $ps[0]['jour'];
        if (in_array('', array_column($ps, 'moyen'), true)) {
            if ($auj > date_decalee($jour, RAPPROCHEMENT_MOYEN_DELAI)) $pb[] = ['type' => 'moyen', 'cle' => 'moyen-' . $kid, 'jour' => $jour, 'commande' => $k, 'montant' => $montant];
            continue;
        }
        $cartes = array_values(array_filter($ps, fn($p) => $p['moyen'] === 'carte'));
        if ($cartes) {
            if (!$recent['tap']) continue;
            $j = $cartes[0]['jour'];
            if ($auj > jour_ouvre_apres(jour_ouvre_apres($j), RAPPROCHEMENT_CARTE_DELAI))
                $pb[] = ['type' => 'carte', 'cle' => 'carte-' . $kid, 'jour' => $j, 'commande' => $k, 'montant' => $montant,
                         'choix' => array_slice(array_keys(array_filter($taps, fn($o) => $O[$o['id']]['reste'] > 0 && (string)$o['jour'] >= date_decalee($j, -7) && (string)$o['jour'] <= date_decalee($j, 10))), 0, 6)];
        } elseif ($recent['tout'] && $auj > date_decalee($jour, RAPPROCHEMENT_VIREMENT_DELAI)) {
            $pb[] = ['type' => 'virement', 'cle' => 'virement-' . $kid, 'jour' => $jour, 'commande' => $k, 'montant' => $montant,
                     'choix' => array_slice(array_keys(array_filter($virs, fn($o) => $O[$o['id']]['net'] >= $montant - 2 && (string)$o['jour'] >= date_decalee($jour, -30) && (string)$o['jour'] <= date_decalee($jour, 30))), 0, 6)];
        }
    }
    usort($pb, fn($a, $b) => strcmp($a['jour'], $b['jour']) ?: strcmp($a['cle'], $b['cle']));

    // 4. Noter ce qui est sûr : une commande dont tous les montants sont retrouvés
    $faits = [];
    $parCommande = [];
    foreach ($libres as $p) $parCommande[$p['commande']][$p['n']] = $p['op'];
    foreach ($parCommande as $kid => $refs) {
        if (in_array(null, $refs, true)) continue;
        ksort($refs);
        $detail = implode(' + ', array_map(fn($op) => rapprochement_op_court($O[$op], false), array_values(array_unique($refs))));   // l'historique garde le jour et le montant, pas le libellé de la banque
        if (commande_rapprocher_auto((int)$kid, array_values($refs), $detail)) $faits[] = (int)$kid;
    }
    return ['problemes' => $pb, 'anciens' => array_values($anciens), 'faits' => $faits, 'ops' => $O, 'nommes' => $nommes, 'libres' => $libres];
}
/** Les montants d'une commande qui se cherchent dans Qonto : tout sauf les espèces. */
function rapprochement_pieces_a_chercher(array $k): array {
    return array_values(array_filter(rapprochement_pieces($k), fn($p) => $p['moyen'] !== 'especes' && $p['montant'] > 0));
}
/** La référence d'une commande quand une personne choisit l'encaissement : chaque montant garde l'encaissement déjà retrouvé, les autres prennent celui-ci. */
function rapprochement_ref_pour(int $kid, string $op, array $r): string {
    $refs = [];
    foreach ($r['libres'] ?? [] as $p) if ((int)$p['commande'] === $kid) $refs[(int)$p['n']] = $p['op'] ?? $op;
    ksort($refs);
    return $refs ? implode(' + ', $refs) : $op;
}
/** Les encaissements d'une référence, en quelques mots (l'historique de la commande). */
function rapprochement_ref_detail(string $ref, array $O): string {
    $l = [];
    foreach (array_unique(explode(' + ', $ref)) as $x) if (isset($O[$x])) $l[] = rapprochement_op_court($O[$x], false);
    return implode(' + ', $l);
}
/** Le nombre de paiements à vérifier (pastille de Plus, Aujourd'hui) ; 0 si Qonto n'est pas relié ou si le passage échoue. */
function rapprochement_a_verifier(): int {
    try { return count(rapprochement_passe()['problemes']); }
    catch (\Throwable $e) { error_log('madamecho gestion rapprochement : ' . $e->getMessage()); return 0; }
}
/** La tâche de nuit : une lecture fraîche de Qonto, puis le passage. */
function rapprochement_nuit(): array {
    if (!qonto_relie()) return ['relie' => false];
    $r = rapprochement_passe(true, true);
    return ['faits' => count($r['faits']), 'a_verifier' => count($r['problemes']), 'erreurs' => $r['erreurs']];
}

/* ---------- L'écran : Plus › Rapprochement Qonto ---------- */
function rapprochement_titre(array $p): string {
    return match ($p['type']) {
        'carte' => 'Carte pas retrouvée',
        'virement' => $p['commande']['moyen'] === 'virement' ? 'Virement pas arrivé' : 'Paiement pas retrouvé',
        'moyen' => 'Moyen de paiement pas noté',
        'confirmer' => $p['op']['genre'] === 'cheque' ? 'Chèques à confirmer' : 'Virement à confirmer',
        'versement' => 'Vente par carte pas notée',
        'client' => 'Virement sans paiement noté',
        default => 'À vérifier',
    };
}
/** La ligne d'une commande : référence, client, montant. */
function rapprochement_ligne_commande(array $k, int $montant): string {
    return '<span class="l-cmd"><a href="' . h(u('commande', ['id' => $k['id']])) . '">' . h($k['reference']) . '</a> · <b translate="no">' . h(commande_client_nom($k)) . '</b> · <b>' . h(euros($montant)) . '</b></span>';
}
function rapprochement_ligne_op(array $o): string { return '<span class="l-op">' . h(ucfirst(rapprochement_op_court($o))) . '</span>'; }
/** Ce qu'une carte « à vérifier » dit en une ligne (Aujourd'hui). */
function rapprochement_sous_titre(array $p): string {
    if (isset($p['commande'])) return '<span>' . h($p['commande']['reference']) . '</span> · <span translate="no">' . h(commande_client_nom($p['commande'])) . '</span>';
    return rapprochement_ligne_op($p['op']);
}
function rapprochement_cache_champs(array $v): string {
    $s = '';
    foreach ($v as $n => $x) foreach ((array)$x as $y) $s .= '<input type="hidden" name="' . h($n) . (is_array($x) ? '[]' : '') . '" value="' . h((string)$y) . '">';
    return $s;
}
/** « Vérifiée autrement » : quelques mots, et le paiement n'est plus cherché dans Qonto. */
function rapprochement_hors_html(array $k): string {
    return '<details class="replie"><summary>Vérifiée autrement</summary>'
        . formulaire('hors', rapprochement_cache_champs(['commande' => (int)$k['id']])
            . champ('motif', 'Comment le paiement a-t-il été vérifié ?', '', ['maxlength' => '200', 'required' => true, 'autocomplete' => 'off'], 'Par exemple : payé sur l\'ancien terminal, chèque encaissé au Crédit Mutuel avant la liaison.')
            . bouton('Noter vérifiée hors Qonto', 'secondaire')) . '</details>';
}
/** Choisir soi-même l'encaissement (versement ou virement) qui contient ce paiement. */
function rapprochement_choix_html(array $k, array $ids, array $O, string $titre): string {
    if (!$ids) return '';
    $s = '';
    foreach ($ids as $i => $op) {
        $o = $O[$op];
        $reste = (int)$o['net'] > 0 && (int)$o['net'] !== (int)$o['centimes'] ? ' · <span>' . h('pas expliqué : ' . euros((int)$o['net'])) . '</span>' : '';
        $s .= '<label class="case ligne-rapprochement"><input type="radio" name="op" value="' . h($op) . '"' . ($i === 0 ? ' required' : '') . '><span>' . rapprochement_ligne_op($o) . $reste . '</span></label>';
    }
    return '<details class="replie"><summary>' . h($titre) . '</summary>'
        . formulaire('lier', rapprochement_cache_champs(['commande' => (int)$k['id']]) . '<div class="liste-cases">' . $s . '</div>' . bouton('Noter rapprochée', 'secondaire')) . '</details>';
}
function rapprochement_carte(array $p, array $r): string {
    $O = $r['ops'];
    $t = '<p class="l1"><b>' . h(rapprochement_titre($p)) . '</b></p>';
    $a = '';
    switch ($p['type']) {
        case 'carte':
            $k = $p['commande'];
            $t .= '<p class="l2">' . rapprochement_ligne_commande($k, $p['montant']) . ' · <span>' . h('Payée le ' . date_fr($p['jour'])) . '</span></p>'
                . '<p class="aide">' . h('Aucun versement Tap to Pay de Qonto ne contient ce paiement par carte.') . '</p>';
            $a = formulaire('moyen', rapprochement_cache_champs(['commande' => (int)$k['id'], 'moyen' => 'especes']) . bouton('C\'était en espèces', 'secondaire'))
               . rapprochement_choix_html($k, $p['choix'], $O, 'Il est dans un versement Tap to Pay') . rapprochement_hors_html($k);
            break;
        case 'virement':
            $k = $p['commande'];
            $t .= '<p class="l2">' . rapprochement_ligne_commande($k, $p['montant']) . ' · <span>' . h('Payée le ' . date_fr($p['jour'])) . '</span></p>'
                . '<p class="aide">' . h($k['moyen'] === 'virement' ? 'Noté payé par virement il y a plus de 7 jours, mais aucun virement reçu dans Qonto ne correspond.'
                                                               : 'Noté payé (moyen « Autre ») il y a plus de 7 jours, mais rien dans Qonto ne correspond.') . '</p>';
            $a = rapprochement_choix_html($k, $p['choix'], $O, 'Il est dans Qonto') . rapprochement_hors_html($k);
            break;
        case 'moyen':
            $k = $p['commande'];
            $t .= '<p class="l2">' . rapprochement_ligne_commande($k, $p['montant']) . ' · <span>' . h('Payée le ' . date_fr($p['jour'])) . '</span></p>'
                . '<p class="aide">' . h('Comment a-t-elle été payée ? La gestion la retrouvera ensuite toute seule dans Qonto.') . '</p>';
            $b = '';
            foreach (['carte', 'virement', 'especes', 'autre'] as $m) $b .= bouton(h(MOYENS[$m]), 'secondaire', ['name' => 'moyen', 'value' => $m]);
            $a = formulaire('moyen', rapprochement_cache_champs(['commande' => (int)$k['id']]) . '<div class="boutons-moyen">' . $b . '</div>');
            break;
        case 'confirmer':
            $o = $p['op'];
            $t .= '<p class="l2">' . rapprochement_ligne_op($o) . '</p>';
            if ($p['groupe']) {   // une remise de plusieurs chèques : les commandes dont les paiements font exactement la somme
                $t .= '<p class="aide">' . h('Ces commandes payées font exactement ce montant. Est-ce que ce sont leurs chèques ?') . '</p>';
                $lignes = '';
                foreach ($p['groupe'] as $kid) if ($k = commande((int)$kid)) $lignes .= '<span>' . rapprochement_ligne_commande($k, rapprochement_montant($k)) . '</span>';
                $a .= '<div class="rappro-choix">' . $lignes . formulaire('confirmer', rapprochement_cache_champs(['op' => $o['id'], 'commandes' => array_map('intval', $p['groupe'])])
                    . bouton(icone('valider') . 'Oui, ce sont leurs chèques', 'secondaire')) . '</div>';
            } else {
                $t .= '<p class="aide">' . h('Même montant qu\'une commande payée. Est-ce son paiement ?') . '</p>';
                foreach ($p['payees'] as $kid) {
                    if (!($k = commande((int)$kid))) continue;
                    $a .= '<div class="rappro-choix"><span>' . rapprochement_ligne_commande($k, $p['montant']) . '</span>'
                        . formulaire('confirmer', rapprochement_cache_champs(['commande' => (int)$kid, 'op' => $o['id']]) . bouton(icone('valider') . 'Oui, c\'est son paiement', 'secondaire')) . '</div>';
                }
            }
            $a .= formulaire('refuser', rapprochement_cache_champs(['op' => $o['id'], 'commandes' => array_map('intval', $p['groupe'] ?: $p['payees'])]) . bouton('Non, aucune', 'discret'));
            break;
        case 'versement':
            $o = $p['op'];
            $t .= '<p class="l2">' . rapprochement_ligne_op($o) . '</p>'
                . '<p class="l2">' . ($p['trouve'] > 0 ? '<span>' . h('Retrouvé : ' . euros($p['trouve'])) . '</span> · ' : '') . '<b>' . h('Pas expliqué : ' . euros($p['montant'])) . '</b></p>'
                . '<p class="aide">' . h('Ce versement contient une vente par carte qui n\'est pas notée payée dans la gestion (les ventes de la veille ; le lundi, celles du vendredi au dimanche).') . '</p>';
            foreach ($p['non_payees'] as $k)
                $a .= '<div class="rappro-choix"><span>' . rapprochement_ligne_commande($k, rapprochement_reste_du($k)) . ' · <span>' . h(STATUTS_COURTS[$k['statut']] ?? '') . '</span></span>'
                    . formulaire('payee_carte', rapprochement_cache_champs(['commande' => (int)$k['id'], 'op' => $o['id']]) . bouton('Noter payée par carte', 'secondaire')) . '</div>';
            foreach ($p['payees'] as $kid) {
                if (!($k = commande((int)$kid))) continue;
                $a .= '<div class="rappro-choix"><span>' . rapprochement_ligne_commande($k, $p['montant']) . '</span>'
                    . formulaire('confirmer', rapprochement_cache_champs(['commande' => (int)$kid, 'op' => $o['id']]) . bouton('C\'est cette commande', 'secondaire')) . '</div>';
            }
            $a .= formulaire('sans_commande', rapprochement_cache_champs(['op' => $o['id']]) . bouton('Vente sans commande', 'discret')
                . '<p class="aide">' . h('Par exemple une vente au marché, jamais saisie dans la gestion.') . '</p>');
            break;
        case 'client':
            $o = $p['op'];
            $noms = [];
            foreach ($p['clients'] as $cid) if ($cl = client((int)$cid)) $noms[] = '<a href="' . h(u('client', ['id' => $cid])) . '" translate="no">' . h(client_nom($cl)) . '</a>';
            $t .= '<p class="l2">' . rapprochement_ligne_op($o) . '</p>' . ($noms ? '<p class="l2">' . implode(', ', $noms) . '</p>' : '');
            if ($p['groupes']) {
                $t .= '<p class="aide">' . h('Des commandes de ce montant ne sont pas notées payées :') . '</p>';
                foreach ($p['groupes'] as $g) {
                    $lignes = '';
                    foreach ($g as $kid) if (isset($p['commandes'][$kid])) $lignes .= '<span>' . rapprochement_ligne_commande($p['commandes'][$kid], rapprochement_reste_du($p['commandes'][$kid])) . '</span>';
                    $a .= '<div class="rappro-choix">' . $lignes . formulaire('payees_virement', rapprochement_cache_champs(['op' => $o['id'], 'commandes' => $g])
                        . bouton(icone('valider') . (count($g) > 1 ? 'Noter payées et rapprochées' : 'Noter payée et rapprochée'), 'secondaire')) . '</div>';
                }
            } else $t .= '<p class="aide">' . h('Aucune commande en attente de paiement n\'a ce montant.') . '</p>';
            $a .= formulaire('pas_une_vente', rapprochement_cache_champs(['op' => $o['id']]) . bouton('Ce n\'est pas une vente', 'discret'));
            break;
    }
    return '<article class="rappro-pb" id="pb-' . h($p['cle']) . '">' . $t . '<div class="rappro-actions">' . $a . '</div></article>';
}
/** L'état de la lecture de Qonto, en tête de l'écran. */
function rapprochement_etat_html(?array $r): string {
    if (!$r) return '<p class="erreur">' . h('Le rapprochement automatique n\'a pas pu se faire : réessayez dans un moment.') . '</p>';
    $s = '';
    foreach ($r['erreurs'] as $e) {   // « Madame CHO (Crédit Mutuel) : message » : le nom du compte tel quel, le message traduit
        $i = strpos($e, ') : ');
        $s .= '<p class="erreur"><span>' . h('Qonto n\'a pas pu être lu en entier :') . '</span> '
            . ($i !== false ? '<span translate="no">' . h(substr($e, 0, $i + 1)) . '</span> : <span>' . h(substr($e, $i + 4)) . '</span>' : '<span>' . h($e) . '</span>') . '</p>';
    }
    if ($r['lu_ok'] > 0 && $r['lu_ok'] < time() - RAPPROCHEMENT_SILENCE * 86400)
        $s .= '<p class="erreur">' . h('Qonto n\'a pas pu être lu en entier depuis plus de 3 jours : les paiements pas retrouvés ne sont plus signalés. Reliez Qonto à nouveau dans Paramètres › Qonto.') . '</p>';
    $lu = $r['lu'] > 0 ? '<span>' . h('Dernière lecture de Qonto :') . '</span> <span>' . h(date_fr(date('Y-m-d H:i:s', (int)$r['lu']), 'relative')) . '</span>' : '<span>' . h('Qonto pas encore lu.') . '</span>';
    return $s . '<div class="rappro-etat"><p class="aide">' . $lu . '</p>' . formulaire('relire', bouton(icone('relire') . 'Relire Qonto', 'discret')) . '</div>';
}
/** L'ancien écran, à la main : cocher les paiements, choisir l'encaissement lu dans Qonto ou coller sa référence. */
function rapprochement_manuel(array $l): string {
    if (!$l) return '<p class="ok">' . h('Tous les paiements par carte et virement sont retrouvés dans Qonto.') . '</p>';
    $rows = '';
    foreach ($l as $k) {
        $rows .= '<label class="case ligne-rapprochement"><input type="checkbox" name="ids[]" value="' . (int)$k['id'] . '"><span>'
               . '<b>' . h(euros(rapprochement_montant($k))) . '</b> · ' . h(PAIEMENTS[$k['paiement']] ?? '') . ' · ' . h(MOYENS[$k['moyen']] ?? '')
               . '<br><span class="petit"><span translate="no">' . h(commande_client_nom($k)) . '</span> · <a href="' . h(u('commande', ['id' => $k['id']])) . '">' . h($k['reference']) . '</a> · '
               . h(date_fr($k['paiement_le'] ?: ($k['remise_le'] ?: $k['maj_le']), 'heure')) . '</span></span></label>';
    }
    return formulaire('rapprocher', '<div class="liste-cases">' . $rows . '</div>'
        . choix_encaissements_qonto()
        . champ('qonto_ref', qonto_relie() ? 'Ou référence de l\'opération, collée à la main' : 'Référence de l\'opération dans Qonto', '', ['maxlength' => '120', 'autocomplete' => 'off', 'required' => !qonto_relie()],
            'Cochez le ou les paiements que cette opération Qonto regroupe (un versement Tap to Pay peut en réunir plusieurs), puis collez la référence de l\'opération. Un nom ou un montant seuls ne suffisent pas.')
        . bouton('Noter rapprochés', 'principal'));
}
function page_rapprochement(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        rediriger('rapprochement', [], rapprochement_action(texte('_action', 40)));
    }
    if (!qonto_relie()) {   // sans Qonto relié : à la main, comme avant
        $l = a_rapprocher();
        $c = titre_page('Rapprochement Qonto', 'Payé (constaté par vous) et retrouvé dans Qonto sont deux choses séparées', '', u('plus'))
           . '<div class="tuiles">' . tuile('À rapprocher', (string)count($l), euros(array_sum(array_map('MadameCho\\Gestion\\rapprochement_montant', $l)))) . '</div>'
           . bloc('Paiements pas encore retrouvés dans Qonto', rapprochement_manuel($l))
           . '<p class="aide">' . h('Les espèces ne passent pas par Qonto : elles ne sont pas dans cette liste.') . ' ' . h('Si un paiement change, son rapprochement est à refaire.') . '</p>';
        mise_en_page('Rapprochement Qonto', $c, 'plus');
    }
    $r = null;
    try { $r = rapprochement_passe(true); }
    catch (\Throwable $e) { error_log('madamecho gestion rapprochement : ' . $e->getMessage()); }
    $c = titre_page('Rapprochement Qonto', h('La gestion retrouve toute seule les paiements dans Qonto : ici, seulement ce qui est à vérifier.'), '', u('plus'))
       . rapprochement_etat_html($r);
    $pb = $r['problemes'] ?? [];
    $c .= bloc('À vérifier' . ($pb ? ' <span class="compte">' . count($pb) . '</span>' : ''),
               $pb ? '<div class="liste-rappro">' . implode('', array_map(fn($p) => rapprochement_carte($p, $r), $pb)) . '</div>'
                   : '<p class="ok">' . h('Rien à vérifier : tous les paiements suivis sont retrouvés dans Qonto.') . '</p>', '', 'a-verifier');
    // Retrouvés tout seuls ces 30 derniers jours : à annuler d'un toucher si l'un est faux
    $faits = rapprochement_commandes("k.qonto_par = ? AND k.qonto_le >= ? AND k.efface_le IS NULL", [RAPPROCHEMENT_PAR, date('Y-m-d H:i:s', time() - RAPPROCHEMENT_RECENTS * 86400)]);
    usort($faits, fn($a, $b) => strcmp((string)$b['qonto_le'], (string)$a['qonto_le']));
    if ($faits) {
        $s = '';
        foreach (array_slice($faits, 0, 60) as $k)
            $s .= '<li class="rappro-fait"><span>' . rapprochement_ligne_commande($k, rapprochement_montant($k)) . '<br><span class="petit">' . rapprochement_ref_texte((string)$k['qonto_ref']) . '</span></span>'
                . formulaire('annuler', rapprochement_cache_champs(['commande' => (int)$k['id']]) . bouton('Annuler', 'discret'), ['data-confirmer' => 'Annuler ce rapprochement ? La gestion ne le refera pas toute seule.']) . '</li>';
        $c .= '<details class="replie" id="faits"><summary>' . h('Retrouvés tout seuls ces 30 derniers jours (' . count($faits) . ')') . '</summary><ul class="liste-rappro-faits">' . $s . '</ul></details>';
    }
    // Payés avant le suivi : jamais signalés
    $an = $r['anciens'] ?? [];
    if ($an) {
        $s = '';
        foreach (array_slice($an, 0, 150) as $k) $s .= '<li>' . rapprochement_ligne_commande($k, rapprochement_montant($k)) . ' · <span>' . h(date_fr(rapprochement_jour_paiement($k), 'longue')) . '</span></li>';
        $c .= '<details class="replie" id="anciens"><summary>' . h('Payés avant le suivi automatique (' . count($an) . ')') . '</summary>'
            . '<p class="aide">' . h('Notés payés avant le 7 novembre 2026 : la gestion les cherche dans Qonto et note ceux qu\'elle retrouve, sans jamais signaler les autres. Rien à faire.') . '</p><ul class="liste-simple">' . $s . '</ul></details>';
    }
    // Mis de côté par une personne : à remettre si c'était une erreur
    $ec = rapprochement_ecartes();
    if ($ec) {
        $s = '';
        $gardees = rapprochement_ops_gardees();
        foreach (array_reverse(array_slice($ec, -40, null, true), true) as $op => $e)
            $s .= '<li class="rappro-fait"><span>' . (isset($gardees[$op]) ? rapprochement_ligne_op($gardees[$op]) : '<span>' . h('Encaissement Qonto') . '</span>') . ' · <span>' . h((string)$e['motif']) . '</span>'
                . '<br><span class="petit"><span translate="no">' . h((string)$e['par']) . '</span> · ' . h(date_fr((string)$e['le'], 'heure')) . '</span></span>'
                . formulaire('remettre', rapprochement_cache_champs(['op' => (string)$op]) . bouton('Remettre à vérifier', 'discret')) . '</li>';
        $c .= '<details class="replie" id="ecartes"><summary>' . h('Mis de côté (' . count($ec) . ')') . '</summary><ul class="liste-rappro-faits">' . $s . '</ul></details>';
    }
    $l = a_rapprocher();
    if ($l) $c .= '<details class="replie" id="main"><summary>' . h('Rapprocher à la main') . '</summary><p class="aide">' . h('Rarement utile : la gestion le fait toute seule. Pour un cas qu\'elle ne sait pas trouver.') . '</p>' . rapprochement_manuel($l) . '</details>';
    $c .= '<p class="aide">' . h('Les espèces ne passent pas par Qonto : elles ne sont jamais cherchées. Si un paiement change, la gestion le recherche toute seule. Rien n\'est jamais écrit dans Qonto.') . '</p>';
    mise_en_page('Rapprochement Qonto', $c, 'plus');
}
/** Les boutons de l'écran ; renvoie l'ancre où revenir. */
function rapprochement_action(string $a): string {
    $id = entier('commande', 1);
    $op = strtolower(texte('op', 40));
    $gardees = rapprochement_ops_gardees();
    $opOk = preg_match('/^[0-9a-f\-]{36}$/', $op) && isset($gardees[$op]);
    $apres = function (string $ok) use ($id): string {
        $r = rapprochement_passe(false, false, true);
        $k = $id ? commande($id) : null;
        flash('ok', $ok . ($k && (string)$k['qonto_ref'] !== '' && (string)$k['qonto_par'] === RAPPROCHEMENT_PAR ? ' ' . 'Retrouvée tout de suite dans Qonto.' : ''));
        return 'a-verifier';
    };
    switch ($a) {
        case 'relire':
            try { $r = rapprochement_passe(true, true, true); flash($r['erreurs'] ? 'erreur' : 'ok', $r['erreurs'] ? 'Qonto n\'a pas pu être lu en entier.' : 'Qonto relu.' . ($r['faits'] ? ' ' . count($r['faits']) . ' paiement(s) retrouvé(s) tout seul(s).' : '')); }
            catch (\Throwable $e) { flash('erreur', 'Qonto n\'a pas pu être lu : ' . $e->getMessage() . '.'); }
            return '';
        case 'moyen':
            $e = commande_moyen_noter($id, choix('moyen', MOYENS, ''));
            if ($e !== '') { flash('erreur', $e); return 'a-verifier'; }
            return $apres('Moyen de paiement noté.');
        case 'hors':
            $e = commande_hors_qonto($id, texte('motif', 200));
            flash($e === '' ? 'ok' : 'erreur', $e === '' ? 'Noté : vérifiée hors Qonto.' : $e);
            return 'a-verifier';
        case 'lier':
        case 'confirmer':
            if (!$opOk) { flash('erreur', 'Cet encaissement n\'est plus dans la dernière lecture de Qonto : rechargez la page.'); return 'a-verifier'; }
            $r = rapprochement_passe(false, false, true);
            $ids = $id ? [$id] : array_values(array_unique(array_filter(array_map('intval', is_array($_POST['commandes'] ?? null) ? $_POST['commandes'] : []))));
            $e = $ids ? '' : 'Choisissez une commande.';
            foreach ($ids as $kid) {
                if ($e !== '') break;
                $ref = rapprochement_ref_pour($kid, $op, $r);
                $e = commande_rapprocher_choisie($kid, $ref, rapprochement_ref_detail($ref, $r['ops']) ?: rapprochement_op_court($gardees[$op], false));
            }
            flash($e === '' ? 'ok' : 'erreur', $e === '' ? (count($ids) > 1 ? count($ids) . ' paiements rapprochés avec Qonto.' : 'Paiement rapproché avec Qonto.') : $e);
            return 'a-verifier';
        case 'refuser':
            if (!$opOk) return 'a-verifier';
            foreach (array_filter(array_map('intval', is_array($_POST['commandes'] ?? null) ? $_POST['commandes'] : [])) as $kid) rapprochement_refuser($kid, [$op]);
            flash('ok', 'Noté : la gestion ne les rapprochera pas de cet encaissement.');
            return 'a-verifier';
        case 'payee_carte':
            $k = commande($id);
            if (!$opOk || !$k || !in_array($k['paiement'], ['aucun', 'acompte_demande', 'acompte_recu'], true)) { flash('erreur', 'Cette commande n\'est plus en attente de paiement : rechargez la page.'); return 'a-verifier'; }
            $avant = $k['paiement'] === 'acompte_recu' ? trim((string)$k['qonto_ref']) : '';   // l'acompte déjà retrouvé garde son encaissement
            commande_paiement($id, 'paye', 'carte', null, (string)$k['facture']);
            $e = commande_rapprocher_choisie($id, ($avant !== '' && $avant !== RAPPROCHEMENT_HORS ? $avant . ' + ' : '') . $op, rapprochement_op_court($gardees[$op], false));
            flash($e === '' ? 'ok' : 'erreur', $e === '' ? 'Commande notée payée par carte et rapprochée avec Qonto.' : $e);
            return 'a-verifier';
        case 'payees_virement':
            $ids = array_values(array_unique(array_filter(array_map('intval', is_array($_POST['commandes'] ?? null) ? $_POST['commandes'] : []))));
            $ks = array_filter(array_map(fn($i) => commande($i), $ids));
            if (!$opOk || !$ids || count($ks) !== count($ids) || array_filter($ks, fn($k) => !in_array($k['paiement'], ['aucun', 'acompte_demande', 'acompte_recu'], true))) { flash('erreur', 'Une de ces commandes n\'est plus en attente de paiement : rechargez la page.'); return 'a-verifier'; }
            foreach ($ks as $k) {
                $avant = $k['paiement'] === 'acompte_recu' ? trim((string)$k['qonto_ref']) : '';   // l'acompte déjà retrouvé garde son encaissement
                commande_paiement((int)$k['id'], 'paye', 'virement', null, (string)$k['facture']);
                commande_rapprocher_choisie((int)$k['id'], ($avant !== '' && $avant !== RAPPROCHEMENT_HORS ? $avant . ' + ' : '') . $op, rapprochement_op_court($gardees[$op], false));
            }
            flash('ok', count($ks) > 1 ? count($ks) . ' commandes notées payées par virement et rapprochées avec Qonto.' : 'Commande notée payée par virement et rapprochée avec Qonto.');
            return 'a-verifier';
        case 'sans_commande':
        case 'pas_une_vente':
            if (!$opOk) return 'a-verifier';
            $reste = (int)(rapprochement_passe(false, false, true)['ops'][$op]['net'] ?? 0);
            if ($reste > 0) rapprochement_ecarter($op, $reste, $a === 'sans_commande' ? 'Vente sans commande' : 'Pas une vente');
            flash('ok', 'Mis de côté : il ne sera plus montré. Pour revenir en arrière : « Mis de côté », en bas de l\'écran.');
            return 'a-verifier';
        case 'remettre':
            if (preg_match('/^[0-9a-f\-]{36}$/', $op)) rapprochement_remettre($op);
            flash('ok', 'Remis à vérifier.');
            return 'ecartes';
        case 'annuler':
            commande_derapprocher($id);
            flash('ok', 'Rapprochement annulé : la gestion ne le refera pas toute seule.');
            return 'faits';
        case 'rapprocher':   // à la main : des paiements cochés, un encaissement choisi ou une référence collée
            $ids = array_values(array_unique(array_filter(array_map('intval', is_array($_POST['ids'] ?? null) ? $_POST['ids'] : []))));
            $choisi = encaissement_qonto_choisi();
            $ref = $choisi ? $choisi['id'] : une_ligne(texte('qonto_ref', 120));
            $erreur = !$ids ? 'Cochez au moins un paiement.' : '';
            foreach ($ids as $i) {   // tout ou rien : on vérifie chaque commande avant d'écrire
                if ($erreur !== '') break;
                $k = commande($i);
                if (!$k || !rapprochement_attendu($k) || (string)$k['qonto_ref'] !== '') $erreur = 'Un des paiements cochés n\'est plus à rapprocher : rechargez la page.';
                elseif (($e = reference_qonto_erreur($ref, $k)) !== null) $erreur = $e;
            }
            if ($erreur === '') {
                foreach ($ids as $i) $choisi ? commande_rapprocher_choisie($i, $ref, rapprochement_op_court($choisi, false)) : commande_rapprocher($i, $ref);
                flash('ok', count($ids) . ' paiement(s) rapproché(s) avec Qonto.');
            } else flash('erreur', $erreur . ' Rien n\'a été noté.');
            return 'main';
    }
    return '';
}
/** Ce que montre la fiche d'une commande pour sa référence Qonto : les encaissements en clair (versement, virement), sinon la référence collée. */
function rapprochement_ref_texte(string $ref): string {
    if ($ref === RAPPROCHEMENT_HORS) return '<span>' . h('Vérifiée hors Qonto') . '</span>';
    $g = rapprochement_ops_gardees();
    $l = [];
    foreach (explode(' + ', $ref) as $x) {
        $x = trim($x);
        $l[] = isset($g[strtolower($x)]) ? '<span>' . h(ucfirst(rapprochement_op_court($g[strtolower($x)]))) . '</span>'
             : (preg_match('/^[0-9a-f\-]{36}$/i', $x) ? '<span>' . h('Encaissement Qonto') . '</span>' : '<span translate="no">' . h($x) . '</span>');
    }
    return implode(' + ', $l);
}
/** Aujourd'hui : les paiements à vérifier, seulement s'il y en a (et l'alerte si Qonto ne se lit plus). */
function bloc_rapprochement_accueil(): string {
    if (!qonto_relie()) return '';
    try { $r = rapprochement_passe(); }
    catch (\Throwable $e) { error_log('madamecho gestion rapprochement : ' . $e->getMessage()); return ''; }
    $s = '';
    if ($r['lu_ok'] > 0 && $r['lu_ok'] < time() - RAPPROCHEMENT_SILENCE * 86400)
        $s .= '<p class="erreur">' . h('Qonto n\'a pas pu être lu en entier depuis plus de 3 jours : les paiements ne sont plus retrouvés.') . ' '
            . (est_admin() ? '<a href="' . h(u('reglages')) . '#qonto">' . h('Paramètres › Qonto') . '</a>' : h('Prévenez Olivier.')) . '</p>';
    $pb = $r['problemes'];
    if (!$pb && $s === '') return '';
    $l = '';
    foreach (array_slice($pb, 0, 4) as $p)
        $l .= '<a class="carte-cmd" href="' . h(u('rapprochement')) . '#pb-' . h($p['cle']) . '"><span class="l1"><b>' . h(rapprochement_titre($p)) . '</b><span class="chip">' . h(euros((int)$p['montant'])) . '</span></span>'
            . '<span class="l2">' . rapprochement_sous_titre($p) . '</span></a>';
    if (count($pb) > 4) $l .= '<p class="aide">' . h('Et ' . (count($pb) - 4) . ' autre(s) au Rapprochement.') . '</p>';
    return bloc('Paiements à vérifier', $s . ($pb ? '<p class="aide">' . h('La gestion retrouve toute seule les paiements dans Qonto. Ceux-ci demandent votre œil, un toucher suffit :') . '</p><div class="liste">' . $l . '</div>'
        . '<p>' . lien_bouton(u('rapprochement') . '#a-verifier', icone('paiement') . 'Ouvrir le Rapprochement Qonto', 'secondaire') . '</p>' : ''), '', 'paiements');
}

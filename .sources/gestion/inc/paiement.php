<?php
/*
 * Madame Cho : espace de gestion. Le paiement en ligne des commandes (spécification 006), par les liens de paiement de Qonto
 * (l'encaissement est fait par Mollie, partenaire de Qonto). Demande d'Olivier du 21/11/2026 ; décisions du 27/11/2026 : plus
 * d'acompte, une commande de la carte payée sur le site est confirmée sans Youngmi, rien n'est gardé à l'annulation, reçu automatique.
 *
 * Deux temps, chacun avec son interrupteur (Paramètres › Paiement en ligne), éteints par défaut :
 * - temps 1 : le mail de confirmation, envoyé au toucher de Youngmi, porte un bouton « Payer en ligne » du montant dû ;
 * - temps 2 : sur le site, « Commander et payer » pour un panier de la carte sur une plage libre ; la place est retenue 30 minutes.
 * Le paiement n'est noté que sur la foi de Qonto : une notification signée (?p=qonto-notification) déclenche une relecture du lien
 * chez Qonto ; en secours, la page de suivi du site, l'ouverture de la commande et la tâche planifiée relisent aussi les liens ouverts.
 *
 * Droits chez Qonto : créer, relire et désactiver un lien, s'abonner aux notifications, et rien d'autre (exception à R-24 limitée aux
 * liens : liste fermée des écritures dans qonto_ecrire). Le code ne déplace jamais d'argent : un remboursement se fait par Youngmi dans
 * Mollie. Le reçu part tout seul (exception à R-01 décidée le 27/11/2026), par le même chemin que l'accusé de réception.
 * Aucun secret dans ce dépôt public : les jetons et le secret des notifications sont dans la base (hors du site), jamais montrés,
 * jamais exportés, jamais lus par l'assistant.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/modele.php';
require_once __DIR__ . '/vues.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/accuse.php';
require_once __DIR__ . '/qonto.php';

const PAIEMENT_ETATS = ['open' => 'Envoyé, pas encore payé', 'processing' => 'Paiement en cours', 'paid' => 'Payé', 'expired' => 'Expiré', 'canceled' => 'Annulé'];
const PAIEMENT_MOYENS_QONTO = ['credit_card' => 'carte bancaire', 'apple_pay' => 'Apple Pay', 'paypal' => 'PayPal', 'ideal' => 'iDEAL', 'bank_transfer' => 'virement'];
/** Qui note un paiement constaté par la gestion, sur la foi de Qonto. */
const PAIEMENT_PAR = 'Qonto (paiement en ligne)';
/** Les étapes du remboursement, dans le tableau de bord de Mollie (aucun remboursement par l'API : R-24). */
const PAIEMENT_ETAPES_MOLLIE = 'Ouvrez Mollie (my.mollie.com) avec le compte de Madame Cho › Transactions › Paiements › touchez le paiement (référence de la commande) › « Rembourser » › montant entier › confirmez. Puis revenez ici et touchez « J\'ai remboursé dans Mollie ».';

/* ---------- Réglages ---------- */
function paiement_minimum(): int { $n = (int)parametre('paiement_minimum', '500'); return $n >= 100 && $n <= 100000 ? $n : 500; }
function paiement_retenue_minutes(): int { $n = (int)parametre('paiement_retenue_minutes', '30'); return $n >= 10 && $n <= 60 ? $n : 30; }
/** Le barème des frais, pour une estimation : [centièmes de pour cent, centimes fixes] (1,40 % + 0,25 € par défaut). */
function paiement_bareme(): array {
    $b = explode(',', (string)parametre('paiement_bareme', '140,25'));
    $pc = (int)($b[0] ?? 140); $fixe = (int)($b[1] ?? 25);
    return [$pc >= 0 && $pc <= 1000 ? $pc : 140, $fixe >= 0 && $fixe <= 500 ? $fixe : 25];
}
function paiement_frais_estimes(int $montant): int { [$pc, $fixe] = paiement_bareme(); return (int)round($montant * $pc / 10000) + $fixe; }
/** L'espace Qonto des liens : un espace relié avec le droit de créer des liens, celui qui a un compte bancaire Qonto d'abord. */
function paiement_org(): ?string {
    $choisi = null;
    foreach (qonto_espaces() as $org => $e) {
        if (($e['refresh'] ?? '') === '' || !str_contains(' ' . ($e['portees'] ?? '') . ' ', ' payment_link.write ')) continue;
        foreach ($e['comptes'] ?? [] as $c) if (empty($c['externe'])) return (string)$org;
        $choisi ??= (string)$org;
    }
    return $choisi;
}
/** Tout est prêt chez Qonto : droit des liens, et abonnement aux notifications actif. */
function paiement_pret(): bool { return paiement_org() !== null && (string)parametre('qonto_notification_secret', '') !== ''; }
/** Un temps est-il allumé (et prêt) ? 1 : bouton dans la confirmation ; 2 : « Commander et payer » sur le site. */
function paiement_temps(int $n): bool { return parametre('paiement_temps' . $n, '0') === '1' && paiement_pret(); }
/** Le site peut-il proposer « Commander et payer » ? Il faut aussi que les demandes du site s'enregistrent dans la gestion. */
function paiement_site_actif(): bool { return gestion_base_existante() && parametre('demandes_site') === '1' && paiement_temps(2); }

/* ---------- Qonto : la liste fermée des écritures ---------- */
/** Les seuls appels qui écrivent chez Qonto (exception à R-24 limitée aux liens de paiement) : créer un lien, le désactiver, créer ou
 *  retirer l'abonnement aux notifications. Tout autre chemin est refusé ici, avant de partir. */
function qonto_ecrire(string $org, string $methode, string $chemin, ?array $corps = null, int $delai = 20): array {
    $permis = match ($methode) {
        'POST' => preg_match('#^v2/(payment_links|webhook_subscriptions)$#', $chemin) === 1,
        'PATCH' => preg_match('#^v2/payment_links/[A-Za-z0-9\-]{8,64}/deactivate$#', $chemin) === 1,
        'DELETE' => preg_match('#^v2/webhook_subscriptions/[A-Za-z0-9\-]{8,64}$#', $chemin) === 1,
        default => false,
    };
    if (!$permis) throw new \LogicException('écriture chez Qonto refusée par la gestion : ' . $methode . ' ' . $chemin);
    $url = GESTION_QONTO_API . $chemin;
    $json = $corps === null ? '' : (string)json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    [$code, $j] = qonto_http($methode, $url, ['authorization: Bearer ' . qonto_jeton($org)], null, $json, $delai);
    if ($code === 401) [$code, $j] = qonto_http($methode, $url, ['authorization: Bearer ' . qonto_jeton($org, true)], null, $json, $delai);
    if ($code < 200 || $code >= 300) throw new \RuntimeException(qonto_erreur($code, $j));
    return is_array($j) ? $j : [];
}
/** « 12.50 » (ou 12.5) de Qonto en centimes ; null si illisible. */
function centimes_qonto(mixed $v): ?int {
    if (is_int($v)) return $v * 100;
    if (is_float($v)) return (int)round($v * 100);
    if (!is_string($v) || !preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/', trim($v), $m)) return null;
    return (int)$m[1] * 100 + (int)str_pad($m[2] ?? '0', 2, '0');
}
function montant_qonto(int $centimes): string { return intdiv($centimes, 100) . '.' . sprintf('%02d', $centimes % 100); }
/** Le taux de TVA d'une fiche (55 = 5,5 %) au format de Qonto (« 0.055 »). */
function tva_qonto(int $tva): string { $s = number_format($tva / 1000, 3, '.', ''); return rtrim(rtrim($s, '0'), '.') ?: '0'; }
/** Une date de Qonto (ISO 8601, UTC) en heure de Paris ; '' si illisible. */
function date_qonto(mixed $v): string { $t = is_string($v) && $v !== '' ? strtotime($v) : false; return $t ? date('Y-m-d H:i:s', $t) : ''; }

/* ---------- Les liens ---------- */
function lien(int $id): ?array { return ligne("SELECT * FROM liens_paiement WHERE id = ?", [$id]); }
function liens_de(int $commande): array { return lignes("SELECT * FROM liens_paiement WHERE commande_id = ? ORDER BY id DESC", [$commande]); }
/** Le lien ouvert d'une commande (au plus un), ou null. */
function lien_ouvert(int $commande): ?array { return ligne("SELECT * FROM liens_paiement WHERE commande_id = ? AND etat IN ('open', 'processing') ORDER BY id DESC LIMIT 1", [$commande]); }
function lien_paye(int $commande): ?array { return ligne("SELECT * FROM liens_paiement WHERE commande_id = ? AND paiement_qonto_id IS NOT NULL ORDER BY id DESC LIMIT 1", [$commande]); }
/** Ce que le client doit encore : le prix final, moins un ancien acompte déjà reçu. */
function montant_du(array $k): int { return max(0, montant_commande($k) - (($k['paiement'] ?? '') === 'acompte_recu' ? (int)$k['acompte'] : 0)); }
/** Pourquoi une commande ne peut pas recevoir de lien au temps 1 ('' : elle peut). */
function lien_refus(array $k): string {
    if (!empty($k['efface_le'])) return 'commande effacée';
    if (($k['client_type'] ?? '') === 'professionnel' || ($k['type'] ?? '') === 'pro') return 'les professionnels paient sur facture';
    if (in_array($k['paiement'] ?? '', ['paye', 'rembourse'], true)) return 'déjà payée';
    if (!in_array($k['statut'] ?? '', ['confirmee'], true)) return 'la commande n\'est pas confirmée';
    if ($k['prix_final'] === null) return 'pas encore de prix final';
    if (montant_du($k) < paiement_minimum()) return 'montant sous le minimum du paiement en ligne (' . euros(paiement_minimum()) . ')';
    return '';
}
/** Le bouton « Payer en ligne » peut-il partir avec la confirmation de cette commande ? */
function paiement_propose(array $k): bool { return paiement_temps(1) && lien_refus($k) === ''; }
/** Les lignes d'un lien du temps 1 : une par gâteau (prix TTC et TVA de la fiche) si leur somme fait le montant dû ; sinon une seule
 *  ligne « Commande MC-… » au taux de TVA le plus présent (un prix final convenu après une proposition). */
function lien_lignes(array $k, int $montant): array {
    $tva = tva_produits();
    $items = []; $somme = 0; $parTaux = [];
    foreach ($k['lignes_tab'] ?? [] as $l) {
        $q = (int)($l['q'] ?? 0); $prix = (int)($l['prix'] ?? 0);
        if ($q < 1 || $prix < 1) continue;
        $t = (int)($tva[explode('|', (string)($l['v'] ?? ''))[0]] ?? 55);
        $parTaux[$t] = ($parTaux[$t] ?? 0) + $prix * $q;
        $items[] = ['title' => mb_substr($k['reference'] . ' · ' . $l['nom'] . (($l['libelle'] ?? '') !== '' ? ', ' . $l['libelle'] : ''), 0, 120), 'quantity' => $q,
                    'unit_price' => ['value' => montant_qonto($prix), 'currency' => 'EUR'], 'vat_rate' => tva_qonto($t), 'type' => 'good'];
        $somme += $prix * $q;
    }
    if ($items && $somme === $montant && ($k['perso'] ?? '') === '') return $items;
    arsort($parTaux);
    $t = $parTaux ? (int)array_key_first($parTaux) : 55;
    return [['title' => 'Commande ' . $k['reference'] . ' · Madame Cho', 'quantity' => 1, 'unit_price' => ['value' => montant_qonto($montant), 'currency' => 'EUR'],
             'vat_rate' => tva_qonto($t), 'type' => 'good']];
}
/** Crée un lien chez Qonto pour ces lignes ; vérifie que Qonto annonce bien le montant attendu (sinon le lien est désactivé aussitôt).
 *  Renvoie [identifiant, adresse, échéance] ; rien n'est écrit dans la base ici. */
function qonto_lien_nouveau(array $items, int $montant): array {
    $org = paiement_org();
    if ($org === null) throw new \RuntimeException('Qonto n\'est pas relié pour le paiement en ligne');
    $j = qonto_ecrire($org, 'POST', 'v2/payment_links', ['payment_link' => ['potential_payment_methods' => ['credit_card', 'apple_pay'], 'reusable' => false, 'items' => $items]], 15);
    $l = is_array($j['payment_link'] ?? null) ? $j['payment_link'] : $j;
    $id = (string)($l['id'] ?? '');
    $url = (string)($l['url'] ?? '');
    if (!preg_match('/^[A-Za-z0-9\-]{8,64}$/', $id) || !preg_match(GESTION_ESSAI ? '#^https?://#' : '#^https://#', $url)) throw new \RuntimeException('réponse inattendue de Qonto (lien de paiement)');
    $annonce = centimes_qonto($l['amount']['value'] ?? null);
    if ($annonce !== $montant) {
        try { qonto_ecrire($org, 'PATCH', 'v2/payment_links/' . $id . '/deactivate', null, 10); } catch (\Throwable $e) { error_log('madamecho gestion paiement : lien ' . $id . ' non désactivé'); }
        error_log('madamecho gestion paiement : montant du lien différent (' . ($annonce ?? -1) . ' au lieu de ' . $montant . ')');
        throw new \RuntimeException('Qonto annonce un autre montant que celui de la commande (' . ($annonce === null ? '?' : euros($annonce)) . ' au lieu de ' . euros($montant) . ') : lien annulé');
    }
    return [$id, $url, date_qonto($l['expiration_date'] ?? '')];
}
/** Garde un lien créé chez Qonto ; renvoie sa ligne. */
function lien_garder(int $commande, string $qid, string $url, int $montant, string $origine, string $expire, ?string $retenue = null, ?string $suivi = null, ?string $par = null): array {
    $now = maintenant();
    q("INSERT INTO liens_paiement (commande_id, qonto_id, url, montant, origine, etat, cree_le, cree_par, expire_le, retenue_jusqua, suivi, maj_le) VALUES (?, ?, ?, ?, ?, 'open', ?, ?, ?, ?, ?, ?)",
      [$commande, $qid, $url, $montant, $origine, $now, $par ?? (auteur() ?: 'système'), $expire, $retenue, $suivi, $now]);
    return lien((int)db()->lastInsertId());
}
/** Le lien du temps 1 pour cette commande : l'ouvert s'il est du bon montant, sinon un neuf (l'ancien est désactivé d'abord, FR-006). */
function lien_pour_commande(array $k): array {
    $du = montant_du($k);
    $o = lien_ouvert((int)$k['id']);
    if ($o && (int)$o['montant'] === $du && $o['origine'] === 'gestion') return $o;
    if ($o) lien_desactiver($o, 'nouveau lien pour un autre montant');
    [$qid, $url, $expire] = qonto_lien_nouveau(lien_lignes($k, $du), $du);
    $l = lien_garder((int)$k['id'], $qid, $url, $du, 'gestion', $expire);
    evenement('paiement_lien', 'Lien de paiement en ligne créé : ' . euros($du), ['commande' => (int)$k['id'], 'client' => (int)$k['client_id']], '', 'open');
    return $l;
}
/** Désactive un lien ouvert chez Qonto (il ne permet plus de payer) et le note annulé. Lève une erreur si Qonto ne répond pas : le lien
 *  reste alors ouvert dans la gestion, et Youngmi peut réessayer depuis la commande. */
function lien_desactiver(array $l, string $raison, ?string $auteur = null): void {
    if (!in_array($l['etat'], ['open', 'processing'], true)) return;
    $org = paiement_org();
    if ($org === null) throw new \RuntimeException('Qonto n\'est pas relié pour le paiement en ligne');
    try {
        qonto_ecrire($org, 'PATCH', 'v2/payment_links/' . $l['qonto_id'] . '/deactivate', null, 10);
    } catch (\RuntimeException $e) {
        // Déjà payé, expiré ou annulé chez Qonto : la relecture dit ce qu'il en est
        $r = lien_relire($l, true);
        if ($r && !in_array($r['etat'], ['open', 'processing'], true)) return;
        throw $e;
    }
    q("UPDATE liens_paiement SET etat = 'canceled', maj_le = ? WHERE id = ? AND etat IN ('open', 'processing') AND paiement_qonto_id IS NULL", [maintenant(), (int)$l['id']]);
    $k = commande((int)$l['commande_id']);
    evenement('paiement_lien', 'Lien de paiement en ligne annulé (' . $raison . ')', ['commande' => (int)$l['commande_id'], 'client' => $k ? (int)$k['client_id'] : null], 'open', 'canceled', $auteur);
}
/** Après un changement de la commande : un lien ouvert qui ne correspond plus (payée autrement, annulée, remise, prix changé) est désactivé
 *  (FR-005, FR-006). Appelé par commande_statut, commande_paiement et commande_modifier ; jamais bloquant. */
function paiement_commande_changee(int $id): void {
    if (!gestion_base_existante()) return;
    $o = lien_ouvert($id);
    $k = $o ? commande($id) : null;
    if (!$o || !$k) return;
    $raison = match (true) {
        in_array($k['paiement'], ['paye', 'rembourse'], true) => 'commande payée autrement',
        in_array($k['statut'], ['annulee', 'remise', 'sans_suite'], true) => 'commande ' . mb_strtolower(STATUTS[$k['statut']]),
        $o['origine'] === 'gestion' && montant_du($k) !== (int)$o['montant'] => 'prix changé',
        default => '',
    };
    if ($raison === '') return;
    try { lien_desactiver($o, $raison); }
    catch (\Throwable $e) {
        error_log('madamecho gestion paiement : lien ' . $o['id'] . ' non désactivé : ' . $e->getMessage());
        evenement('paiement_lien', 'Lien de paiement en ligne à annuler (' . $raison . ') : Qonto n\'a pas répondu, réessayez depuis la commande', ['commande' => $id, 'client' => (int)$k['client_id']], '', '', 'système');
    }
}

/* ---------- Relire et constater ---------- */
/** Signale un problème sur la commande sans effacer la note d'un problème déjà ouvert : la nouvelle note s'ajoute à la suite. */
function probleme_ajouter(array $k, string $note): void {
    $avant = $k['probleme'] ? trim((string)$k['probleme_note']) : '';
    q("UPDATE commandes SET probleme = 1, probleme_note = ?, maj_le = ? WHERE id = ?", [$avant !== '' ? $avant . "\n" . $note : $note, maintenant(), (int)$k['id']]);
}
/** Relit un lien chez Qonto (au plus toutes les $intervalle secondes, sauf $forcer) et en tire les conséquences : paiement constaté,
 *  ou, au temps 2, paiement non abouti. Renvoie la ligne à jour. Seule la relecture compte, jamais une notification seule (FR-010). */
function lien_relire(array $l, bool $forcer = false, int $intervalle = 15): ?array {
    if ($l['paiement_qonto_id'] !== null) return $l;   // un paiement constaté ne change plus
    if (!$forcer && strtotime((string)$l['maj_le']) > time() - $intervalle) return $l;
    $org = paiement_org();
    if ($org === null) return $l;
    q("UPDATE liens_paiement SET maj_le = ? WHERE id = ?", [maintenant(), (int)$l['id']]);   // même si Qonto ne répond pas : pas d'appel en rafale
    $j = qonto_lire($org, 'v2/payment_links/' . rawurlencode((string)$l['qonto_id']));
    $p = is_array($j['payment_link'] ?? null) ? $j['payment_link'] : $j;
    $etat = (string)($p['status'] ?? '');
    if (!isset(PAIEMENT_ETATS[$etat])) throw new \RuntimeException('réponse inattendue de Qonto (état du lien)');
    if (in_array($etat, ['paid', 'processing'], true)) {
        $pj = qonto_lire($org, 'v2/payment_links/' . rawurlencode((string)$l['qonto_id']) . '/payments', ['per_page' => 25]);
        foreach (is_array($pj['payments'] ?? null) ? $pj['payments'] : [] as $x) {
            if (is_array($x) && ($x['status'] ?? '') === 'paid' && !empty($x['id'])) { paiement_constater($l, $x); return lien((int)$l['id']); }
        }
    }
    q("UPDATE liens_paiement SET etat = ?, expire_le = CASE WHEN ? <> '' THEN ? ELSE expire_le END WHERE id = ? AND paiement_qonto_id IS NULL",
      [$etat === 'paid' ? 'processing' : $etat, date_qonto($p['expiration_date'] ?? ''), date_qonto($p['expiration_date'] ?? ''), (int)$l['id']]);
    $l = lien((int)$l['id']);
    if ($l && in_array($etat, ['expired', 'canceled'], true) && $l['origine'] === 'site') paiement_non_abouti($l, $etat === 'expired' ? 'lien expiré chez Qonto' : 'lien annulé', false);
    return $l;
}
/**
 * Un paiement réussi, lu chez Qonto : tout est noté en une transaction (lien payé, commande payée « En ligne », retenue effacée,
 * événement, reçu réservé), une seule fois grâce à l'identifiant unique du paiement (FR-009) ; puis le reçu part (exception à R-01).
 * Au temps 2, la commande devient confirmée. Un montant différent n'est jamais noté payé : la commande est signalée « à vérifier ».
 * Renvoie true si ce paiement vient d'être constaté.
 */
function paiement_constater(array $l, array $p): bool {
    $pid = mb_substr((string)$p['id'], 0, 80);
    $montant = centimes_qonto($p['amount']['value'] ?? ($p['amount'] ?? null));
    $paye_le = date_qonto($p['paid_at'] ?? '') ?: maintenant();
    $moyen = (string)($p['payment_method'] ?? '');
    $moyen = isset(PAIEMENT_MOYENS_QONTO[$moyen]) ? $moyen : '';
    $email = mb_strtolower(trim((string)($p['debitor_email'] ?? '')));
    if (!email_valide($email) || mb_strlen($email) > 120) $email = '';
    $now = maintenant();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $bon = $montant === (int)$l['montant'];
        $n = q("UPDATE liens_paiement SET etat = 'paid', paiement_qonto_id = ?, paye_le = ?, moyen_detail = ?, payeur_email = ?, paye_montant = ?, frais_estimes = ?,
                recu_envoye_le = ?, recu_etat = ?, maj_le = ? WHERE id = ? AND paiement_qonto_id IS NULL",
               [$pid, $paye_le, $moyen, $email, $montant, $montant !== null ? paiement_frais_estimes($montant) : null, $bon ? $now : null, $bon ? 'reserve' : 'a_verifier', $now, (int)$l['id']])->rowCount();
        if ($n === 0) { $pdo->commit(); return false; }   // déjà constaté par un autre chemin
        q("DELETE FROM retenues WHERE commande_id = ?", [(int)$l['commande_id']]);
        $k = commande((int)$l['commande_id']);
        if (!$k) { $pdo->commit(); return false; }
        $liens = ['commande' => (int)$k['id'], 'client' => (int)$k['client_id']];
        $detail = euros((int)$montant) . ($moyen !== '' ? ' par ' . PAIEMENT_MOYENS_QONTO[$moyen] : '') . ', le ' . date_fr($paye_le, 'heure');
        if (!$bon) {
            probleme_ajouter($k, 'Paiement en ligne à vérifier : Qonto annonce ' . ($montant === null ? 'un montant illisible' : euros($montant)) . ' au lieu de ' . euros((int)$l['montant']) . '. Rien n\'a été noté payé : regardez le paiement dans Mollie, puis notez le paiement à la main.');
            evenement('probleme', 'Paiement en ligne à vérifier : montant différent de celui du lien', $liens, '', '1', 'système');
            $pdo->commit();
            return true;
        }
        if ($k['paiement'] === 'paye' || $k['statut'] === 'annulee' || !empty($k['efface_le'])) {
            // Payée deux fois (en ligne et au retrait), ou payée après l'annulation : rien n'est écrasé, la commande est signalée
            $note = $k['statut'] === 'annulee' ? 'Payée en ligne après son annulation (' . $detail . ') : à rembourser en entier dans Mollie.'
                                              : 'Payée deux fois : en ligne (' . $detail . ') et ' . mb_strtolower(MOYENS[$k['moyen']] ?? 'autrement') . ' : ' . euros((int)$montant) . ' à rembourser au client dans Mollie.';
            probleme_ajouter($k, $note);
            evenement('probleme', $note, $liens, '', '1', 'système');
        } else {
            q("UPDATE commandes SET paiement = 'paye', moyen = 'en_ligne', paiement_par = ?, paiement_le = ?, qonto_ref = ?, qonto_le = ?, qonto_par = ?, maj_le = ? WHERE id = ?",
              [PAIEMENT_PAR, $paye_le, $pid, $now, PAIEMENT_PAR, $now, (int)$k['id']]);
            evenement('paiement', 'Payé en ligne : ' . $detail, $liens, $k['paiement'], 'paye', 'système');
            if ($l['origine'] === 'site' && in_array($k['statut'], ['demande', 'proposition'], true)) {
                // Temps 2 : la commande de la carte payée est confirmée sans Youngmi (décision du 27/11/2026), avec sa plage
                q("UPDATE commandes SET statut = 'confirmee', dernier_echange = ?, maj_le = ? WHERE id = ?", [$now, $now, (int)$k['id']]);
                evenement('statut', 'Confirmée : payée en ligne sur le site', $liens, $k['statut'], 'confirmee', 'site');
                $av = plage_avertissement(commande((int)$k['id']) ?? $k);
                if ($av !== '') {   // payée après la fin de la retenue, sur une plage devenue complète : acceptée, signalée
                    probleme_ajouter(commande((int)$k['id']) ?? $k, 'Payée en ligne sur une plage devenue complète entre-temps. ' . $av . ' Appelez le client si besoin.');
                    evenement('probleme', 'Plage dépassée par une commande payée en ligne', $liens, '', '1', 'système');
                }
            }
        }
        evenement('paiement_en_ligne', 'Paiement en ligne reçu : ' . $detail, $liens, $pid, (string)$montant, 'système');
        q("UPDATE clients SET dernier_echange = ? WHERE id = ?", [$now, (int)$k['client_id']]);
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    // Après la transaction : le reçu (une seule fois, réservé ci-dessus), et au temps 2 le mail interne « Nouvelle commande payée »
    if ($bon) {
        try { recu_envoyer((int)$l['id'], 'système'); } catch (\Throwable $e) { error_log('madamecho gestion paiement : reçu non envoyé : ' . $e->getMessage()); }
        if ($l['origine'] === 'site') {
            try { mail_interne_paiement((int)$l['commande_id'], 'payee'); } catch (\Throwable $e) { error_log('madamecho gestion paiement : mail interne non envoyé'); }
        }
    }
    return true;
}

/* ---------- Le reçu (exception à R-01 décidée le 27/11/2026) ---------- */
/** Les détails du paiement pour le reçu. */
function recu_details(array $l): array {
    return ['montant' => (int)($l['paye_montant'] ?? $l['montant']), 'le' => (string)($l['paye_le'] ?? ''), 'moyen' => PAIEMENT_MOYENS_QONTO[$l['moyen_detail']] ?? 'paiement en ligne',
            'ref' => (string)$l['paiement_qonto_id'], 'confirme' => $l['origine'] === 'site'];
}
/** Envoie le reçu d'un paiement constaté (tout seul, ou au toucher de Youngmi sur « Renvoyer le reçu ») : signé DKIM, copie à contact@,
 *  dans la même conversation que les autres mails de la commande, rangé dans les Échanges. Au temps 2, il vaut confirmation.
 *  Renvoie 'envoye', 'sans_email', 'echec' ou 'sans'. */
function recu_envoyer(int $lienId, string $auteur): string {
    $l = lien($lienId);
    if (!$l || $l['paiement_qonto_id'] === null || $l['recu_etat'] === 'a_verifier') return 'sans';
    $k = commande((int)$l['commande_id']);
    if (!$k) return 'sans';
    if (!email_valide((string)$k['client_email'])) { q("UPDATE liens_paiement SET recu_etat = 'sans_email' WHERE id = ?", [$lienId]); return 'sans_email'; }
    $d = recu_details($l);
    $d['confirme'] = $d['confirme'] && $k['statut'] === 'confirmee';   // payée sur le site mais pas confirmée (annulée entre-temps) : un simple reçu
    $m = mail_commande($k, 'paiement', ['paiement' => $d]);
    $mid = envoyer_email_client((string)$k['client_email'], $m['sujet'], $m['corps'], $m['html'], mail_refs((int)$k['id']), true);
    $liens = ['commande' => (int)$k['id'], 'client' => (int)$k['client_id']];
    if ($mid === '') {
        q("UPDATE liens_paiement SET recu_etat = 'echec' WHERE id = ?", [$lienId]);
        evenement('paiement_en_ligne', 'Reçu du paiement en ligne pas parti : à renvoyer depuis la commande', $liens, '', '', 'système');
        return 'echec';
    }
    $now = maintenant();
    q("UPDATE liens_paiement SET recu_etat = 'envoye', recu_envoye_le = ? WHERE id = ?", [$now, $lienId]);
    $type = $d['confirme'] ? 'confirmation' : 'paiement';   // au temps 2, le reçu est aussi la confirmation (l'étape « confirmation envoyée » en tient compte)
    evenement('email_client', ($auteur === 'système' ? 'Reçu du paiement en ligne envoyé automatiquement à ' : 'Reçu du paiement en ligne renvoyé à ') . $k['client_email'] . ' : « ' . $m['sujet'] . ' »', $liens,
              'type:' . $type . ' mid:' . $mid . ($type === 'confirmation' ? ' rdv:' . rdv_cle($k) : ''), $m['corps'], $auteur);
    q("UPDATE commandes SET dernier_echange = ? WHERE id = ?", [$now, (int)$k['id']]);
    return 'envoye';
}

/* ---------- Temps 2 : commander et payer sur le site ---------- */
/** Les places retenues d'un jour par des paiements en cours, par plage (le site seulement, jamais l'Agenda de la gestion). */
function retenues_du_jour(string $jour, int $sauf = 0): array {
    $r = [];
    foreach (lignes("SELECT plage, COUNT(*) AS n FROM retenues WHERE jour = ? AND jusqua > ? AND commande_id <> ? GROUP BY plage", [$jour, maintenant(), $sauf]) as $x) $r[(string)$x['plage']] = (int)$x['n'];
    return $r;
}
/** Pourquoi le site ne propose pas de payer ce panier ('' : il peut). $d : pro, perso, besoin, lignes, total. */
function paiement_site_refus(array $d): string {
    if (!paiement_site_actif()) return 'Le paiement en ligne n\'est pas proposé pour le moment : envoyez votre demande, Madame Cho vous répond sous 24 heures.';
    if (!empty($d['pro']) || trim((string)($d['perso'] ?? '')) !== '' || trim((string)($d['besoin'] ?? '')) !== '' || empty($d['lignes']))
        return 'Le paiement en ligne est proposé pour les gâteaux de la carte, sans personnalisation : pour votre demande, Madame Cho vous répond sous 24 heures.';
    if ((int)($d['total'] ?? 0) < paiement_minimum()) return 'Le paiement en ligne commence à ' . euros(paiement_minimum()) . ' : envoyez votre demande, vous paierez au retrait.';
    return '';
}
/**
 * « Commander et payer » (appelé par ma-demande/envoyer.php, après tous ses contrôles et sa limite d'envois). Sous un verrou : la plage
 * est revérifiée (retenues comprises), le lien est créé chez Qonto, puis la commande est enregistrée comme une demande avec sa retenue.
 * Aucun mail à ce moment. Renvoie ['ok' => true, 'url', 'suivi', 'jusqua', 'commande'] ou ['ok' => false, 'message'].
 * $d : les champs de enregistrer_demande(), plus total (centimes) ; chaque ligne : nom, libelle, q, prix (total de la ligne), p, c.
 */
function commander_et_payer(array $d): array {
    $refus = paiement_site_refus($d);
    if ($refus !== '') return ['ok' => false, 'message' => $refus];
    paiement_retenues_echues();
    $verrou = fopen(gestion_dossier() . '/retenues.verrou', 'c');
    if ($verrou === false) return ['ok' => false, 'message' => 'Le paiement en ligne n\'est pas possible pour le moment : envoyez votre demande sans payer.'];
    $qid = '';
    try {
        flock($verrou, LOCK_EX);
        $plage = plage_refusee((string)$d['date'], (string)$d['plage']);
        if ($plage !== '') return ['ok' => false, 'message' => $plage];
        $tva = tva_produits();
        $items = []; $somme = 0;
        foreach ($d['lignes'] as $x) {
            $q = (int)$x['q']; $prix = (int)$x['prix'];
            if ($q < 1 || $prix < 1 || $prix % $q !== 0) return ['ok' => false, 'message' => 'Le paiement en ligne n\'est pas possible pour ce panier : envoyez votre demande sans payer.'];
            $items[] = ['title' => mb_substr($d['ref'] . ' · ' . $x['nom'] . ', ' . $x['libelle'], 0, 120), 'quantity' => $q,
                        'unit_price' => ['value' => montant_qonto(intdiv($prix, $q)), 'currency' => 'EUR'], 'vat_rate' => tva_qonto((int)($tva[(string)$x['p']] ?? 55)), 'type' => 'good'];
            $somme += $prix;
        }
        if ($somme !== (int)$d['total']) return ['ok' => false, 'message' => 'Le paiement en ligne n\'est pas possible pour ce panier : envoyez votre demande sans payer.'];
        try { [$qid, $url, $expire] = qonto_lien_nouveau($items, $somme); }
        catch (\Throwable $e) {
            error_log('madamecho formulaire : lien de paiement non créé (' . $e->getMessage() . ')');
            return ['ok' => false, 'message' => 'Le paiement en ligne ne répond pas pour le moment, et rien n\'a été débité : envoyez votre demande sans payer, Madame Cho vous répond sous 24 heures.'];
        }
        $id = enregistrer_demande($d + ['paiement' => true]);
        if ($id <= 0) throw new \RuntimeException('commande non enregistrée');
        $jusqua = date('Y-m-d H:i:s', time() + paiement_retenue_minutes() * 60);
        $suivi = bin2hex(random_bytes(16));
        $pdo = db();
        $pdo->beginTransaction();
        try {
            q("INSERT OR REPLACE INTO retenues (commande_id, jour, plage, jusqua) VALUES (?, ?, ?, ?)", [$id, (string)$d['date'], (string)$d['plage'], $jusqua]);
            lien_garder($id, $qid, $url, $somme, 'site', $expire, $jusqua, $suivi, 'site');
            evenement('paiement_lien', 'Le client paie en ligne sur le site (' . euros($somme) . ') : place retenue jusqu\'à ' . substr($jusqua, 11, 5), ['commande' => $id, 'client' => (int)val("SELECT client_id FROM commandes WHERE id = ?", [$id])], '', 'open', 'site');
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // La commande est arrivée, mais sans paiement possible : elle devient une demande ordinaire (mail à Madame Cho, accusé au client)
            try { qonto_ecrire((string)paiement_org(), 'PATCH', 'v2/payment_links/' . $qid . '/deactivate', null, 10); } catch (\Throwable) {}
            demande_sans_paiement($id, 'erreur technique au moment du paiement');
            error_log('madamecho formulaire : retenue non enregistrée (' . get_class($e) . ')');
            return ['ok' => false, 'envoyee' => true, 'ref' => (string)$d['ref'], 'message' => 'Le paiement en ligne n\'a pas pu commencer, et rien n\'a été débité. Votre demande est bien arrivée chez Madame Cho : elle vous répond sous 24 heures.'];
        }
        return ['ok' => true, 'commande' => $id, 'url' => $url, 'suivi' => $suivi, 'jusqua' => date('c', (int)strtotime($jusqua))];
    } finally {
        flock($verrou, LOCK_UN);
        fclose($verrou);
    }
}
/** Une commande du temps 2 qui ne sera pas payée en ligne devient une demande ordinaire (FR-032) : Madame Cho reçoit le mail habituel
 *  d'une demande, et le client l'accusé de réception (exception à R-01 du 11/11/2026). */
function demande_sans_paiement(int $id, string $pourquoi): void {
    try { mail_interne_paiement($id, 'non_abouti', $pourquoi); } catch (\Throwable $e) { error_log('madamecho gestion paiement : mail interne non envoyé'); }
    try { accuser_reception_commande($id); } catch (\Throwable $e) { error_log('madamecho gestion paiement : accusé non envoyé'); }
}
/** Le paiement d'une commande du temps 2 n'a pas abouti (retenue échue, lien expiré, client qui préfère payer au retrait) : lien désactivé,
 *  place libérée, commande marquée « paiement non abouti » ; une seule fois (la retenue effacée sert de garde). */
function paiement_non_abouti(array $l, string $pourquoi, bool $desactiver = true): bool {
    if ($desactiver && in_array($l['etat'], ['open', 'processing'], true)) {
        try { lien_desactiver($l, $pourquoi, 'système'); }
        catch (\Throwable $e) { error_log('madamecho gestion paiement : lien ' . $l['id'] . ' non désactivé (' . $e->getMessage() . ')'); return false; }
        $l = lien((int)$l['id']) ?? $l;
        if ($l['paiement_qonto_id'] !== null) return false;   // payé entre-temps : constaté par la relecture
    }
    if (q("DELETE FROM retenues WHERE commande_id = ?", [(int)$l['commande_id']])->rowCount() === 0) return false;
    $k = commande((int)$l['commande_id']);
    if (!$k) return false;
    evenement('paiement_non_abouti', 'Paiement en ligne non abouti (' . $pourquoi . ') : demande à traiter comme les autres', ['commande' => (int)$k['id'], 'client' => (int)$k['client_id']], 'open', $l['etat'], 'système');
    if ($k['statut'] === 'demande') demande_sans_paiement((int)$k['id'], $pourquoi);
    return true;
}
/** Les retenues échues : chaque lien est relu (un paiement arrivé est constaté), sinon désactivé et la commande devient une demande. */
function paiement_retenues_echues(): void {
    foreach (lignes("SELECT l.* FROM retenues r JOIN liens_paiement l ON l.commande_id = r.commande_id AND l.origine = 'site' AND l.paiement_qonto_id IS NULL
                     WHERE r.jusqua <= ? ORDER BY r.jusqua LIMIT 20", [maintenant()]) as $l) {
        try {
            $l = lien_relire($l, true) ?? $l;
            if ($l['paiement_qonto_id'] !== null || $l['etat'] === 'processing') continue;   // payé, ou paiement en cours : on attend
            paiement_non_abouti($l, 'délai de paiement dépassé');
        } catch (\Throwable $e) { error_log('madamecho gestion paiement : retenue ' . $l['commande_id'] . ' : ' . $e->getMessage()); }
    }
    // Une retenue sans lien (lien jamais gardé) ne retient rien longtemps
    q("DELETE FROM retenues WHERE jusqua <= ? AND commande_id NOT IN (SELECT commande_id FROM liens_paiement WHERE paiement_qonto_id IS NULL AND etat IN ('open', 'processing'))", [date('Y-m-d H:i:s', time() - 3600)]);
}
/** La page de suivi du formulaire (suivi=<jeton>) : où en est le paiement, sans aucune donnée personnelle (FR-021). */
function paiement_suivi(string $jeton, bool $sansPayer = false): array {
    if (!preg_match('/^[0-9a-f]{32}$/', $jeton) || !gestion_base_existante()) return ['etat' => 'inconnu'];
    $l = ligne("SELECT * FROM liens_paiement WHERE suivi = ?", [$jeton]);
    if (!$l || strtotime((string)$l['cree_le']) < time() - 86400) return ['etat' => 'inconnu'];
    try {
        paiement_retenues_echues();
        $l = lien((int)$l['id']) ?? $l;
        if (in_array($l['etat'], ['open', 'processing'], true)) $l = lien_relire($l, $sansPayer) ?? $l;
        if ($sansPayer && $l['etat'] === 'open' && $l['paiement_qonto_id'] === null) {
            paiement_non_abouti($l, 'le client a choisi d\'envoyer sa demande sans payer');
            $l = lien((int)$l['id']) ?? $l;
        }
    } catch (\Throwable $e) { error_log('madamecho formulaire : suivi du paiement : ' . $e->getMessage()); }
    if ($l['paiement_qonto_id'] !== null && $l['recu_etat'] !== 'a_verifier')
        return ['etat' => 'paye', 'message' => 'Paiement reçu : votre commande est confirmée. Un mail de confirmation, qui vaut reçu, vous a été envoyé.'];
    if (in_array($l['etat'], ['open', 'processing'], true))
        return ['etat' => 'attente'] + ($sansPayer ? ['message' => 'Votre paiement est en cours : attendez quelques instants avant de changer d\'avis.'] : []);
    return ['etat' => 'non_abouti', 'message' => $sansPayer ? 'Votre demande est envoyée, sans paiement : Madame Cho vous répond sous 24 heures. Vous paierez au retrait.'
                                                            : 'Le paiement n\'a pas abouti, et rien n\'a été débité. Votre demande est quand même arrivée chez Madame Cho : elle vous répond sous 24 heures, et vous pourrez payer au retrait.'];
}

/* ---------- Mails internes à contact@ ---------- */
/** Le mail à Madame Cho quand une commande du site est payée en ligne, ou quand son paiement n'a pas abouti (elle redevient une demande). */
function mail_interne_paiement(int $id, string $quoi, string $pourquoi = ''): bool {
    $k = commande($id);
    if (!$k) return false;
    $nom = commande_client_nom($k);
    $quand = $k['retrait_date'] ? ucfirst(date_fr((string)$k['retrait_date'], 'jour')) . retrait_heure_phrase($k) : 'jour à convenir';
    if ($quoi === 'payee') {
        $sujet = 'Commande payée en ligne ' . $k['reference'] . ' : ' . $nom . ', retrait le ' . date('d/m', (int)strtotime((string)$k['retrait_date']));
        $tete = 'Nouvelle commande de la carte, payée en ligne sur le site madamecho.com : elle est déjà confirmée, sa place est prise dans la plage, et le client a reçu tout seul la confirmation qui vaut reçu. Rien à répondre : préparez-la.';
    } else {
        $sujet = 'Demande ' . $k['reference'] . ' : ' . $nom . ', retrait le ' . ($k['retrait_date'] ? date('d/m', (int)strtotime((string)$k['retrait_date'])) : '?') . ' (paiement en ligne non abouti)';
        $tete = 'Nouvelle demande reçue par le site madamecho.com. Le client avait choisi de payer en ligne, mais le paiement n\'a pas abouti (' . $pourquoi . ') : rien n\'a été débité. C\'est une demande ordinaire, à traiter comme les autres (proposition, puis confirmation).';
    }
    $t = [$tete, '', 'Référence : ' . $k['reference'], '', 'RETRAIT', $quand, '', 'GÂTEAUX', lignes_email($k), 'Total : ' . euros(montant_commande($k)) . ' TTC' . ($quoi === 'payee' ? ', payé en ligne' : ''), '',
          'CLIENT', $nom, 'Email : ' . $k['client_email']];
    if ((string)$k['client_tel'] !== '') $t[] = 'Téléphone : ' . $k['client_tel'];
    $t[] = '';
    $t[] = 'Dans la gestion : https://' . GESTION_DOMAINE . '/gestion/?p=commande&ref=' . $k['reference'];
    $t[] = $quoi === 'payee' ? '관리 화면에서 확인해 주세요. 이미 확정되고 결제된 주문이에요.' : '관리 화면에서 다른 문의처럼 처리해 주세요. 온라인 결제는 되지 않았어요.';
    return envoyer_email(GESTION_COMPTE_INITIAL, $sujet, implode("\n", $t));
}

/* ---------- La notification signée de Qonto (?p=qonto-notification, sans session) ---------- */
/** L'en-tête X-Qonto-Signature (« t=…,v1=… ») : HMAC-SHA256 de « t.corps brut » avec le secret de l'abonnement, horodatage à moins
 *  de 10 minutes de $maintenant (0 : pas de contrôle de l'heure, pour l'essai sur l'exemple publié par Qonto). */
function qonto_signature_valide(string $entete, string $corps, string $secret, int $maintenant): bool {
    if ($secret === '' || strlen($entete) > 1000) return false;
    $t = ''; $v1 = [];
    foreach (explode(',', $entete) as $part) {
        [$c, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($c === 't') $t = $v;
        elseif ($c === 'v1' && preg_match('/^[0-9a-f]{64}$/', $v)) $v1[] = $v;
    }
    if (!ctype_digit($t) || !$v1) return false;
    if ($maintenant > 0 && abs($maintenant - (int)$t) > 600) return false;
    $attendu = hash_hmac('sha256', $t . '.' . $corps, $secret);
    foreach ($v1 as $v) if (hash_equals($attendu, $v)) return true;
    return false;
}
/** Le lien nommé par une notification (enveloppe de Qonto : organization_id en haut, data.payment_link_id), s'il est connu de la gestion. */
function notification_lien(array $j): ?array {
    $org = (string)($j['organization_id'] ?? '');
    if ($org !== '' && $org !== (string)paiement_org()) return null;
    $id = (string)($j['data']['payment_link_id'] ?? ($j['payment_link_id'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9\-]{8,64}$/', $id)) return null;
    return ligne("SELECT * FROM liens_paiement WHERE qonto_id = ?", [$id]);
}
function page_qonto_notification(): never {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $fin = function (int $code, string $journal): never {
        http_response_code($code);
        echo $code === 200 ? '{"ok":true}' : '{}';
        error_log('madamecho gestion : notification Qonto ' . $journal);
        exit;
    };
    $brut = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? file_get_contents('php://input', false, null, 0, 65537) : false;
    if ($brut === false || $brut === '' || strlen($brut) > 65536) $fin(400, 'refusée (méthode ou taille)');
    if (!qonto_signature_valide((string)($_SERVER['HTTP_X_QONTO_SIGNATURE'] ?? ''), $brut, (string)parametre('qonto_notification_secret', ''), time())) $fin(401, 'refusée (signature ou heure)');
    $j = json_decode($brut, true);
    $l = is_array($j) ? notification_lien($j) : null;
    if (!$l) $fin(200, 'sans effet (lien inconnu)');
    // Réponse tout de suite (Qonto attend moins d'une seconde), puis la relecture chez Qonto : seule elle compte (FR-010, FR-033)
    ignore_user_abort(true);
    http_response_code(200);
    $r = '{"ok":true}';
    header('Content-Length: ' . strlen($r));
    header('Connection: close');
    echo $r;
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else { while (ob_get_level() > 0) ob_end_flush(); flush(); }
    try {
        $avant = $l['etat'];
        $l = lien_relire($l, true) ?? $l;
        if ($l['origine'] === 'site' && $l['paiement_qonto_id'] === null) paiement_retenues_echues();
        error_log('madamecho gestion : notification Qonto acceptée (lien ' . $l['id'] . ', ' . $avant . ' → ' . $l['etat'] . ')');
    } catch (\Throwable $e) {
        error_log('madamecho gestion : notification Qonto acceptée, relecture impossible (lien ' . $l['id'] . ') : ' . $e->getMessage());
    }
    exit;
}
/** L'adresse que Qonto appelle. */
function paiement_adresse_notification(): string { return GESTION_ESSAI ? gestion_adresse() . '?p=qonto-notification' : 'https://' . GESTION_DOMAINE . '/gestion/?p=qonto-notification'; }
/** S'abonne aux notifications des liens de paiement (Paramètres, au toucher de Youngmi) : secret de 64 caractères tiré au hasard, gardé
 *  dans la base, jamais montré. Un ancien abonnement est retiré d'abord. */
function paiement_abonner(): void {
    $org = paiement_org();
    if ($org === null) throw new \RuntimeException('reliez d\'abord Qonto avec les droits des liens de paiement');
    paiement_desabonner();
    $secret = bin2hex(random_bytes(32));
    $j = qonto_ecrire($org, 'POST', 'v2/webhook_subscriptions', ['callback_url' => paiement_adresse_notification(), 'types' => ['v1/payment-links'], 'secret' => $secret,
                                                                  'description' => 'Gestion Madame Cho : paiements en ligne']);
    $s = is_array($j['webhook_subscription'] ?? null) ? $j['webhook_subscription'] : $j;
    $id = (string)($s['id'] ?? '');
    if (!preg_match('/^[A-Za-z0-9\-]{8,64}$/', $id)) throw new \RuntimeException('réponse inattendue de Qonto (abonnement)');
    $rendu = (string)($s['secret'] ?? '');
    parametre_ecrire('qonto_notification_id', $id);
    parametre_ecrire('qonto_notification_secret', strlen($rendu) >= 32 ? $rendu : $secret);
    parametre_ecrire('qonto_notification_le', maintenant());
    evenement('reglage', 'Paiement en ligne : abonnement aux notifications de Qonto activé');
}
function paiement_desabonner(): void {
    $id = (string)parametre('qonto_notification_id', '');
    $org = paiement_org();
    if ($id !== '' && $org !== null) {
        try { qonto_ecrire($org, 'DELETE', 'v2/webhook_subscriptions/' . $id, null, 10); }
        catch (\Throwable $e) { error_log('madamecho gestion paiement : ancien abonnement non retiré (' . $e->getMessage() . ')'); }
    }
    parametre_ecrire('qonto_notification_id', '');
    parametre_ecrire('qonto_notification_secret', '');
}

/* ---------- Paramètres › Paiement en ligne (T022) ---------- */
/** Le bloc des réglages, administratrice seulement : les étapes dans l'ordre, puis les deux interrupteurs (éteints au départ). */
function bloc_reglages_paiement(): string {
    $portees = parametre('paiement_portees', '0') === '1';
    $org = paiement_org();
    $espaces = qonto_espaces();
    $abonne = (string)parametre('qonto_notification_secret', '') !== '';
    $etape = fn(bool $fait, string $texte) => '<li' . ($fait ? ' class="fait"' : '') . '>' . ($fait ? icone('valider') . ' ' : '') . $texte . '</li>';
    $t = '<p>' . h('Le client peut payer sa commande en ligne, par carte bancaire ou Apple Pay, grâce aux liens de paiement de Qonto (Mollie). La gestion peut seulement créer et annuler ces liens : ni virement, ni carte, ni facture. Les deux interrupteurs restent éteints tant que vous ne les allumez pas.') . '</p>'
       . '<ol class="aide marche">'
       . $etape($portees, h('Portail développeurs de Qonto, application « Gestion Madame Cho » : ajoutez les droits payment_link.read, payment_link.write et webhook, puis cochez ci-dessous.')
           . formulaire('paiement_portees', case_a_cocher('portees', h('Les droits sont ajoutés dans le portail'), $portees) . bouton('Enregistrer', 'discret')))
       . $etape($org !== null, h($org !== null ? 'Espace relié avec ces droits : ' . ($espaces[$org]['nom'] ?? $org) . '.' : 'Reliez de nouveau l\'espace « Madame CHO » (cadre Qonto ci-dessus) : Qonto demande d\'accepter les nouveaux droits.'))
       . $etape($abonne, $abonne ? h('Notifications de Qonto actives depuis le ' . date_fr((string)parametre('qonto_notification_le', ''), 'heure') . '.')
                                 . formulaire('paiement_desactiver', bouton('Couper le paiement en ligne', 'discret'), ['data-confirmer' => 'Couper le paiement en ligne ? Les deux interrupteurs s\'éteignent ; les liens déjà envoyés restent payables jusqu\'à leur échéance.'])
                               : h('Activez les notifications : Qonto préviendra la gestion à chaque paiement.') . ($org !== null ? formulaire('paiement_activer', bouton('Activer le paiement en ligne', 'secondaire')) : ''))
       . '</ol>';
    $pret = paiement_pret();
    $t1 = parametre('paiement_temps1', '0') === '1'; $t2 = parametre('paiement_temps2', '0') === '1';
    [$pc, $fixe] = paiement_bareme();
    $t .= '<h3>' . h('Interrupteurs et réglages') . '</h3>'
        . (!$pret ? '<p class="aide">' . h('Les interrupteurs n\'ont d\'effet qu\'une fois les trois étapes faites.') . '</p>' : '')
        . formulaire('paiement_reglages',
            case_a_cocher('temps1', h('Bouton « Payer en ligne » dans la confirmation (commandes de particuliers confirmées)'), $t1)
            . case_a_cocher('temps2', h('« Commander et payer » sur le site (gâteaux de la carte, plage libre)'), $t2)
            . (parametre('demandes_site') !== '1' ? '<p class="aide">' . h('« Commander et payer » attend aussi que les demandes du site s\'enregistrent dans la gestion.') . '</p>' : '')
            . '<div class="ligne-champs">'
            . champ('minimum', 'Montant minimum (€)', number_format(paiement_minimum() / 100, 2, ',', ''), ['inputmode' => 'decimal', 'required' => true])
            . champ('retenue', 'Place retenue pendant le paiement (minutes)', (string)paiement_retenue_minutes(), ['type' => 'number', 'min' => '10', 'max' => '60', 'step' => '1', 'inputmode' => 'numeric', 'required' => true])
            . '</div><div class="ligne-champs">'
            . champ('bareme_pc', 'Frais estimés : pourcentage', number_format($pc / 100, 2, ',', ''), ['inputmode' => 'decimal', 'required' => true])
            . champ('bareme_fixe', 'Frais estimés : fixe par paiement (€)', number_format($fixe / 100, 2, ',', ''), ['inputmode' => 'decimal', 'required' => true])
            . '</div><p class="aide">' . h('Le barème sert seulement à estimer les frais de chaque paiement ; les frais réels se lisent sur les versements de Mollie (Statistiques).') . '</p>'
            . bouton('Enregistrer', 'secondaire'));
    return bloc('Paiement en ligne', $t, '', 'paiement-en-ligne');
}
/** Les actions du bloc ; administratrice seulement (vérifié par l'appelant). Renvoie l'ancre. */
function reglages_paiement_action(string $a): string {
    switch ($a) {
        case 'paiement_portees':
            $v = !empty($_POST['portees']) ? '1' : '0';
            if ($v !== parametre('paiement_portees', '0')) evenement('reglage', 'Paiement en ligne : droits des liens ' . ($v === '1' ? 'demandés à Qonto' : 'plus demandés'));
            parametre_ecrire('paiement_portees', $v);
            flash('ok', $v === '1' ? 'Noté. Reliez maintenant de nouveau l\'espace « Madame CHO » dans le cadre Qonto : Qonto demandera d\'accepter les nouveaux droits.' : 'Noté : la gestion ne demandera plus les droits des liens.');
            break;
        case 'paiement_activer':
            try { paiement_abonner(); flash('ok', 'Paiement en ligne activé chez Qonto. Allumez maintenant le ou les interrupteurs.'); }
            catch (\Throwable $e) { error_log('madamecho gestion paiement : abonnement : ' . $e->getMessage()); flash('erreur', 'Qonto n\'a pas accepté (' . $e->getMessage() . ') : rien n\'a changé.'); }
            break;
        case 'paiement_desactiver':
            paiement_desabonner();
            parametre_ecrire('paiement_temps1', '0');
            parametre_ecrire('paiement_temps2', '0');
            evenement('reglage', 'Paiement en ligne coupé (notifications retirées, interrupteurs éteints)');
            flash('ok', 'Paiement en ligne coupé. Les liens déjà envoyés restent payables jusqu\'à leur échéance ; la gestion les relit chaque heure.');
            break;
        case 'paiement_reglages':
            $min = centimes(texte('minimum', 12));
            $ret = entier('retenue', 0, 999, 0);
            $pc = centimes(texte('bareme_pc', 12));
            $fixe = centimes(texte('bareme_fixe', 12));
            if ($min === null || $min < 100 || $min > 100000 || $ret < 10 || $ret > 60 || $pc === null || $pc < 0 || $pc > 1000 || $fixe === null || $fixe < 0 || $fixe > 500) {
                flash('erreur', 'Rien n\'a été changé : minimum entre 1 et 1 000 €, retenue entre 10 et 60 minutes, pourcentage entre 0 et 10, fixe entre 0 et 5 €.');
                break;
            }
            $avant = [parametre('paiement_temps1', '0'), parametre('paiement_temps2', '0')];
            $apres = [!empty($_POST['temps1']) ? '1' : '0', !empty($_POST['temps2']) ? '1' : '0'];
            parametre_ecrire('paiement_temps1', $apres[0]);
            parametre_ecrire('paiement_temps2', $apres[1]);
            parametre_ecrire('paiement_minimum', (string)$min);
            parametre_ecrire('paiement_retenue_minutes', (string)$ret);
            parametre_ecrire('paiement_bareme', $pc . ',' . $fixe);
            if ($avant !== $apres) evenement('reglage', 'Paiement en ligne : bouton de la confirmation ' . ($apres[0] === '1' ? 'allumé' : 'éteint') . ', « Commander et payer » ' . ($apres[1] === '1' ? 'allumé' : 'éteint'));
            flash('ok', 'Enregistré.' . (($apres[0] === '1' || $apres[1] === '1') && !paiement_pret() ? ' Les interrupteurs agiront une fois les trois étapes faites.' : ''));
            break;
    }
    return 'paiement-en-ligne';
}

/* ---------- Tâche planifiée ---------- */
/** Relit les liens ouverts (paiement constaté même sans notification), traite les retenues échues ; chaque heure si la tâche planifiée
 *  d'OVH tourne toutes les heures. Sans effet tant que rien n'est prêt. */
function paiement_tache(): array {
    if (!gestion_base_existante() || paiement_org() === null) return [];
    $cr = ['relus' => 0, 'erreurs' => 0];
    paiement_retenues_echues();
    foreach (lignes("SELECT * FROM liens_paiement WHERE etat IN ('open', 'processing') AND paiement_qonto_id IS NULL AND maj_le < ? ORDER BY maj_le LIMIT 50", [date('Y-m-d H:i:s', time() - 600)]) as $l) {
        try { lien_relire($l, true); $cr['relus']++; } catch (\Throwable $e) { $cr['erreurs']++; error_log('madamecho gestion paiement : lien ' . $l['id'] . ' non relu : ' . $e->getMessage()); }
    }
    // Les liens des commandes disparues (corbeille vidée) : 40 jours après, plus rien ne les garde
    q("DELETE FROM liens_paiement WHERE commande_id NOT IN (SELECT id FROM commandes) AND cree_le < ?", [date('Y-m-d H:i:s', time() - 40 * 86400)]);
    return $cr;
}

/* ---------- Rembourser ---------- */
/** Une commande payée en ligne puis annulée (ou payée deux fois) : à rembourser en entier par Youngmi dans Mollie (FR-016 à FR-018). */
function a_rembourser(array $k): int {
    if (($k['paiement'] ?? '') === 'rembourse') return 0;
    $l = lien_paye((int)$k['id']);
    if (!$l || $l['rembourse_le'] !== null || $l['recu_etat'] === 'a_verifier') return 0;
    $double = $k['moyen'] !== 'en_ligne' && $k['paiement'] === 'paye';
    return ($k['statut'] === 'annulee' || $double) ? (int)($l['paye_montant'] ?? $l['montant']) : 0;
}
function rembourse_noter(int $id): string {
    $k = commande($id);
    $l = $k ? lien_paye($id) : null;
    if (!$k || !$l || !a_rembourser($k)) return 'Rien à rembourser sur cette commande : rechargez la page.';
    $now = maintenant();
    q("UPDATE liens_paiement SET rembourse_le = ?, rembourse_par = ? WHERE id = ?", [$now, auteur(), (int)$l['id']]);
    if ($k['statut'] === 'annulee') commande_paiement($id, 'rembourse', 'en_ligne', null, (string)$k['facture']);
    evenement('paiement_en_ligne', 'Paiement en ligne remboursé dans Mollie : ' . euros((int)($l['paye_montant'] ?? $l['montant'])), ['commande' => $id, 'client' => (int)$k['client_id']], '', 'rembourse');
    return '';
}

/* ---------- Écrans de la commande ---------- */
/** Le bloc « Paiement en ligne » de la commande (FR-004) : état des liens, copier, annuler, reçu, « À rembourser ». */
function bloc_paiement_en_ligne(array $k): string {
    $liens = gestion_base_existante() ? liens_de((int)$k['id']) : [];
    $temps1 = paiement_temps(1);
    if (!$liens && !$temps1) return '';
    $id = (int)$k['id'];
    $t = '';
    $retenue = ligne("SELECT * FROM retenues WHERE commande_id = ? AND jusqua > ?", [$id, maintenant()]);
    if ($retenue) $t .= '<p class="erreur">' . icone('alerte') . ' ' . h('Le client est en train de payer en ligne sur le site (place retenue jusqu\'à ' . substr((string)$retenue['jusqua'], 11, 5) . ') : attendez avant de lui répondre.') . '</p>';
    if (($r = a_rembourser($k)) > 0) {
        $t .= '<div class="a-rembourser"><p><b>' . h('À rembourser : ' . euros($r)) . '</b> ' . h($k['statut'] === 'annulee' ? '(payée en ligne puis annulée : Madame Cho ne garde rien).' : '(payée deux fois).') . '</p>'
            . '<p class="aide">' . h(PAIEMENT_ETAPES_MOLLIE) . '</p>'
            . formulaire('rembourse_noter', bouton('J\'ai remboursé dans Mollie', 'principal'), ['data-confirmer' => 'Le remboursement de ' . euros($r) . ' est bien fait dans Mollie ?']) . '</div>';
    }
    $rows = '';
    foreach (array_slice($liens, 0, 5) as $l) {
        $etat = PAIEMENT_ETATS[$l['etat']] ?? $l['etat'];
        $quoi = '<b>' . h(euros((int)$l['montant'])) . '</b> · ' . h($etat) . ' · ' . h(($l['origine'] === 'site' ? 'payé sur le site' : 'lien créé') . ' le ' . date_fr((string)$l['cree_le'], 'heure'));
        $suite = '';
        if ($l['paiement_qonto_id'] !== null) {
            $quoi = '<b>' . h(euros((int)($l['paye_montant'] ?? $l['montant']))) . '</b> · ' . h($l['recu_etat'] === 'a_verifier' ? 'Paiement à vérifier' : 'Payé le ' . date_fr((string)$l['paye_le'], 'heure'))
                  . ((string)$l['moyen_detail'] !== '' ? ' · ' . h(PAIEMENT_MOYENS_QONTO[$l['moyen_detail']] ?? '') : '');
            if ($l['frais_estimes'] !== null) $suite .= '<p class="petit">' . h('Frais estimés : ' . euros((int)$l['frais_estimes']) . ' (barème des Paramètres ; les frais réels sont retenus sur les versements de Mollie)') . '</p>';
            if ($l['rembourse_le'] !== null) $suite .= '<p class="petit">' . h('Remboursé dans Mollie, noté le ' . date_fr((string)$l['rembourse_le'], 'heure') . ' par ' . $l['rembourse_par']) . '</p>';
            $recu = match ((string)$l['recu_etat']) {
                'envoye' => 'Reçu envoyé le ' . date_fr((string)$l['recu_envoye_le'], 'heure') . '.',
                'echec' => 'Le reçu n\'est pas parti (souci d\'envoi).',
                'sans_email' => 'Pas d\'email pour ce client : pas de reçu automatique.',
                'a_verifier' => '',
                default => 'Reçu en cours d\'envoi.',
            };
            if ($recu !== '') $suite .= '<p class="petit">' . h($recu) . '</p>';
            if (in_array($l['recu_etat'], ['envoye', 'echec', 'reserve'], true) && email_valide((string)$k['client_email']))
                $suite .= formulaire('recu_renvoyer', '<input type="hidden" name="lien" value="' . (int)$l['id'] . '">' . bouton($l['recu_etat'] === 'echec' ? 'Envoyer le reçu' : 'Renvoyer le reçu', 'secondaire'),
                                     ['data-confirmer' => 'Envoyer le reçu à ' . $k['client_email'] . ' ?']);
        } elseif (in_array($l['etat'], ['open', 'processing'], true)) {
            $suite .= '<p class="petit" translate="no" id="lien-' . (int)$l['id'] . '">' . h($l['url']) . '</p>'
                    . '<div class="actions"><button type="button" class="btn secondaire" data-copier="lien-' . (int)$l['id'] . '">' . icone('copier') . 'Copier le lien</button>'
                    . ($l['etat'] === 'open' ? formulaire('lien_annuler', '<input type="hidden" name="lien" value="' . (int)$l['id'] . '">' . bouton('Annuler le lien', 'discret'), ['data-confirmer' => 'Annuler ce lien ? Le client ne pourra plus payer avec.']) : '') . '</div>'
                    . '<p class="aide">' . h('Pour un client sans email : copiez le lien et envoyez-le par SMS ou WhatsApp depuis votre téléphone.') . '</p>';
        }
        $rows .= '<li><p>' . $quoi . '</p>' . $suite . '</li>';
    }
    if ($rows !== '') $t .= '<ul class="liste-simple liens-paiement">' . $rows . '</ul>';
    if ($temps1 && !lien_ouvert($id) && !lien_paye($id)) {
        $refus = lien_refus($k);
        $t .= $refus === '' ? '<p class="aide">' . h('Le bouton « Payer en ligne » (' . euros(montant_du($k)) . ') part avec la confirmation. Pour un client sans email, créez le lien et copiez-le.') . '</p>'
                             . formulaire('lien_creer', bouton('Créer le lien de paiement', 'secondaire'))
                           : ($k['statut'] === 'confirmee' ? '<p class="aide">' . h('Pas de paiement en ligne pour cette commande : ' . $refus . '.') . '</p>' : '');
    }
    return $t === '' ? '' : '<div class="paiement-en-ligne" id="paiement-en-ligne"><h3>' . h('Paiement en ligne') . '</h3>' . $t . '</div>';
}
/** Les actions du bloc ; renvoie le message du bandeau. Youngmi seulement (l'assistant ne touche jamais aux liens, FR-025). */
function paiement_action(string $a, array $k): array {
    $id = (int)$k['id'];
    $lien = fn() => ($l = lien(entier('lien', 1))) && (int)$l['commande_id'] === $id ? $l : null;
    try {
        switch ($a) {
            case 'lien_creer':
                if (!paiement_temps(1) || ($r = lien_refus($k)) !== '') return ['erreur', 'Pas de lien pour cette commande' . (isset($r) && $r !== '' ? ' : ' . $r : '') . '.'];
                lien_pour_commande($k);
                return ['ok', 'Lien de paiement créé : copiez-le dans « Paiement en ligne ».'];
            case 'lien_annuler':
                $l = $lien();
                if (!$l || $l['etat'] !== 'open') return ['erreur', 'Ce lien n\'est plus ouvert : rechargez la page.'];
                lien_desactiver($l, 'annulé par ' . auteur());
                $l = lien((int)$l['id']);
                return $l && $l['paiement_qonto_id'] !== null ? ['erreur', 'Trop tard : le client venait de payer. Le paiement est noté.'] : ['ok', 'Lien annulé : le client ne peut plus payer avec.'];
            case 'recu_renvoyer':
                $l = $lien();
                if (!$l || $l['paiement_qonto_id'] === null) return ['erreur', 'Pas de paiement en ligne à reprendre : rechargez la page.'];
                $r = recu_envoyer((int)$l['id'], auteur());
                return $r === 'envoye' ? ['ok', 'Reçu envoyé au client. Une copie arrive dans contact@madamecho.com.'] : ['erreur', 'Le reçu n\'est pas parti : réessayez dans un moment.'];
            case 'rembourse_noter':
                $e = rembourse_noter($id);
                return $e === '' ? ['ok', 'Remboursement noté.'] : ['erreur', $e];
        }
    } catch (\Throwable $e) {
        error_log('madamecho gestion paiement : ' . $a . ' : ' . $e->getMessage());
        return ['erreur', 'Qonto n\'a pas répondu (' . $e->getMessage() . ') : rien n\'a changé, réessayez dans un moment.'];
    }
    return ['erreur', ''];
}
/** À l'ouverture de la commande : un lien ouvert est relu au plus une fois par minute (T011). */
function paiement_relire_commande(int $id): void {
    if (!gestion_base_existante()) return;
    $l = lien_ouvert($id);
    if (!$l) return;
    try { lien_relire($l, false, 60); } catch (\Throwable $e) { error_log('madamecho gestion paiement : relecture à l\'ouverture : ' . $e->getMessage()); }
}
/** Statistiques : les paiements en ligne par mois (nombre, montant, frais estimés) et les versements de Mollie lus dans Qonto (T020).
 *  Les frais réels sont la différence entre ce qui a été payé et ce que Mollie a versé, avec quelques jours de décalage. */
function paiement_statistiques(): string {
    if (!gestion_base_existante()) return '';
    $mois = [];
    foreach (lignes("SELECT substr(paye_le, 1, 7) AS m, COUNT(*) AS n, SUM(COALESCE(paye_montant, montant)) AS total, SUM(COALESCE(frais_estimes, 0)) AS frais,
                            SUM(CASE WHEN rembourse_le IS NOT NULL THEN COALESCE(paye_montant, montant) ELSE 0 END) AS rembourse
                     FROM liens_paiement WHERE paiement_qonto_id IS NOT NULL AND recu_etat <> 'a_verifier' GROUP BY m") as $x)
        $mois[(string)$x['m']] = ['n' => (int)$x['n'], 'total' => (int)$x['total'], 'frais' => (int)$x['frais'], 'rembourse' => (int)$x['rembourse'], 'verse' => 0];
    foreach (function_exists(__NAMESPACE__ . '\\rapprochement_ops_gardees') ? rapprochement_ops_gardees() : [] as $o) {
        if (($o['genre'] ?? '') !== 'en_ligne') continue;
        $m = substr((string)$o['jour'], 0, 7);
        $mois[$m] = ($mois[$m] ?? ['n' => 0, 'total' => 0, 'frais' => 0, 'rembourse' => 0, 'verse' => 0]);
        $mois[$m]['verse'] += (int)$o['centimes'];
    }
    if (!$mois) return '';
    krsort($mois);
    $rows = '';
    foreach (array_slice($mois, 0, 12, true) as $m => $x) {
        $rows .= '<tr><td>' . h(ucfirst(MOIS[(int)substr($m, 5, 2)]) . ' ' . substr($m, 0, 4)) . '</td><td class="nombre">' . $x['n'] . '</td>'
               . '<td class="nombre">' . h(euros($x['total'])) . '</td><td class="nombre">' . h(euros($x['frais'])) . '</td>'
               . '<td class="nombre">' . h($x['rembourse'] ? euros($x['rembourse']) : '—') . '</td><td class="nombre">' . h($x['verse'] ? euros($x['verse']) : '—') . '</td></tr>';
    }
    return bloc('Paiements en ligne', '<div class="defile"><table class="tableau"><thead><tr><th>Mois</th><th class="nombre">Paiements</th><th class="nombre">Payé TTC</th>'
        . '<th class="nombre">Frais estimés</th><th class="nombre">Remboursé</th><th class="nombre">Versé par Mollie</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
        . '<p class="aide">' . h('Mollie verse les paiements quelques jours plus tard, frais déduits : la différence entre « Payé » et « Versé » donne les frais réels, à quelques jours près. Les versements viennent de la dernière lecture de Qonto.') . '</p>', '', 'paiements-en-ligne');
}
/** Pour l'assistant (FR-025) : où en est le paiement en ligne, en clair, sans adresse de lien ni donnée du payeur. */
function paiement_en_ligne_texte(array $k): string {
    if (!gestion_base_existante()) return '';
    $l = liens_de((int)$k['id'])[0] ?? null;
    if (!$l) return '';
    if ($l['paiement_qonto_id'] !== null) return 'payée en ligne le ' . date_fr((string)$l['paye_le'], 'heure') . ' (' . euros((int)($l['paye_montant'] ?? $l['montant'])) . ')' . (($r = a_rembourser($k)) ? ', à rembourser ' . euros($r) . ' dans Mollie' : '') . ($l['rembourse_le'] !== null ? ', remboursée' : '');
    return 'lien de paiement ' . mb_strtolower(PAIEMENT_ETATS[$l['etat']] ?? $l['etat']) . ' (' . euros((int)$l['montant']) . ')';
}

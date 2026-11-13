<?php
/*
 * Madame Cho : espace de gestion. La liaison avec Qonto, EN LECTURE SEULE (R-24 : jamais de virement, rien n'est écrit dans Qonto).
 * Demande d'Olivier du 07/11/2026 (« Oui, les deux étapes ») ; branchement le 10/11/2026 à la réouverture du portail développeurs.
 *
 * Une application OAuth du portail développeurs de Qonto (cas d'usage « Automate your business operations » : pas d'examen
 * par Qonto), créée avec le compte de Youngmi. Portées demandées : organization.read (comptes et opérations),
 * client_invoices.read (factures) et offline_access (rester relié) ; aucune portée d'écriture ni de paiement.
 * On relie chaque espace Qonto à part : « MADAME CHO » (Qonto Facturation) et « Madame CHO » (compte pro, Tap to Pay).
 *
 * L'identifiant et le secret de l'application, et les jetons de chaque espace, vivent dans la base (Paramètres), hors du site :
 * jamais dans ce dépôt public (R-08), jamais réaffichés, jamais dans l'export ni lus par l'assistant.
 * Le jeton d'accès dure 1 heure ; le jeton de renouvellement dure 90 jours et ne sert qu'une fois (chaque renouvellement en
 * donne un nouveau, enregistré tout de suite, sous verrou pour que deux pages ne renouvellent pas en même temps).
 * Ce que la gestion fait des données : proposer les encaissements lus dans Qonto au Rapprochement (Plus › Rapprochement Qonto).
 * Un encaissement ne crée jamais de vente ni de paiement : Youngmi choisit, d'un toucher, l'opération qui correspond.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

// En essai local (MC_ESSAI=1), MC_QONTO=http://127.0.0.1:8770/ pointe vers un faux Qonto.
define('GESTION_QONTO_OAUTH', GESTION_ESSAI && getenv('MC_QONTO') ? rtrim((string)getenv('MC_QONTO'), '/') . '/' : 'https://oauth.qonto.com/');
define('GESTION_QONTO_API', GESTION_ESSAI && getenv('MC_QONTO') ? rtrim((string)getenv('MC_QONTO'), '/') . '/' : 'https://thirdparty.qonto.com/');
const GESTION_QONTO_PORTEES = 'organization.read client_invoices.read offline_access';
// L'adresse de retour déclarée dans le portail de Qonto : elle doit y être recopiée exactement
const GESTION_QONTO_RETOUR = 'https://madamecho.com/gestion/?p=qonto-retour';
const GESTION_QONTO_JOURS = 45;          // encaissements proposés au rapprochement : les 45 derniers jours
const GESTION_QONTO_CACHE = 600;         // opérations gardées 10 minutes (moins d'appels à Qonto)
const GESTION_QONTO_RENOUVELER = 80;     // jours : au-delà, on prévient qu'il faudra bientôt relier à nouveau (le jeton en dure 90 sans usage)

function qonto_adresse_retour(): string { return GESTION_ESSAI ? gestion_adresse() . '?p=qonto-retour' : GESTION_QONTO_RETOUR; }
function qonto_application(): array { return [(string)parametre('qonto_client_id', ''), (string)parametre('qonto_client_secret', '')]; }
function qonto_application_prete(): bool { [$i, $s] = qonto_application(); return $i !== '' && $s !== ''; }
/** Les espaces reliés, par identifiant d'organisation Qonto. */
function qonto_espaces(): array { $e = json_decode((string)parametre('qonto_espaces', ''), true); return is_array($e) ? $e : []; }
function qonto_espaces_ecrire(array $e): void { parametre_ecrire('qonto_espaces', json_encode($e, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
function qonto_relie(): bool { foreach (qonto_espaces() as $e) if (($e['refresh'] ?? '') !== '') return true; return false; }

/* ---------- HTTP ---------- */
/** Un appel à Qonto. Renvoie [code HTTP, réponse décodée]. Le secret et les jetons ne passent que dans le corps ou l'en-tête. */
function qonto_http(string $methode, string $url, array $entetes = [], ?array $formulaire = null): array {
    if (!function_exists('curl_init')) throw new \RuntimeException('cURL manque sur ce serveur');
    $ch = curl_init($url);
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25,
          CURLOPT_HTTPHEADER => array_merge(['accept: application/json', 'user-agent: Gestion Madame Cho'], $entetes)];
    if ($methode === 'POST') { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = http_build_query($formulaire ?? [], '', '&', PHP_QUERY_RFC1738); }
    curl_setopt_array($ch, $o);
    $r = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if (!is_string($r)) {
        error_log('madamecho gestion qonto : ' . curl_error($ch));
        throw new \RuntimeException('impossible de joindre Qonto');
    }
    return [$code, json_decode($r, true)];
}
/** Une erreur de Qonto, en clair, sans jamais recopier un jeton ni le secret. */
function qonto_erreur(int $code, mixed $j): string {
    $e = is_array($j) ? (string)($j['error'] ?? ($j['errors'][0]['code'] ?? '')) : '';
    return match (true) {
        $e === 'invalid_client' => 'identifiant ou secret de l\'application refusé par Qonto',
        $e === 'invalid_grant' => 'autorisation expirée ou déjà utilisée : reliez l\'espace à nouveau',
        $e === 'invalid_scope' => 'droits refusés : vérifiez dans le portail que l\'application a bien organization.read, client_invoices.read et offline_access',
        $code === 401 => 'accès refusé par Qonto : reliez l\'espace à nouveau',
        $code === 403 => 'Qonto refuse cette lecture (droit manquant sur l\'application)',
        $code === 429 => 'trop de demandes à Qonto, réessayez dans une minute',
        $code >= 500 => 'Qonto a un souci de son côté, réessayez plus tard',
        default => 'réponse inattendue de Qonto (code ' . $code . ($e !== '' ? ', ' . preg_replace('/[^a-z_\-]/i', '', $e) : '') . ')',
    };
}
/** Demande des jetons (code reçu au retour, ou renouvellement). */
function qonto_jetons(array $demande): array {
    [$id, $secret] = qonto_application();
    [$code, $j] = qonto_http('POST', GESTION_QONTO_OAUTH . 'oauth2/token', ['content-type: application/x-www-form-urlencoded'],
                             $demande + ['client_id' => $id, 'client_secret' => $secret]);
    if ($code !== 200 || !is_array($j) || empty($j['access_token'])) throw new \RuntimeException(qonto_erreur($code, $j));
    return $j;
}

/* ---------- Relier un espace ---------- */
/** Envoie le navigateur chez Qonto ; Youngmi choisit l'espace et accepte la lecture. */
function qonto_demarrer(): never {
    [$id] = qonto_application();
    $etat = bin2hex(random_bytes(32));
    session_ouvrir();
    $_SESSION['qonto_etat'] = [$etat, time()];
    $url = GESTION_QONTO_OAUTH . 'oauth2/auth?' . http_build_query(['client_id' => $id, 'redirect_uri' => qonto_adresse_retour(), 'response_type' => 'code',
                                                                    'scope' => GESTION_QONTO_PORTEES, 'state' => $etat], '', '&', PHP_QUERY_RFC3986);
    header('Location: ' . $url, true, 302);
    exit;
}
/** ?p=qonto-relier&j=… : le bouton « Relier un espace Qonto » de Paramètres. */
function page_qonto_relier(): void {
    $j = is_string($_GET['j'] ?? null) ? $_GET['j'] : '';
    if (!est_admin() || !hash_equals(csrf(), $j)) rediriger('reglages', [], 'qonto');
    if (!qonto_application_prete()) { flash('erreur', 'Enregistrez d\'abord le Client ID et le Client Secret.'); rediriger('reglages', [], 'qonto'); }
    qonto_demarrer();
}
/** Le retour de Qonto (?p=qonto-retour) : vérifie l'état, échange le code, lit l'organisation et garde les jetons. */
function page_qonto_retour(): void {
    if (!est_admin()) rediriger('accueil');
    session_ouvrir();
    [$attendu, $quand] = is_array($_SESSION['qonto_etat'] ?? null) ? $_SESSION['qonto_etat'] : ['', 0];
    unset($_SESSION['qonto_etat']);
    $etat = is_string($_GET['state'] ?? null) ? $_GET['state'] : '';
    $code = is_string($_GET['code'] ?? null) ? $_GET['code'] : '';
    $refus = is_string($_GET['error'] ?? null) ? $_GET['error'] : '';
    if ($attendu === '' || !hash_equals($attendu, $etat) || time() - (int)$quand > 1800) {
        flash('erreur', 'Liaison Qonto interrompue : le retour ne correspond pas à la demande (ou plus de 30 minutes). Recommencez depuis Paramètres › Qonto.');
    } elseif ($refus !== '' || $code === '') {
        flash('erreur', 'Qonto n\'a pas donné l\'accès' . ($refus === 'access_denied' ? ' (refusé sur l\'écran de Qonto)' : '') . '. Rien n\'a été relié.');
    } else {
        try {
            $j = qonto_jetons(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => qonto_adresse_retour()]);
            $org = qonto_organisation_lire((string)$j['access_token']);
            $e = qonto_espaces();
            $e[$org['id']] = $org + ['access' => (string)$j['access_token'], 'expire' => time() + (int)($j['expires_in'] ?? 3600) - 60,
                                     'refresh' => (string)($j['refresh_token'] ?? ''), 'portees' => (string)($j['scope'] ?? ''),
                                     'relie_le' => maintenant(), 'relie_par' => auteur(), 'renouvele_le' => maintenant(), 'erreur' => ''];
            qonto_espaces_ecrire($e);
            parametre_ecrire('qonto_cache', '');
            evenement('reglage', 'Qonto relié en lecture seule : espace « ' . $org['nom'] . ' »');
            flash('ok', 'Espace Qonto « ' . $org['nom'] . ' » relié, en lecture seule.' . (($j['refresh_token'] ?? '') === '' ? ' Attention : Qonto n\'a pas donné de jeton durable (offline_access) : la liaison s\'arrêtera dans 1 heure.' : ''));
        } catch (\Throwable $ex) {
            error_log('madamecho gestion qonto retour : ' . $ex->getMessage());
            flash('erreur', 'Liaison Qonto non faite : ' . $ex->getMessage() . '.');
        }
    }
    rediriger('reglages', [], 'qonto');
}
/** L'organisation vue par ce jeton : nom et comptes (sans IBAN complet ni solde). */
function qonto_organisation_lire(string $jeton): array {
    [$code, $j] = qonto_http('GET', GESTION_QONTO_API . 'v2/organization', ['authorization: Bearer ' . $jeton]);
    $o = is_array($j) ? ($j['organization'] ?? null) : null;
    if ($code !== 200 || !is_array($o) || empty($o['id'])) throw new \RuntimeException(qonto_erreur($code, $j));
    $comptes = [];
    foreach (is_array($o['bank_accounts'] ?? null) ? $o['bank_accounts'] : [] as $b) {
        if (!is_array($b) || empty($b['id'])) continue;
        $comptes[] = ['id' => (string)$b['id'], 'nom' => mb_substr(une_ligne((string)($b['name'] ?? 'Compte')), 0, 80),
                      'iban_fin' => substr(preg_replace('/\s+/', '', (string)($b['iban'] ?? '')) ?? '', -4),
                      'externe' => !empty($b['is_external_account']), 'statut' => (string)($b['status'] ?? '')];
    }
    $nom = une_ligne((string)($o['legal_name'] ?? ($o['name'] ?? ($o['slug'] ?? 'Qonto'))));
    return ['id' => (string)$o['id'], 'nom' => mb_substr($nom, 0, 80), 'slug' => (string)($o['slug'] ?? ''), 'comptes' => $comptes];
}
function qonto_retirer(string $org): void {
    $e = qonto_espaces();
    if (!isset($e[$org])) return;
    $nom = (string)$e[$org]['nom'];
    unset($e[$org]);
    qonto_espaces_ecrire($e);
    parametre_ecrire('qonto_cache', '');
    evenement('reglage', 'Qonto : espace « ' . $nom . ' » retiré (jetons effacés)');
}

/* ---------- Lire ---------- */
/** Un jeton d'accès valable pour cet espace ; le renouvelle sous verrou si besoin (le jeton de renouvellement ne sert qu'une fois). */
function qonto_jeton(string $org, bool $forcer = false): string {
    $e = qonto_espaces()[$org] ?? null;
    if (!$e || ($e['refresh'] ?? '') === '' && ($e['expire'] ?? 0) <= time()) throw new \RuntimeException('espace Qonto pas relié');
    if (!$forcer && ($e['access'] ?? '') !== '' && ($e['expire'] ?? 0) > time()) return (string)$e['access'];
    $verrou = fopen(gestion_dossier() . '/qonto.verrou', 'c');
    if ($verrou === false) throw new \RuntimeException('verrou Qonto impossible');
    try {
        flock($verrou, LOCK_EX);
        $tous = qonto_espaces();   // relu sous le verrou : une autre page a peut-être déjà renouvelé
        $e = $tous[$org] ?? null;
        if (!$e) throw new \RuntimeException('espace Qonto pas relié');
        if (!$forcer && ($e['access'] ?? '') !== '' && ($e['expire'] ?? 0) > time()) return (string)$e['access'];
        try {
            $j = qonto_jetons(['grant_type' => 'refresh_token', 'refresh_token' => (string)$e['refresh']]);
        } catch (\Throwable $ex) {
            $tous[$org]['erreur'] = $ex->getMessage();
            qonto_espaces_ecrire($tous);
            throw $ex;
        }
        $tous[$org] = array_merge($e, ['access' => (string)$j['access_token'], 'expire' => time() + (int)($j['expires_in'] ?? 3600) - 60,
                                       'refresh' => (string)($j['refresh_token'] ?? $e['refresh']), 'renouvele_le' => maintenant(), 'erreur' => '']);
        qonto_espaces_ecrire($tous);
        return (string)$j['access_token'];
    } finally {
        flock($verrou, LOCK_UN);
        fclose($verrou);
    }
}
/** Une lecture dans un espace (GET seulement : la gestion n'écrit jamais dans Qonto). */
function qonto_lire(string $org, string $chemin, array $parametres = []): array {
    $url = GESTION_QONTO_API . $chemin . ($parametres ? '?' . http_build_query($parametres, '', '&', PHP_QUERY_RFC3986) : '');
    [$code, $j] = qonto_http('GET', $url, ['authorization: Bearer ' . qonto_jeton($org)]);
    if ($code === 401) [$code, $j] = qonto_http('GET', $url, ['authorization: Bearer ' . qonto_jeton($org, true)]);
    if ($code !== 200 || !is_array($j)) throw new \RuntimeException(qonto_erreur($code, $j));
    $tous = qonto_espaces();
    if (isset($tous[$org])) { $tous[$org]['lu_le'] = maintenant(); $tous[$org]['erreur'] = ''; qonto_espaces_ecrire($tous); }
    return $j;
}
/** Les encaissements des 45 derniers jours, sur les comptes Qonto (pas les comptes externes), du plus récent au plus ancien.
 *  Renvoie [opérations, erreurs]. Gardés 10 minutes. */
function qonto_encaissements(bool $frais = false): array {
    $cache = json_decode((string)parametre('qonto_cache', ''), true);
    if (!$frais && is_array($cache) && ($cache['le'] ?? 0) > time() - GESTION_QONTO_CACHE) return [$cache['ops'] ?? [], $cache['erreurs'] ?? []];
    $ops = []; $erreurs = [];
    $depuis = gmdate('Y-m-d\TH:i:s\Z', time() - GESTION_QONTO_JOURS * 86400);
    foreach (qonto_espaces() as $org => $e) {
        foreach ($e['comptes'] ?? [] as $c) {
            if (!empty($c['externe']) || ($c['statut'] ?? '') === 'closed') continue;
            try {
                $j = qonto_lire((string)$org, 'v2/transactions', ['bank_account_id' => $c['id'], 'side' => 'credit', 'settled_at_from' => $depuis,
                                                                  'sort_by' => 'settled_at:desc', 'per_page' => 100]);
                foreach (is_array($j['transactions'] ?? null) ? $j['transactions'] : [] as $t) {
                    if (!is_array($t) || empty($t['id'])) continue;
                    $ops[] = ['id' => (string)$t['id'], 'numero' => (string)($t['transaction_id'] ?? ''),
                              'centimes' => (int)($t['amount_cents'] ?? round((float)($t['amount'] ?? 0) * 100)),
                              'libelle' => mb_substr(une_ligne((string)($t['label'] ?? '')), 0, 80), 'reference' => mb_substr(une_ligne((string)($t['reference'] ?? '')), 0, 120),
                              'type' => (string)($t['operation_type'] ?? ''), 'le' => (string)($t['settled_at'] ?? ($t['emitted_at'] ?? '')),
                              'compte' => (string)$c['nom'], 'espace' => (string)$e['nom']];
                }
            } catch (\Throwable $ex) {
                $erreurs[] = $e['nom'] . ' : ' . $ex->getMessage();
            }
        }
    }
    usort($ops, fn($a, $b) => strcmp($b['le'], $a['le']));
    parametre_ecrire('qonto_cache', json_encode(['le' => time(), 'ops' => $ops, 'erreurs' => $erreurs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return [$ops, $erreurs];
}
/** Les factures clients impayées, par espace (pour l'essai de lecture dans Paramètres). */
function qonto_factures_impayees(string $org): array {
    $j = qonto_lire($org, 'v2/client_invoices', ['filter[status]' => 'unpaid', 'per_page' => 100]);
    $l = is_array($j['client_invoices'] ?? null) ? $j['client_invoices'] : [];
    $total = 0;
    foreach ($l as $f) $total += (int)round((float)($f['total_amount']['value'] ?? 0) * 100);
    return [(int)($j['meta']['total_count'] ?? count($l)), $total];
}
/** Libellé court d'un type d'opération Qonto. */
function qonto_type(string $t): string {
    return match ($t) {
        'card_acquirer_payout' => 'Versement Tap to Pay', 'transfer', 'income' => 'Virement reçu', 'card' => 'Carte',
        'direct_debit' => 'Prélèvement', 'qonto_fee' => 'Frais Qonto', default => $t !== '' ? $t : 'Opération',
    };
}

/* ---------- Écrans ---------- */
/** Le cadre « Qonto (lecture seule) » de Paramètres. */
function bloc_reglages_qonto(): string {
    $prete = qonto_application_prete();
    $espaces = qonto_espaces();
    $t = '<p>' . fr_ko('La gestion lit Qonto en lecture seule, pour retrouver les paiements des commandes. Elle ne peut faire aucun virement ni rien changer dans Qonto.',
                       '관리 화면은 주문 결제를 찾기 위해 Qonto를 읽기만 해요. 이체나 Qonto 안의 변경은 전혀 할 수 없어요.') . '</p>';
    if ($espaces) {
        $rows = '';
        foreach ($espaces as $org => $e) {
            $comptes = implode(', ', array_map(fn($c) => $c['nom'] . ($c['iban_fin'] !== '' ? ' (…' . $c['iban_fin'] . ')' : '') . (!empty($c['externe']) ? ' · externe' : ''), $e['comptes'] ?? []));
            $vieux = jours_depuis((string)($e['relie_le'] ?? '')) >= GESTION_QONTO_RENOUVELER;
            $rows .= '<li><p><b translate="no">' . h($e['nom']) . '</b></p><p class="petit">' . h($comptes !== '' ? $comptes : 'Aucun compte bancaire (facturation seulement)') . '</p>'
                   . '<p class="petit">' . h('Relié le ' . date_fr((string)$e['relie_le'], 'heure') . ' par ' . $e['relie_par'] . (($e['lu_le'] ?? '') !== '' ? ' · dernière lecture ' . date_fr((string)$e['lu_le'], 'heure') : '')) . '</p>'
                   . (($e['erreur'] ?? '') !== '' ? '<p class="erreur">' . h('Dernière erreur : ' . $e['erreur']) . '</p>' : '')
                   . ($vieux ? '<p class="aide">' . h('Reliez cet espace à nouveau bientôt : Qonto demande de redonner l\'accord tous les 90 jours.') . '</p>' : '')
                   . formulaire('qonto_retirer', '<input type="hidden" name="org" value="' . h((string)$org) . '">' . bouton('Retirer', 'discret'),
                                ['data-confirmer' => 'Retirer l\'espace Qonto « ' . $e['nom'] . ' » ? La gestion ne le lira plus.']) . '</li>';
        }
        $t .= '<ul class="liste-simple">' . $rows . '</ul>';
        $t .= formulaire('qonto_essai', bouton('Essayer la lecture', 'secondaire'))
            . '<p>' . lien_bouton(u('rapprochement'), 'Ouvrir le Rapprochement Qonto', 'discret') . '</p>';   // sur ordinateur, le menu du haut n'a pas « Plus »
    }
    if ($prete) {
        // Un lien et pas un formulaire : la règle form-action 'self' du site bloquerait la redirection d'un formulaire vers Qonto
        $t .= '<p>' . lien_bouton(u('qonto-relier', ['j' => csrf()]), $espaces ? 'Relier un autre espace Qonto' : 'Relier un espace Qonto', $espaces ? 'secondaire' : 'principal') . '</p>'
            . '<p class="aide">' . fr_ko('Une fois par espace Qonto : « Madame CHO » (compte pro et Tap to Pay) d\'abord ; « MADAME CHO » (factures) quand Qonto aura fini de le vérifier. Qonto demande de choisir l\'espace, puis la validation sur le téléphone de Youngmi.',
                                         'Qonto 공간마다 한 번씩: 먼저 « Madame CHO » (사업자 계좌와 Tap to Pay), « MADAME CHO » (청구서)는 Qonto 확인이 끝나면 연결해요. Qonto가 공간을 고르게 한 뒤 Youngmi 휴대폰으로 확인을 요청해요.') . '</p>';
    }
    $t .= '<details class="replie"' . ($prete ? '' : ' open') . '><summary>' . ($prete ? 'Remplacer l\'application Qonto' : 'Enregistrer l\'application Qonto') . '</summary>'
        . '<ol class="aide marche">'
        . '<li>Portail développeurs de Qonto, compte de Youngmi : application « Gestion Madame Cho », cas d\'usage « Automate your business operations », accès OAuth 2.0.</li>'
        . '<li>Droits : organization.read, client_invoices.read et offline_access, rien d\'autre.</li>'
        . '<li>Adresse de retour (Redirect URI) : <code translate="no">' . h(GESTION_QONTO_RETOUR) . '</code></li>'
        . '<li>Collez ici le Client ID et le Client Secret de Production. Le secret est gardé hors du site et jamais réaffiché.</li></ol>'
        . formulaire('qonto_application', champ('qonto_client_id', 'Client ID', (string)parametre('qonto_client_id', ''), ['autocomplete' => 'off', 'spellcheck' => 'false', 'autocapitalize' => 'off', 'required' => true, 'maxlength' => '200'])
            . champ('qonto_client_secret', 'Client Secret', '', ['type' => 'password', 'autocomplete' => 'new-password', 'spellcheck' => 'false', 'autocapitalize' => 'off', 'required' => true, 'maxlength' => '300'])
            . bouton('Enregistrer', 'secondaire'))
        . '</details>';
    return bloc('Qonto (lecture seule)', $t, '', 'qonto');
}
/** Les actions de Paramètres pour Qonto ; renvoie l'ancre où revenir. Administratrice seulement (vérifié par l'appelant). */
function reglages_qonto_action(string $a): string {
    switch ($a) {
        case 'qonto_application':
            $id = preg_replace('/\s+/', '', texte('qonto_client_id', 200)) ?? '';
            $secret = preg_replace('/\s+/', '', is_string($_POST['qonto_client_secret'] ?? null) ? mb_substr($_POST['qonto_client_secret'], 0, 300) : '') ?? '';
            if (!preg_match('/^[A-Za-z0-9._\-]{8,200}$/', $id) || !preg_match('/^[A-Za-z0-9._~\-]{8,300}$/', $secret)) {
                flash('erreur', 'Collez le Client ID et le Client Secret tels que le portail de Qonto les affiche, sans espace.');
                break;
            }
            if ($id !== (string)parametre('qonto_client_id', '')) { qonto_espaces_ecrire([]); parametre_ecrire('qonto_cache', ''); }   // autre application : les liaisons d'avant ne valent plus
            parametre_ecrire('qonto_client_id', $id);
            parametre_ecrire('qonto_client_secret', $secret);
            evenement('reglage', 'Qonto : application enregistrée (lecture seule)');
            flash('ok', 'Application Qonto enregistrée. Reliez maintenant chaque espace.');
            break;
        case 'qonto_retirer':
            qonto_retirer(texte('org', 80));
            flash('ok', 'Espace Qonto retiré. Pour couper aussi l\'accès côté Qonto : appli Qonto › Paramètres › Intégrations.');
            break;
        case 'qonto_essai':
            $l = [];
            foreach (qonto_espaces() as $org => $e) {
                try {
                    [$n, $total] = qonto_factures_impayees((string)$org);
                    $l[] = $e['nom'] . ' : ' . $n . ' facture(s) impayée(s), ' . euros($total);
                } catch (\Throwable $ex) { $l[] = $e['nom'] . ' : ' . $ex->getMessage(); }
            }
            [$ops, $err] = qonto_encaissements(true);
            $l[] = count($ops) . ' encaissement(s) sur ' . GESTION_QONTO_JOURS . ' jours';
            foreach ($err as $x) $l[] = $x;
            flash($err ? 'erreur' : 'ok', 'Lecture Qonto : ' . implode(' · ', $l) . '.');
            break;
    }
    return 'qonto';
}
/** Au Rapprochement : les encaissements lus dans Qonto, pas encore utilisés, à choisir d'un toucher. */
function choix_encaissements_qonto(): string {
    if (!qonto_relie()) return '';
    try { [$ops, $err] = qonto_encaissements(); }
    catch (\Throwable $ex) { return '<p class="erreur">' . h('Qonto n\'a pas pu être lu : ' . $ex->getMessage() . '.') . '</p>'; }
    $deja = array_flip(array_map('strval', q("SELECT DISTINCT qonto_ref FROM commandes WHERE qonto_ref <> ''")->fetchAll(\PDO::FETCH_COLUMN)));
    $s = '';
    foreach ($ops as $o) {
        if (isset($deja[$o['id']])) continue;
        $s .= '<label class="case ligne-rapprochement"><input type="radio" name="qonto_op" value="' . h($o['id']) . '"><span><b>' . h(euros($o['centimes'])) . '</b> · ' . h(qonto_type($o['type']))
            . '<br><span class="petit"><span translate="no">' . h($o['libelle']) . ($o['reference'] !== '' ? ' · ' . $o['reference'] : '') . '</span> · ' . h(date_fr(date('Y-m-d H:i:s', strtotime($o['le']) ?: time()), 'heure')) . '</span></span></label>';
    }
    $e = $err ? '<p class="erreur">' . h('Lecture incomplète : ' . implode(' · ', $err)) . '</p>' : '';
    if ($s === '') return $e . '<p class="aide">' . h('Aucun encaissement Qonto non rapproché sur les ' . GESTION_QONTO_JOURS . ' derniers jours.') . '</p>';
    return $e . '<fieldset class="liste-cases"><legend>' . h('Encaissements lus dans Qonto (' . GESTION_QONTO_JOURS . ' derniers jours)') . '</legend>' . $s . '</fieldset>';
}
/** L'encaissement choisi au Rapprochement, s'il est bien dans la dernière lecture de Qonto. */
function encaissement_qonto_choisi(): ?array {
    $id = is_string($_POST['qonto_op'] ?? null) ? $_POST['qonto_op'] : '';
    if ($id === '' || !preg_match('/^[0-9a-f\-]{36}$/i', $id)) return null;
    $cache = json_decode((string)parametre('qonto_cache', ''), true);
    foreach (is_array($cache['ops'] ?? null) ? $cache['ops'] : [] as $o) if ($o['id'] === $id) return $o;
    return null;
}

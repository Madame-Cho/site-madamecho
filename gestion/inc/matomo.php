<?php
/*
 * Madame Cho : espace de gestion. Statistiques du site, lues dans Matomo (https://stats.madamecho.com, T-06),
 * pour que Youngmi voie tout au même endroit (demande d'Olivier, 06/11/2026).
 * La clé d'accès de Matomo est collée dans Réglages et gardée dans la base, hors du site : jamais dans ce dépôt public (R-08),
 * jamais réaffichée, jamais dans la sauvegarde. Les chiffres (agrégés, sans donnée personnelle) sont gardés 15 minutes.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

function matomo_relie(): bool { return (string)parametre('matomo_jeton', '') !== ''; }

/** Envoi POST vers l'API de Matomo (la clé ne passe jamais dans l'adresse) : cURL s'il est là, sinon les flux de PHP. */
function matomo_poster(string $corps, int $delai): string {
    $url = GESTION_MATOMO . 'index.php';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corps, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => $delai, CURLOPT_USERAGENT => 'Gestion Madame Cho',
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']]);
        $r = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        if ($err !== '') error_log('madamecho gestion matomo : ' . $err);
        if (!is_string($r) || $code !== 200) throw new \RuntimeException('Matomo ne répond pas, ' . ($err === '' ? 'code ' . $code : (stripos($err, 'timed out') !== false ? 'délai dépassé' : 'connexion impossible')));
        return $r;
    }
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => $delai, 'ignore_errors' => true,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: Gestion Madame Cho\r\n", 'content' => $corps]]);
    $r = @file_get_contents($url, false, $ctx);
    if (!is_string($r)) throw new \RuntimeException('Matomo ne répond pas');
    return $r;
}

/** Plusieurs questions à Matomo en un seul appel (API.getBulkRequest) : les réponses reviennent dans l'ordre ; une réponse peut être une erreur. */
function matomo_appel(array $requetes, string $jeton, int $delai = 20): array {
    $champs = ['module' => 'API', 'method' => 'API.getBulkRequest', 'format' => 'json', 'token_auth' => $jeton, 'urls' => []];
    foreach (array_values($requetes) as $i => $r) {
        $champs['urls'][$i] = http_build_query($r + ['idSite' => GESTION_MATOMO_SITE, 'format' => 'json', 'language' => 'fr']);
    }
    $rep = json_decode(matomo_poster(http_build_query($champs), $delai), true);
    if (!is_array($rep)) throw new \RuntimeException('réponse illisible de Matomo');
    if (($rep['result'] ?? '') === 'error') throw new \RuntimeException(matomo_message((string)($rep['message'] ?? '')));
    return array_values($rep);
}

/** Une réponse de Matomo est-elle une erreur ? */
function matomo_erreur(mixed $r): bool { return !is_array($r) || (($r['result'] ?? '') === 'error'); }

/** Les messages de Matomo, en clair pour Youngmi (et sans jamais recopier de clé). */
function matomo_message(string $m): string {
    $m = trim(strip_tags($m));
    if ($m === '') return 'erreur de Matomo';
    if (stripos($m, 'token') !== false || stripos($m, 'authenticat') !== false || stripos($m, 'logged in') !== false) return 'Matomo ne reconnaît pas cette clé';
    if (stripos($m, "access") !== false) return 'cette clé n\'a pas accès au site madamecho.com dans Matomo';
    return mb_substr($m, 0, 160);
}

/** Vérifie une clé avant de l'enregistrer : elle doit ouvrir le site madamecho.com. Renvoie '' si elle marche, sinon la raison. */
function matomo_essayer_cle(string $jeton): string {
    try {
        $r = matomo_appel([['method' => 'SitesManager.getSiteFromId']], $jeton, 10);
        $s = $r[0] ?? null;
        if (matomo_erreur($s)) return matomo_message(is_array($s) ? (string)($s['message'] ?? '') : '');
        if ((int)($s['idsite'] ?? 0) !== GESTION_MATOMO_SITE) return 'cette clé ne voit pas le site madamecho.com';
        return '';
    } catch (\Throwable $e) {
        return $e->getMessage();
    }
}

/** Les chiffres du site : ceux de la base s'ils ont moins de 15 minutes, sinon demandés à Matomo. */
function matomo_chiffres(bool $forcer = false): array {
    $jeton = (string)parametre('matomo_jeton', '');
    if ($jeton === '') return ['etat' => 'non-relie'];
    $cache = json_decode((string)parametre('matomo_cache', ''), true);
    if (!$forcer && is_array($cache) && ($cache['etat'] ?? '') === 'ok' && (int)($cache['le'] ?? 0) > time() - GESTION_MATOMO_CACHE) return $cache;
    $q = [
        'aujourdhui' => ['method' => 'VisitsSummary.get', 'period' => 'day', 'date' => 'today'],
        'j7' => ['method' => 'VisitsSummary.get', 'period' => 'range', 'date' => 'last7'],
        'j30' => ['method' => 'VisitsSummary.get', 'period' => 'range', 'date' => 'last30'],
        'jours' => ['method' => 'VisitsSummary.get', 'period' => 'day', 'date' => 'last30'],
        'actions' => ['method' => 'Actions.get', 'period' => 'range', 'date' => 'last30'],
        'buts7' => ['method' => 'Goals.get', 'period' => 'range', 'date' => 'last7'],
        'buts30' => ['method' => 'Goals.get', 'period' => 'range', 'date' => 'last30'],
        'pages' => ['method' => 'Actions.getPageTitles', 'period' => 'range', 'date' => 'last30', 'flat' => '1', 'filter_limit' => '10',
                    'filter_sort_column' => 'nb_hits', 'filter_sort_order' => 'desc'],
        'types' => ['method' => 'Referrers.getReferrerType', 'period' => 'range', 'date' => 'last30'],
        'sources' => ['method' => 'Referrers.getAll', 'period' => 'range', 'date' => 'last30', 'filter_limit' => '8'],
        'appareils' => ['method' => 'DevicesDetection.getType', 'period' => 'range', 'date' => 'last30'],
        // scans des QR codes imprimés (madamecho.com/qr) : campagne « qr-code », un mot-clé par support ; demandes envoyées après un scan
        'qr7' => ['method' => 'Referrers.getCampaigns', 'period' => 'range', 'date' => 'last7', 'expanded' => '1', 'filter_limit' => '-1'],
        'qr30' => ['method' => 'Referrers.getCampaigns', 'period' => 'range', 'date' => 'last30', 'expanded' => '1', 'filter_limit' => '-1'],
        'qrbuts30' => ['method' => 'Goals.get', 'period' => 'range', 'date' => 'last30', 'segment' => 'referrerType==campaign;referrerName==' . GESTION_QR_CAMPAGNE],
    ];
    try {
        $r = matomo_appel(array_values($q), $jeton);
        $d = ['etat' => 'ok', 'le' => time()];
        foreach (array_keys($q) as $i => $k) $d[$k] = matomo_erreur($r[$i] ?? null) ? null : $r[$i];
        if ($d['j30'] === null && $d['jours'] === null) {   // rien de lisible : la clé a sans doute perdu ses droits
            $m = is_array($r[2] ?? null) ? (string)($r[2]['message'] ?? '') : '';
            throw new \RuntimeException(matomo_message($m));
        }
        parametre_ecrire('matomo_cache', (string)json_encode($d, JSON_UNESCAPED_UNICODE));
        return $d;
    } catch (\Throwable $e) {
        error_log('madamecho gestion matomo : ' . $e->getMessage());
        if (is_array($cache) && ($cache['etat'] ?? '') === 'ok') return ['erreur' => $e->getMessage()] + $cache;
        return ['etat' => 'erreur', 'erreur' => $e->getMessage()];
    }
}

/** Scans des QR codes imprimés : la campagne « qr-code » du rapport des campagnes, avec ses visites en tout et par support (mot-clé) ; null si le rapport manque. */
function matomo_qr(mixed $campagnes): ?array {
    if (!is_array($campagnes)) return null;
    foreach ($campagnes as $c) {
        if (!is_array($c) || mb_strtolower(trim((string)($c['label'] ?? ''))) !== GESTION_QR_CAMPAGNE) continue;
        $supports = [];
        foreach (is_array($c['subtable'] ?? null) ? $c['subtable'] : [] as $s) {
            if (is_array($s)) $supports[trim((string)($s['label'] ?? ''))] = matomo_val($s, 'nb_visits');
        }
        return ['visites' => matomo_val($c, 'nb_visits'), 'supports' => $supports];
    }
    return ['visites' => 0.0, 'supports' => []];
}

/** Valeur d'un rapport de Matomo, qu'il soit vide ([]) ou rempli. */
function matomo_val(mixed $r, string $cle): float {
    if (!is_array($r)) return 0.0;
    $v = $r[$cle] ?? 0;
    if (is_string($v)) $v = str_replace(['%', ','], ['', '.'], $v);
    return is_numeric($v) ? (float)$v : 0.0;
}

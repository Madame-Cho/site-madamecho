<?php
/*
 * Madame Cho : un petit son joyeux quand une nouvelle commande arrive par le site pendant que la gestion est ouverte
 * (demande d'Olivier du 12/11/2026 : « chaque commande est une bonne nouvelle pour Youngmi »).
 * La page demande toutes les 30 secondes la dernière commande du site (?p=nouvelles) ; gestion.js joue le son et montre un bandeau.
 * Le réglage (son ou pas) est gardé sur chaque appareil, dans Paramètres : Olivier peut le couper chez lui sans le couper chez Youngmi.
 * Cette question ne prolonge jamais la session (elle ne touche pas à $_SESSION['vu']) : 12 h sans action, la session s'arrête comme avant.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

const SON_INTERVALLE = 30;   // secondes entre deux questions, page visible (le navigateur espace davantage quand l'onglet est caché)

/** Le numéro interne de la dernière commande arrivée par le site ou le QR code (0 s'il n'y en a pas). */
function son_dernier_id(): int {
    return (int)(val("SELECT MAX(id) FROM commandes WHERE canal IN ('site', 'qr') AND efface_le IS NULL") ?? 0);
}

/** Les commandes du site arrivées après $depuis : leur nombre, et la plus récente (référence, prénom ou société, lien). */
function son_nouvelles(int $depuis): array {
    $n = (int)val("SELECT COUNT(*) FROM commandes WHERE canal IN ('site', 'qr') AND efface_le IS NULL AND id > ?", [$depuis]);
    $r = ['dernier' => son_dernier_id(), 'n' => $n];
    if ($n > 0) {
        $c = ligne("SELECT c.id, c.reference, cl.prenom, cl.societe, cl.type FROM commandes c LEFT JOIN clients cl ON cl.id = c.client_id
                    WHERE c.canal IN ('site', 'qr') AND c.efface_le IS NULL ORDER BY c.id DESC LIMIT 1");
        if ($c) {
            $qui = $c['type'] === 'pro' && $c['societe'] !== '' ? (string)$c['societe'] : (string)($c['prenom'] ?? '');
            $r['ref'] = (string)$c['reference'];
            $r['qui'] = $qui;
            $r['url'] = $n > 1 ? u('commandes') : u('commande', ['id' => (int)$c['id']]);
        }
    }
    return $r;
}

/**
 * ?p=nouvelles : la réponse à la question de la page, en JSON. Une personne connectée seulement (session encore valable, ou jeton
 * « rester connectée ») ; sinon 401 et la page arrête de demander. Rien n'est écrit, ni dans la session ni dans la base.
 */
function page_nouvelles(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    session_ouvrir();
    $uid = (int)($_SESSION['uid'] ?? 0);
    $depuisSession = (string)($_SESSION['depuis'] ?? '');
    if ($uid && (time() - (int)($_SESSION['vu'] ?? 0)) > GESTION_SESSION_INACTIVE) $uid = 0;
    session_write_close();
    if (!$uid && !empty($_COOKIE['mc_souvenir']) && ($j = jeton_verifier((string)$_COOKIE['mc_souvenir'], 'session'))) { $uid = (int)$j['utilisateur_id']; $depuisSession = ''; }
    $u = $uid ? ligne("SELECT id, mdp_change_le FROM utilisateurs WHERE id = ? AND actif = 1", [$uid]) : null;
    if ($u && $depuisSession !== '' && !empty($u['mdp_change_le']) && $depuisSession < $u['mdp_change_le']) $u = null;
    if (!$u) { http_response_code(401); echo '{}'; exit; }
    $depuis = is_string($_GET['depuis'] ?? null) && ctype_digit($_GET['depuis']) ? (int)$_GET['depuis'] : PHP_INT_MAX;
    echo json_encode(son_nouvelles($depuis), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Le bloc de Paramètres : le réglage est sur l'appareil (gestion.js le lit et l'écrit), le serveur n'en garde rien. */
function son_bloc_reglages(): string {
    $actif = parametre('demandes_site') === '1';
    return bloc(fr_ko('Son des nouvelles commandes', '새 주문 알림음'),
        '<p class="aide">' . fr_ko('Gardé sur cet appareil : un petit son joyeux et un bandeau quand une commande arrive par le site pendant que la gestion est ouverte.',
                                   '이 기기에 저장돼요. 관리 화면이 열려 있을 때 사이트로 주문이 들어오면 짧고 기분 좋은 소리와 알림 띠가 나와요.') . '</p>'
        . '<label class="case son-case"><input type="checkbox" id="son-actif" data-son-reglage checked><span>' . fr_ko('Jouer le son sur cet appareil', '이 기기에서 소리 켜기') . '</span></label>'
        . '<div class="actions"><button type="button" class="btn secondaire" data-son-tester>' . icone('son') . fr_ko('Écouter le son', '소리 들어 보기') . '</button></div>'
        . '<p class="aide">' . fr_ko('Le téléphone ne sonne que si la gestion est ouverte à l\'écran, et après un premier toucher sur la page. Écran éteint ou appli fermée : le son se joue en revenant dessus. En mode silencieux, l\'iPhone reste muet.',
                                   '관리 화면이 열려 있고 페이지를 한 번 누른 뒤에만 소리가 나요. 화면이 꺼져 있거나 앱이 닫혀 있으면 다시 열 때 소리가 나요. 무음 모드인 아이폰은 소리가 나지 않아요.') . '</p>'
        . ($actif ? '' : '<p class="aide"><span class="dormant">' . fr_ko('À noter', '참고') . '</span> ' . fr_ko(': les demandes du site ne sont pas encore enregistrées ici (Demandes du site, plus bas) ; le son attend ce moment.',
                                   ': 사이트 문의가 아직 여기에 자동으로 들어오지 않아요(아래 « 사이트 문의 »). 그때부터 소리가 나요.') . '</p>'),
        '', 'son');
}

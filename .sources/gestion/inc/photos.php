<?php
declare(strict_types=1);
namespace MadameCho\Gestion;
/*
 * Les photos des clients (demande d'Olivier du 10/11/2026, choix « Oui, photos Google seulement »).
 * Gmail montre, pour certains clients, la photo de leur compte Google (rendue publique par eux) ou celle d'une fiche contact de
 * contact@. Le script Google « Réponses des clients », dans contact@ (lecture seule), la lit une fois par jour avec l'API Contacts
 * de Google, seulement pour les adresses que la gestion lui donne (ses clients), et envoie ici une petite image. Rien d'autre.
 * - Source unique : Google (contacts et « autres contacts » de contact@). Ni Gravatar, ni adresse non officielle, ni autre boîte (R-41, R-05).
 * - L'image est refaite ici en 160 px (JPEG, sans ses métadonnées) et gardée dans la base, jamais dans le dossier servi ;
 *   elle ne s'affiche qu'une fois connecté, n'entre jamais dans l'assistant IA, ni dans le miroir du Drive.
 * - Elle n'est affichée que si l'adresse du client n'a pas changé depuis ; elle part avec la fiche (effacement, suppression, fusion).
 * - « Masquer la photo » sur la fiche (opposition du client, ou photo qui ne convient pas) : plus jamais redemandée pour ce client.
 * - Une photo est revue chaque mois ; un client sans photo est recherché de nouveau chaque semaine.
 * La clé est celle du script des réponses (Paramètres › Réponses des clients) : un seul script, une seule clé.
 */

const PHOTOS_TAILLE = 160;              // pixels, carré
const PHOTOS_MAX_OCTETS = 400_000;      // image reçue, avant d'être refaite
const PHOTOS_REVOIR_JOURS = 30;         // une photo est redemandée chaque mois (le client a pu la changer ou la retirer)
const PHOTOS_RECHERCHER_JOURS = 7;      // un client sans photo est recherché de nouveau chaque semaine
const PHOTOS_MAX_LISTE = 300;           // adresses données au script par passage
const PHOTOS_MAX_PAR_ENVOI = 40;        // photos reçues par appel

function photos_actif(): bool { return reponses_actif(); }

/** Les adresses dont la gestion attend une photo : clients non effacés, adresse valide, photo pas masquée, à revoir. */
function photos_emails_a_chercher(): array {
    $revoir = date('Y-m-d H:i:s', time() - PHOTOS_REVOIR_JOURS * 86400);
    $chercher = date('Y-m-d H:i:s', time() - PHOTOS_RECHERCHER_JOURS * 86400);
    $r = [];
    foreach (lignes("SELECT c.email, p.email AS p_email, p.image IS NOT NULL AS a_photo, p.verifie_le, p.masquee
                     FROM clients c LEFT JOIN photos_clients p ON p.client_id = c.id
                     WHERE c.statut <> 'efface' AND c.email <> '' ORDER BY c.dernier_echange DESC") as $l) {
        $e = mb_strtolower(trim((string)$l['email']));
        if (!email_valide($e) || isset($r[$e]) || (int)($l['masquee'] ?? 0)) continue;
        $aRevoir = $l['p_email'] === null || $l['p_email'] !== $e || !$l['verifie_le']
            || (string)$l['verifie_le'] < ((int)$l['a_photo'] ? $revoir : $chercher);
        if ($aRevoir) $r[$e] = true;
        if (count($r) >= PHOTOS_MAX_LISTE) break;
    }
    return array_keys($r);
}

/** Refait une image reçue en carré de 160 px, en JPEG sans métadonnées ; null si ce n'est pas une image qu'on accepte. */
function photo_refaire(string $octets): ?string {
    if ($octets === '' || strlen($octets) > PHOTOS_MAX_OCTETS || !function_exists('imagecreatefromstring')) return null;
    $info = @getimagesizefromstring($octets);
    if (!$info || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) return null;
    [$l, $h] = [(int)$info[0], (int)$info[1]];
    if ($l < 16 || $h < 16 || $l > 2048 || $h > 2048) return null;
    $src = @imagecreatefromstring($octets);
    if (!$src) return null;
    $cote = min($l, $h);
    $dst = imagecreatetruecolor(PHOTOS_TAILLE, PHOTOS_TAILLE);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // une image transparente sur fond blanc
    imagecopyresampled($dst, $src, 0, 0, intdiv($l - $cote, 2), intdiv($h - $cote, 2), PHOTOS_TAILLE, PHOTOS_TAILLE, $cote, $cote);
    ob_start();
    imagejpeg($dst, null, 85);
    $jpeg = (string)ob_get_clean();
    return $jpeg !== '' ? $jpeg : null;
}

/** Range ce que le script a trouvé pour une adresse : une image (base64) ou rien. Renvoie 'photo', 'sans', 'inconnu' ou 'refuse'. */
function photo_recevoir(string $email, ?string $base64): string {
    $email = mb_strtolower(trim($email));
    if (!email_valide($email)) return 'refuse';
    $ids = array_map('intval', array_column(lignes("SELECT id FROM clients WHERE statut <> 'efface' AND lower(trim(email)) = ?", [$email]), 'id'));
    if (!$ids) return 'inconnu';   // pas (ou plus) un client : rien n'est gardé
    $image = null;
    if ($base64 !== null && $base64 !== '') {
        $octets = base64_decode(strtr($base64, '-_', '+/'), true);
        $image = $octets === false ? null : photo_refaire($octets);
        if ($image === null) return 'refuse';
    }
    $now = maintenant();
    foreach ($ids as $id) {
        if ((int)val("SELECT masquee FROM photos_clients WHERE client_id = ?", [$id])) continue;   // masquée : on n'y touche plus
        q("INSERT INTO photos_clients (client_id, email, image, verifie_le, maj_le, masquee) VALUES (?, ?, ?, ?, ?, 0)
           ON CONFLICT(client_id) DO UPDATE SET email = excluded.email, image = excluded.image, verifie_le = excluded.verifie_le,
           maj_le = CASE WHEN photos_clients.image IS excluded.image THEN photos_clients.maj_le ELSE excluded.maj_le END",
          [$id, $email, $image, $now, $now]);
    }
    return $image !== null ? 'photo' : 'sans';
}

/**
 * Appelée par le script Google de contact@, en POST, avec la clé des réponses :
 * - etape=liste : la gestion répond la liste des adresses à chercher (ses clients, rien d'autre) ;
 * - etape=photos&photos=[{email, image|null}] : ce que le script a trouvé.
 */
function page_photos(): void {
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    $empreinte = (string)parametre('reponses_empreinte', '');
    $cle = is_string($_POST['cle'] ?? null) ? $_POST['cle'] : '';
    $bloque = (int)val("SELECT COUNT(*) FROM connexions WHERE ok = 0 AND email = 'reponses' AND ip = ? AND le > ?", [ip_empreinte(), date('Y-m-d H:i:s', time() - 900)]) >= GESTION_ECHECS_MAX;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $empreinte === '' || $bloque) { http_response_code(404); exit; }
    if (!hash_equals($empreinte, hash('sha256', $cle))) { connexion_noter('reponses', false, 'photos'); http_response_code(403); exit; }
    header('Content-Type: application/json; charset=utf-8');
    $etape = is_string($_POST['etape'] ?? null) ? $_POST['etape'] : '';
    if ($etape === 'liste') {
        parametre_ecrire('photos_dernier', maintenant());
        echo json_encode(['ok' => true, 'emails' => photos_emails_a_chercher()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($etape !== 'photos') { http_response_code(400); echo json_encode(['erreur' => 'étape inconnue']); exit; }
    $brut = is_string($_POST['photos'] ?? null) ? $_POST['photos'] : '[]';
    $photos = strlen($brut) <= 8_000_000 ? json_decode($brut, true) : null;
    if (!is_array($photos)) { http_response_code(400); echo json_encode(['erreur' => 'photos illisibles']); exit; }
    $attendues = array_flip(photos_emails_a_chercher());   // seules les adresses demandées sont acceptées
    $compte = [];
    foreach (array_slice(array_values($photos), 0, PHOTOS_MAX_PAR_ENVOI) as $p) {
        if (!is_array($p) || !is_string($p['email'] ?? null)) continue;
        $e = mb_strtolower(trim($p['email']));
        $r = isset($attendues[$e]) ? photo_recevoir($e, is_string($p['image'] ?? null) ? $p['image'] : null) : 'inconnu';
        $compte[$r] = ($compte[$r] ?? 0) + 1;
    }
    parametre_ecrire('photos_dernier', maintenant());
    echo json_encode(['ok' => true, 'compte' => $compte], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Les clients qui ont une photo à montrer (adresse inchangée, pas masquée) : [client_id => maj_le]. Lu une fois par page. */
function photos_index(): array {
    static $i = null;
    if ($i !== null) return $i;
    $i = [];
    try {
        foreach (lignes("SELECT p.client_id, p.maj_le FROM photos_clients p JOIN clients c ON c.id = p.client_id
                         WHERE p.image IS NOT NULL AND p.masquee = 0 AND c.statut <> 'efface' AND lower(trim(c.email)) = p.email") as $l) $i[(int)$l['client_id']] = (string)$l['maj_le'];
    } catch (\PDOException $e) { $i = []; }
    return $i;
}
/** La photo ronde d'un client, ou '' s'il n'en a pas (rien ne change alors à l'écran). $taille : 'petite' (listes) ou 'grande' (fiche). */
function avatar_client(int $clientId, string $taille = 'petite'): string {
    $maj = photos_index()[$clientId] ?? null;
    if ($maj === null) return '';
    $px = $taille === 'grande' ? 56 : 32;
    return '<img class="avatar-client avatar-' . h($taille) . '" src="' . h(u('photo-client', ['id' => $clientId, 'v' => substr(md5($maj), 0, 8)])) . '" alt="" width="' . $px . '" height="' . $px . '" loading="lazy" decoding="async">';
}
/** Sert la photo d'un client (connexion obligatoire, page privée). */
function page_photo_client(): void {
    $id = entier('id', 1);
    if (!isset(photos_index()[$id])) { http_response_code(404); exit; }
    $img = val("SELECT image FROM photos_clients WHERE client_id = ?", [$id]);
    if (!is_string($img) || $img === '') { http_response_code(404); exit; }
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . strlen($img));
    header('Cache-Control: private, max-age=604800');
    header('X-Content-Type-Options: nosniff');
    echo $img;
    exit;
}

/** L'état de la photo d'un client, pour sa fiche : 'photo', 'masquee', 'sans' (cherchée, pas trouvée) ou 'jamais' (pas encore cherchée). */
function photo_etat(array $c): string {
    $p = ligne("SELECT email, image IS NOT NULL AS a_photo, masquee, verifie_le FROM photos_clients WHERE client_id = ?", [(int)$c['id']]);
    if (!$p) return 'jamais';
    if ((int)$p['masquee']) return 'masquee';
    if ($p['email'] !== mb_strtolower(trim((string)$c['email']))) return 'jamais';
    return (int)$p['a_photo'] ? 'photo' : 'sans';
}
/** « Masquer la photo » : l'image est effacée et ne sera plus redemandée. « Afficher de nouveau » : elle sera cherchée au prochain passage. */
function photo_masquer(int $clientId, bool $masquer): void {
    if ($masquer) {
        q("INSERT INTO photos_clients (client_id, email, image, verifie_le, maj_le, masquee) VALUES (?, '', NULL, NULL, ?, 1)
           ON CONFLICT(client_id) DO UPDATE SET image = NULL, masquee = 1, maj_le = excluded.maj_le", [$clientId, maintenant()]);
        evenement('modification', 'Photo du client masquée (elle ne sera plus cherchée)', ['client' => $clientId], '', '', null);
    } else {
        q("DELETE FROM photos_clients WHERE client_id = ?", [$clientId]);
        evenement('modification', 'Photo du client : de nouveau autorisée (cherchée au prochain passage du script)', ['client' => $clientId], '', '', null);
    }
}
/** La photo part avec la fiche (effacement RGPD, suppression, fusion d'un doublon). */
function photo_oublier(int $clientId): void {
    try { q("DELETE FROM photos_clients WHERE client_id = ?", [$clientId]); } catch (\PDOException $e) {}
}
/** Tâche de nuit : rien ne reste pour une fiche disparue ou effacée, ni pour une adresse qui a changé. */
function photos_purger(): void {
    q("DELETE FROM photos_clients WHERE client_id NOT IN (SELECT id FROM clients WHERE statut <> 'efface')");
    q("UPDATE photos_clients SET image = NULL WHERE masquee = 0 AND image IS NOT NULL
       AND email <> (SELECT lower(trim(c.email)) FROM clients c WHERE c.id = photos_clients.client_id)");
}
/** Pour Paramètres : combien de clients ont une photo, et le dernier passage. */
function photos_resume(): array {
    return ['avec' => count(photos_index()), 'masquees' => (int)val("SELECT COUNT(*) FROM photos_clients WHERE masquee = 1"),
            'dernier' => parametre('photos_dernier')];
}

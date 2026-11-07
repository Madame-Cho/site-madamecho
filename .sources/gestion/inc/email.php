<?php
/*
 * Madame Cho : espace de gestion. Emails du système (lien pour choisir son mot de passe, invitation),
 * envoyés par le site depuis site@madamecho.com et signés DKIM comme le formulaire (sélecteur « site », P-07).
 * Ce ne sont pas des emails rédigés par une IA (R-01) : seulement les messages de connexion demandés par la personne elle-même.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

/** Envoie un email texte signé DKIM ; en essai local, le garde pour l'afficher à l'écran. */
function envoyer_email(string $a, string $sujet, string $corps): bool {
    if (!email_valide($a)) return false;
    if (GESTION_ESSAI) {
        session_ouvrir();
        $_SESSION['essai_email'] = ['a' => $a, 'sujet' => $sujet, 'corps' => $corps];
        return true;
    }
    $dkim = gestion_site() . '/ma-demande/dkim.php';
    if (!is_file($dkim)) { error_log('madamecho gestion : dkim.php absent, email non envoyé'); return false; }
    require_once $dkim;
    $corps = str_replace(["\r\n", "\r"], "\n", $corps);
    $corpsEnc = rtrim(chunk_split(base64_encode(str_replace("\n", "\r\n", $corps)), 76, "\r\n"), "\r\n") . "\r\n";
    $sujetEnc = mb_encode_mimeheader($sujet, 'UTF-8', 'B');
    $signes = [
        'From' => mb_encode_mimeheader('Madame Cho · Gestion', 'UTF-8', 'B') . ' <' . GESTION_EXPEDITEUR . '>',
        'To' => $a,
        'Subject' => $sujetEnc,
        'Date' => date('r'),
        'Message-ID' => '<gestion.' . bin2hex(random_bytes(8)) . '@' . GESTION_DOMAINE . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => 'base64',
    ];
    $signature = \dkim_signer($signes, $corpsEnc);
    if (!$signature) { error_log('madamecho gestion : signature DKIM impossible, email non envoyé'); return false; }
    $l = [$signature];
    foreach ($signes as $n => $v) if ($n !== 'To' && $n !== 'Subject') $l[] = $n . ': ' . $v;
    $l[] = 'X-Mailer: madamecho.com';
    $entetes = implode("\r\n", $l);
    $ok = @mail($a, $sujetEnc, $corpsEnc, $entetes, '-f' . GESTION_EXPEDITEUR);
    if (!$ok) $ok = @mail($a, $sujetEnc, $corpsEnc, $entetes);
    if (!$ok) error_log('madamecho gestion : mail() a échoué');
    return $ok;
}

function email_lien_mot_de_passe(array $u, string $jeton, bool $invitation): bool {
    $lien = gestion_adresse() . '?' . http_build_query(['p' => 'mot-de-passe', 'j' => $jeton]);
    $duree = $invitation ? '3 jours' : '1 heure';
    $t = 'Bonjour ' . $u['nom'] . ",\n\n"
       . ($invitation ? "Un compte vous a été ouvert dans la gestion de Madame Cho (clients, commandes, produits).\n\n"
                      : "Vous avez demandé à choisir un nouveau mot de passe pour la gestion de Madame Cho.\n\n")
       . "Pour choisir votre mot de passe, ouvrez ce lien (valable " . $duree . ", une seule fois) :\n" . $lien . "\n\n"
       . "Ensuite, vous vous connecterez avec votre adresse " . $u['email'] . " et ce mot de passe, à l'adresse :\n" . gestion_adresse() . "\n\n"
       . "Si vous n'avez rien demandé, ne faites rien : votre mot de passe actuel reste valable.\n\n"
       . "Madame Cho";
    return envoyer_email($u['email'], $invitation ? 'Votre accès à la gestion de Madame Cho' : 'Gestion Madame Cho : choisir votre mot de passe', $t);
}

/* ---------- Emails aux clients, envoyés au toucher de Youngmi (demande d'Olivier du 07/11/2026) ----------
 * Écrits par Youngmi (phrase d'accueil, petit mot) à partir de la commande, relus dans l'aperçu, envoyés par son toucher
 * sur « Envoyer » : jamais par une IA (R-01). Au nom de « Madame Cho <contact@madamecho.com> », signés DKIM par le site
 * (sélecteur « site », d=madamecho.com : DMARC aligné ; OVH autorisé dans le SPF), en deux versions (texte et mise en forme).
 * Les réponses vont à contact@ ; une copie cachée arrive dans contact@ (le mail n'est pas dans « Envoyés » de Gmail). */
function envoyer_email_client(string $a, string $sujet, string $texte, string $html): bool {
    if (!email_valide($a)) return false;
    if (GESTION_ESSAI) {
        session_ouvrir();
        $_SESSION['essai_email'] = ['a' => $a, 'sujet' => $sujet, 'corps' => $texte, 'html' => $html];
        return true;
    }
    $dkim = gestion_site() . '/ma-demande/dkim.php';
    if (!is_file($dkim)) { error_log('madamecho gestion : dkim.php absent, email client non envoyé'); return false; }
    require_once $dkim;
    $enc = fn(string $s) => rtrim(chunk_split(base64_encode(str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $s))), 76, "\r\n"), "\r\n");
    $frontiere = 'mc-' . bin2hex(random_bytes(12));
    $corps = "--$frontiere\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($texte) . "\r\n"
           . "--$frontiere\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $enc($html) . "\r\n"
           . "--$frontiere--\r\n";
    $sujetEnc = mb_encode_mimeheader($sujet, 'UTF-8', 'B');
    $signes = [
        'From' => 'Madame Cho <' . GESTION_COMPTE_INITIAL . '>',
        'Reply-To' => GESTION_COMPTE_INITIAL,
        'To' => $a,
        'Subject' => $sujetEnc,
        'Date' => date('r'),
        'Message-ID' => '<commande.' . bin2hex(random_bytes(8)) . '@' . GESTION_DOMAINE . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'multipart/alternative; boundary="' . $frontiere . '"',
    ];
    $signature = \dkim_signer($signes, $corps);
    if (!$signature) { error_log('madamecho gestion : signature DKIM impossible, email client non envoyé'); return false; }
    $l = [$signature];
    foreach ($signes as $n => $v) if ($n !== 'To' && $n !== 'Subject') $l[] = $n . ': ' . $v;
    $l[] = 'Bcc: ' . GESTION_COMPTE_INITIAL;
    $l[] = 'X-Mailer: madamecho.com';
    $entetes = implode("\r\n", $l);
    $ok = @mail($a, $sujetEnc, $corps, $entetes, '-f' . GESTION_EXPEDITEUR);
    if (!$ok) $ok = @mail($a, $sujetEnc, $corps, $entetes);
    if (!$ok) error_log('madamecho gestion : mail() a échoué (email client)');
    return $ok;
}

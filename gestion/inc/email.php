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

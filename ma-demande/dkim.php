<?php
/*
 * Madame Cho : signature DKIM des emails du formulaire (sélecteur « site », domaine madamecho.com).
 * L'hébergement OVH ne signe pas les emails envoyés par mail() : le site les signe lui-même.
 * La clé privée est créée sur le serveur, hors du dossier du site quand c'est possible ;
 * elle n'est jamais dans le dépôt public ni dans le Drive (R-08). Inclus par envoyer.php et cle-dkim.php.
 */
declare(strict_types=1);

const DKIM_DOMAINE = 'madamecho.com';
const DKIM_SELECTEUR = 'site';
const DKIM_FICHIER = 'dkim-site.pem';

/** Dossiers possibles pour la clé : d'abord hors du site, sinon le dossier protégé des limites. */
function dkim_dossiers(): array {
    $d = [];
    $racine = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($racine !== '') $d[] = rtrim(dirname(rtrim($racine, '/')), '/') . '/.madamecho-cles';
    $d[] = __DIR__ . '/.limites';
    return $d;
}

/** Chemin de la clé existante, ou null. */
function dkim_cle_existante(): ?string {
    foreach (dkim_dossiers() as $d) {
        $f = $d . '/' . DKIM_FICHIER;
        if (is_file($f) && is_readable($f)) return $f;
    }
    return null;
}

/** Crée la clé (2048 bits) si elle n'existe pas encore ; renvoie son chemin, ou null en cas d'échec. */
function dkim_creer_cle(): ?string {
    if ($f = dkim_cle_existante()) return $f;
    $cle = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    if (!$cle || !openssl_pkey_export($cle, $pem)) return null;
    foreach (dkim_dossiers() as $d) {
        if (!is_dir($d) && !@mkdir($d, 0700, true)) continue;
        $f = $d . '/' . DKIM_FICHIER;
        $h = @fopen($f, 'x');               // jamais d'écrasement d'une clé existante
        if (!$h) { if (is_file($f)) return $f; continue; }
        fwrite($h, $pem); fclose($h);
        @chmod($f, 0600);
        return $f;
    }
    return null;
}

/** Valeur du TXT à mettre dans la zone DNS (site._domainkey.madamecho.com). */
function dkim_enregistrement(string $fichier): ?string {
    $cle = openssl_pkey_get_private((string)file_get_contents($fichier));
    if (!$cle) return null;
    $pub = openssl_pkey_get_details($cle)['key'] ?? '';
    $p = preg_replace('/-----[^-]+-----|\s+/', '', $pub);
    return $p ? 'v=DKIM1; k=rsa; p=' . $p : null;
}

/** Canonisation « relaxed » d'un en-tête (RFC 6376, 3.4.2). */
function dkim_entete_relaxed(string $nom, string $valeur): string {
    $valeur = preg_replace("/\r\n(?=[ \t])/", '', $valeur);
    $valeur = trim(preg_replace('/[ \t]+/', ' ', $valeur));
    return strtolower(trim($nom)) . ':' . $valeur;
}

/** Canonisation « relaxed » du corps (RFC 6376, 3.4.4). */
function dkim_corps_relaxed(string $corps): string {
    $corps = str_replace(["\r\n", "\r"], "\n", $corps);
    $lignes = explode("\n", $corps);
    foreach ($lignes as &$l) $l = rtrim(preg_replace('/[ \t]+/', ' ', $l), " \t");
    unset($l);
    while ($lignes && end($lignes) === '') array_pop($lignes);
    return $lignes ? implode("\r\n", $lignes) . "\r\n" : '';
}

/**
 * Renvoie l'en-tête « DKIM-Signature: ... » (sans retour à la ligne final), ou null sans clé.
 * $entetes : [nom => valeur] tels qu'ils partiront (To et Subject compris), $corps : le corps tel qu'il partira.
 */
function dkim_signer(array $entetes, string $corps): ?string {
    $f = dkim_cle_existante();
    if (!$f) return null;
    $cle = openssl_pkey_get_private((string)file_get_contents($f));
    if (!$cle) return null;
    $noms = array_keys($entetes);
    $bh = base64_encode(hash('sha256', dkim_corps_relaxed($corps), true));
    $tags = 'v=1; a=rsa-sha256; c=relaxed/relaxed; d=' . DKIM_DOMAINE . '; s=' . DKIM_SELECTEUR
          . '; t=' . time() . '; h=' . strtolower(implode(':', $noms)) . '; bh=' . $bh . '; b=';
    $donnees = '';
    foreach ($entetes as $n => $v) $donnees .= dkim_entete_relaxed($n, $v) . "\r\n";
    $donnees .= dkim_entete_relaxed('DKIM-Signature', $tags);   // sans CRLF final
    if (!openssl_sign($donnees, $sig, $cle, OPENSSL_ALGO_SHA256)) return null;
    // Repli des lignes : seulement dans la valeur de b= (ignorée à la vérification) et avant « b= ».
    $b = rtrim(chunk_split(base64_encode($sig), 72, "\r\n\t"), "\r\n\t");
    return 'DKIM-Signature: ' . substr($tags, 0, -strlen(' b=')) . "\r\n\tb=" . $b;
}

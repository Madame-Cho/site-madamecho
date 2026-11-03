<?php
/*
 * Madame Cho : affiche l'enregistrement DNS de la signature DKIM du formulaire (clé publique seulement).
 * Au premier appel, crée la clé sur le serveur. La clé privée n'est jamais affichée.
 */
declare(strict_types=1);
require __DIR__ . '/dkim.php';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$f = dkim_creer_cle();
$valeur = $f ? dkim_enregistrement($f) : null;
if (!$valeur) {
    http_response_code(500);
    echo "La clé n'a pas pu être créée sur le serveur.\n";
    exit;
}
$nom = DKIM_SELECTEUR . '._domainkey.' . DKIM_DOMAINE;
$dns = @dns_get_record($nom, DNS_TXT) ?: [];
$publie = '';
foreach ($dns as $r) $publie .= implode('', $r['entries'] ?? [$r['txt'] ?? '']);
$norm = fn($s) => preg_replace('/\s+/', '', (string)$s);
if ($publie === '') $etat = 'pas encore dans la zone DNS (ou pas encore propagé)';
elseif ($norm($publie) === $norm($valeur)) $etat = 'PRÉSENT et identique : la signature est active';
else $etat = 'présent mais DIFFÉRENT : à corriger dans la zone DNS';

echo "Signature DKIM du formulaire de madamecho.com\n\n";
echo "Zone DNS de madamecho.com chez OVH, ajouter une entrée de type TXT :\n";
echo "  Sous-domaine : " . DKIM_SELECTEUR . "._domainkey\n";
echo "  Valeur :\n\n" . $valeur . "\n\n";
echo "État dans le DNS : " . $etat . "\n";
echo "Clé rangée " . (strpos($f, '/.limites/') !== false ? "dans le dossier protégé du formulaire" : "hors du dossier du site") . ".\n";

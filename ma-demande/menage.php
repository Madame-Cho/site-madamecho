<?php
/*
 * Madame Cho : ménage des empreintes du formulaire (48 heures au plus, politique de confidentialité).
 * Une empreinte de plus de 2 heures ne sert plus à rien (limite de 5 envois par heure) : elle est effacée.
 * Inclus par envoyer.php, qui le fait à chaque appel. Lancé aussi chaque jour par une tâche planifiée
 * de l'hébergement OVH (« Tâches planifiées - Cron »), pour que l'effacement ne dépende pas des visites :
 * 2 heures + 24 heures, toujours moins de 48 heures (audit du site du 03/11/2026, C07). Jamais servi par le site (.htaccess).
 */
declare(strict_types=1);

/** Efface les fichiers d'empreintes plus vieux que $duree secondes ; renvoie le nombre de fichiers en trop restés. */
function menage_empreintes(string $dossier, int $duree): int {
    $restes = 0;
    foreach (glob($dossier . '/*.json') ?: [] as $f) {
        $m = @filemtime($f);
        if ($m !== false && $m < time() - $duree && !@unlink($f)) $restes++;
    }
    if ($restes) error_log('madamecho ménage : ' . $restes . ' fichier(s) d\'empreintes non effacé(s)');
    return $restes;
}

/** Clé secrète des empreintes, créée au premier appel dans le dossier protégé ; null si elle est illisible. */
function sel_empreintes(string $dossier): ?string {
    $f = $dossier . '/.sel';
    if (!is_file($f)) {
        $h = @fopen($f, 'x');   // jamais d'écrasement
        if ($h) { fwrite($h, bin2hex(random_bytes(32))); fclose($h); @chmod($f, 0600); }
    }
    $s = @file_get_contents($f);
    return (is_string($s) && strlen($s) === 64) ? $s : null;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(menage_empreintes(__DIR__ . '/.limites', 7200) ? 1 : 0);
}

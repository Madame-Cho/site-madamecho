<?php
/*
 * Madame Cho : espace de gestion. Les pastilles « nouveau » et « a changé » (demande d'Olivier, 08/11/2026, FR-041).
 * Trois couleurs seulement : rouge avec un chiffre pour ce qui demande d'agir, ambre (un point) pour ce qui a changé,
 * chocolat ✦ pour une nouveauté de l'outil. Le « vu » est propre à chaque personne (choix d'Olivier) : ce qu'Olivier
 * ouvre reste nouveau pour Youngmi. Ce qu'une personne fait elle-même ne lui est jamais signalé.
 * Tout se calcule à partir de l'historique (table evenements) ; la table vus garde seulement des identifiants et des dates.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

/** Au-delà, une pastille s'éteint seule. */
const PASTILLES_JOURS = 30;
/** Une nouveauté de l'outil reste signalée au plus ce nombre de jours après sa publication. */
const NOUVEAUTES_JOURS = 21;
/** Les événements d'une commande qui comptent, et leur couleur ; une création par le site, un mail reçu et un problème ouvert sont rouges. */
const PASTILLES_TYPES_COMMANDE = ['creation', 'email_recu', 'probleme', 'statut', 'paiement', 'rapprochement', 'modification', 'assistant', 'commentaire', 'email_client', 'avis', 'restauration'];
/** Ceux d'une fiche client (hors commande). */
const PASTILLES_TYPES_CLIENT = ['creation', 'modification', 'commentaire', 'fusion', 'avis', 'restauration'];

/** Les champs d'une fiche client, en clair (l'historique garde leur nom technique). */
const CHAMPS_FICHE = ['type' => 'particulier ou professionnel', 'nom' => 'nom', 'prenom' => 'prénom', 'societe' => 'société', 'contact' => 'contact', 'email' => 'email',
                      'telephone' => 'téléphone', 'ville' => 'ville', 'canal' => 'canal', 'qonto' => 'nom dans Qonto', 'lettre_info' => 'lettre d\'information'];

/**
 * Les nouveautés de l'outil, la plus récente d'abord : identifiant fixe, date de publication, titre, texte, lien (page et ancre).
 * Une ligne par publication qui change ce que Youngmi voit ; les textes coréens sont dans inc/coreen.php.
 */
const NOUVEAUTES = [
    ['id' => '2026-11-hygiene', 'le' => '2026-11-08', 'titre' => 'L\'écran Hygiène',
     'texte' => 'Plus › Hygiène : la température des frigos, les réceptions, les fournées avec leur numéro de lot et les livraisons aux restaurants, notées en quelques touches sur le téléphone.',
     'lien' => ['manuel', 'hygiene']],
    ['id' => '2026-11-obligations', 'le' => '2026-11-08', 'titre' => 'L\'écran Obligations',
     'texte' => 'Plus › Obligations : tout ce qu\'il faut faire ou prouver pour être en règle, le plus urgent en haut, avec les échéances qui reviennent.',
     'lien' => ['manuel', 'obligations']],
    ['id' => '2026-11-noms', 'le' => '2026-11-08', 'titre' => 'Les majuscules des noms',
     'texte' => 'Les majuscules oubliées dans les noms et prénoms sont ajoutées toutes seules. Pour corriger un nom : « Corriger le nom ou les coordonnées », sur la fiche ou dans la commande.',
     'lien' => ['manuel', 'clients']],
    ['id' => '2026-11-08-pastilles', 'le' => '2026-11-08', 'titre' => 'Les pastilles de couleur',
     'texte' => 'Rouge : à regarder en premier. Ambre : quelque chose a changé. Elles s\'éteignent quand vous ouvrez la commande ou la fiche.',
     'lien' => ['manuel', 'pastilles']],
    ['id' => '2026-11-08-rappel', 'le' => '2026-11-08', 'titre' => 'Le rappel de retrait',
     'texte' => 'La veille du retrait, Aujourd\'hui liste les rappels à envoyer au client, par mail ou par SMS.',
     'lien' => ['manuel', 'parcours']],
];

/**
 * Le point de départ des pastilles de la personne : le numéro du dernier événement qu'elle n'a pas à voir. Les événements sont
 * numérotés dans l'ordre où ils arrivent (une réponse rattachée à la main plus tard reçoit un numéro neuf) : comparer les numéros,
 * jamais les heures, évite d'oublier un changement fait dans la même seconde (audit n° 5, constat 4).
 */
function pastilles_base(array $u): int {
    return (int)(val("SELECT pastilles_id FROM utilisateurs WHERE id = ?", [(int)$u['id']]) ?? 0);
}

/** Rouge (« action ») ou ambre (« change ») et le libellé d'un événement ; null s'il ne compte pas. */
function pastille_de(array $e, bool $surCommande): ?array {
    $t = (string)$e['type'];
    if ($surCommande) {
        return match ($t) {
            'creation' => $e['auteur'] === 'site' ? ['action', 'Nouvelle demande'] : ['change', 'Commande créée'],
            'email_recu' => ['action', 'Le client a répondu'],
            'probleme' => (string)$e['nouveau'] === '1' ? ['action', 'Problème signalé'] : ['change', 'Problème résolu'],
            'statut' => ['change', 'Étape changée'],
            'paiement' => ['change', 'Paiement noté'],
            'rapprochement' => ['change', 'Rapprochement Qonto'],
            'modification', 'assistant' => ['change', 'Commande modifiée'],
            'commentaire' => ['change', 'Échange noté'],
            'email_client' => ['change', 'Mail envoyé au client'],
            'avis' => ['change', 'Avis Google'],
            'restauration' => ['change', 'Restaurée'],
            default => null,
        };
    }
    return match ($t) {
        'creation' => ['change', $e['auteur'] === 'site' ? 'Nouvelle fiche (site)' : 'Nouvelle fiche'],
        'modification' => ['change', 'Fiche modifiée'],
        'commentaire' => ['change', 'Note ajoutée'],
        'fusion' => ['change', 'Doublon fusionné'],
        'avis' => ['change', 'Avis Google'],
        'restauration' => ['change', 'Restaurée'],
        default => null,
    };
}

/**
 * Tout ce qui est signalé à la personne connectée (une requête par page) :
 * ['commandes' => [id => objet], 'clients' => [id => objet], 'outil' => [nouveautés], 'action' => n, 'change_commandes' => bool, 'change_clients' => bool].
 * Un objet : 'niveau' (action ou change), 'evs' (du plus récent au plus ancien), 'le' (le plus récent) et ses données d'affichage.
 */
function pastilles(): array {
    if (isset($GLOBALS['mc_pastilles'])) return $GLOBALS['mc_pastilles'];
    $r = &$GLOBALS['mc_pastilles'];
    $r = ['commandes' => [], 'clients' => [], 'outil' => [], 'action' => 0, 'change_commandes' => false, 'change_clients' => false];
    $u = utilisateur_courant();
    if (!$u) return $r;
    $uid = (int)$u['id'];
    $typesK = "'" . implode("','", PASTILLES_TYPES_COMMANDE) . "'";
    $typesC = "'" . implode("','", PASTILLES_TYPES_CLIENT) . "'";
    $agir = "(e.type = 'email_recu' OR (e.type = 'creation' AND e.auteur = 'site' AND e.commande_id IS NOT NULL) OR (e.type = 'probleme' AND e.nouveau = '1'))";
    $objet = "(e.commande_id IS NULL), COALESCE(e.commande_id, e.client_id)";
    // Une seule requête, sans limite globale : chaque commande ou fiche garde ses 10 derniers changements pour l'affichage, plus ses
    // rouges, et son total (audit n° 5, constat 5). Le « vu » est propre à la personne et ne dépend jamais du repère partagé
    // « lu » des mails, qui reste pour l'assistant (constats 1 et 2). Une réponse de client compte toujours, même si l'expéditeur
    // porte le nom d'une utilisatrice ; pour le reste, ce que la personne a fait elle-même ne lui est pas signalé (constat 3).
    $evs = lignes("SELECT * FROM (SELECT e.id, e.le, e.auteur, e.type, e.commande_id, e.client_id, e.texte, e.ancien, e.nouveau,
            k.reference, c.nom, c.prenom, c.societe, c.type AS c_type, c.numero, c.statut AS c_statut,
            CASE WHEN $agir THEN 1 ELSE 0 END AS a_agir,
            ROW_NUMBER() OVER (PARTITION BY $objet ORDER BY e.id DESC) AS rang,
            COUNT(*) OVER (PARTITION BY $objet) AS total
        FROM evenements e
        LEFT JOIN commandes k ON k.id = e.commande_id
        LEFT JOIN clients c ON c.id = e.client_id
        LEFT JOIN vus v ON v.utilisateur_id = :u AND ((e.commande_id IS NOT NULL AND v.genre = 'commande' AND v.objet = CAST(e.commande_id AS TEXT))
                                                   OR (e.commande_id IS NULL AND v.genre = 'client' AND v.objet = CAST(e.client_id AS TEXT)))
        WHERE e.id > :base AND (v.jusqu_a IS NULL OR e.id > v.jusqu_a) AND e.le > :plancher
          AND (e.type = 'email_recu' OR e.auteur NOT IN (:nom, 'système', 'Gmail'))
          AND ((e.commande_id IS NOT NULL AND k.id IS NOT NULL AND e.type IN ($typesK))
            OR (e.commande_id IS NULL AND e.client_id IS NOT NULL AND c.id IS NOT NULL AND c.statut != 'efface' AND e.type IN ($typesC)))
        ) WHERE rang <= 10 OR a_agir = 1 ORDER BY id DESC",
        ['u' => $uid, 'base' => pastilles_base($u), 'nom' => (string)$u['nom'], 'plancher' => date('Y-m-d H:i:s', time() - PASTILLES_JOURS * 86400)]);
    foreach ($evs as $e) {
        $surK = $e['commande_id'] !== null;
        $p = pastille_de($e, $surK);
        if (!$p) continue;
        $e['niveau'] = $p[0];
        $e['libelle'] = $p[1];
        $cle = $surK ? 'commandes' : 'clients';
        $id = (int)($surK ? $e['commande_id'] : $e['client_id']);
        if (!isset($r[$cle][$id])) $r[$cle][$id] = ['niveau' => $p[0], 'evs' => [], 'le' => $e['le'], 'id' => $id, 'genre' => $surK ? 'commande' : 'client', 'n' => (int)$e['total'],
            'reference' => (string)($surK ? $e['reference'] : $e['numero']), 'nom' => $e['numero'] !== null ? client_nom(['nom' => (string)$e['nom'], 'prenom' => (string)$e['prenom'], 'societe' => (string)$e['societe'], 'type' => (string)$e['c_type'], 'statut' => (string)$e['c_statut'], 'numero' => (string)$e['numero']]) : ''];
        $r[$cle][$id]['evs'][] = $e;
        if ($p[0] === 'action') $r[$cle][$id]['niveau'] = 'action';
    }
    foreach ($r['commandes'] as $o) { if ($o['niveau'] === 'action') $r['action']++; else $r['change_commandes'] = true; }
    $r['change_clients'] = (bool)$r['clients'];
    // Les nouveautés de l'outil : publiées depuis moins de 21 jours, après la création du compte et le point de départ, pas encore vues
    $vues = array_column(lignes("SELECT objet FROM vus WHERE utilisateur_id = ? AND genre = 'outil'", [$uid]), 'objet');
    $cree = substr((string)($u['cree_le'] ?? ''), 0, 10);
    $depart = substr((string)(val("SELECT pastilles_depuis FROM utilisateurs WHERE id = ?", [$uid]) ?? ''), 0, 10);
    $min = date('Y-m-d', time() - NOUVEAUTES_JOURS * 86400);
    foreach (NOUVEAUTES as $n) {
        if ($n['le'] >= $min && $n['le'] >= $cree && $n['le'] >= $depart && !in_array($n['id'], $vues, true)) $r['outil'][] = $n;
    }
    return $r;
}
/** Le numéro du dernier événement déjà vu par la personne pour une commande ou une fiche, à lire AVANT pastilles_vu(). */
function pastilles_seuil(string $genre, int $id): int {
    $u = utilisateur_courant();
    if (!$u) return PHP_INT_MAX;
    $v = (int)(val("SELECT jusqu_a FROM vus WHERE utilisateur_id = ? AND genre = ? AND objet = ?", [(int)$u['id'], $genre, (string)$id]) ?? 0);
    return max(pastilles_base($u), $v);
}
/**
 * La personne a ouvert la commande, la fiche ou « Quoi de neuf » : vu jusqu'au dernier événement affiché ($jusqua), pas au-delà ;
 * un changement arrivé pendant l'affichage reste signalé.
 */
function pastilles_vu(string $genre, string $objet, int $jusqua = 0): void {
    $u = utilisateur_courant();
    if (!$u) return;
    $avant = (int)(val("SELECT jusqu_a FROM vus WHERE utilisateur_id = ? AND genre = ? AND objet = ?", [(int)$u['id'], $genre, $objet]) ?? 0);
    q("INSERT OR REPLACE INTO vus (utilisateur_id, genre, objet, le, jusqu_a) VALUES (?, ?, ?, ?, ?)", [(int)$u['id'], $genre, $objet, maintenant(), max($avant, $jusqua)]);
    // Le menu de cette page-ci ne compte déjà plus ce qui vient d'être ouvert
    if (isset($GLOBALS['mc_pastilles'])) {
        $r = &$GLOBALS['mc_pastilles'];
        if ($genre === 'outil') $r['outil'] = array_values(array_filter($r['outil'], fn($n) => $n['id'] !== $objet));
        elseif (in_array($genre, ['commande', 'client'], true)) {
            $cle = $genre === 'commande' ? 'commandes' : 'clients';
            if (isset($r[$cle][(int)$objet]) && (int)$r[$cle][(int)$objet]['evs'][0]['id'] <= $jusqua) {
                if ($cle === 'commandes' && $r[$cle][(int)$objet]['niveau'] === 'action') $r['action']--;
                unset($r[$cle][(int)$objet]);
            }
            $r['change_commandes'] = (bool)array_filter($r['commandes'], fn($o) => $o['niveau'] === 'change');
            $r['change_clients'] = (bool)$r['clients'];
        }
    }
}
/** « Tout marquer comme vu » : pour la personne seule, jusqu'au dernier événement existant. */
function page_pastilles(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') rediriger('accueil');
    csrf_verifier();
    $u = utilisateur_courant();
    q("UPDATE utilisateurs SET pastilles_id = ?, pastilles_depuis = ? WHERE id = ?", [(int)(val("SELECT MAX(id) FROM evenements") ?? 0), maintenant(), (int)$u['id']]);
    foreach (NOUVEAUTES as $n) pastilles_vu('outil', $n['id']);
    flash('ok', 'Tout est marqué comme vu.');
    rediriger(texte('retour', 20) === 'nouveautes' ? 'nouveautes' : 'accueil');
}

/* ---------- Affichage ---------- */

/** Une pastille de menu : un chiffre rouge, ou un point (ambre ou chocolat), avec un texte pour les lecteurs d'écran. */
function pastille_menu(int $n, string $point = ''): string {
    if ($n > 0) return '<span class="pastille" aria-hidden="true">' . ($n > 9 ? '9+' : $n) . '</span><span class="sr"> (' . $n . ' à regarder)</span>';
    if ($point !== '') return '<span class="point point-' . $point . '" aria-hidden="true"></span><span class="sr"> (' . ($point === 'outil' ? 'nouveauté' : 'a changé') . ')</span>';
    return '';
}
/** L'étiquette d'une carte : un point de couleur et un mot. */
function etiquette_pastille(array $o): string {
    $lib = $o['evs'][0]['libelle'];
    if ($o['niveau'] === 'action') foreach ($o['evs'] as $e) if ($e['niveau'] === 'action') { $lib = $e['libelle']; break; }
    $n = (int)$o['n'];
    return '<span class="etiq etiq-' . $o['niveau'] . '">' . h($lib) . '</span>' . ($n > 1 ? '<span class="etiq-plus">+' . ($n - 1) . '</span>' : '');
}
/** Le prénom de l'auteur d'un changement (« Olivier »), jamais traduit. */
function pastille_qui(array $e): string {
    $a = (string)$e['auteur'];
    if ($a === 'site') return '';
    if ($e['type'] === 'email_recu') return '';
    return explode(' ', trim($a))[0] ?? $a;
}
/** Le détail d'un changement, en morceaux traduisibles (étapes, paiements) ou protégés (textes des clients et notes). */
function pastille_detail(array $e): string {
    $t = (string)$e['type'];
    $court = fn(string $s, int $n = 90) => mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
    switch ($t) {
        case 'statut':
            if (isset(STATUTS[$e['ancien']], STATUTS[$e['nouveau']])) return '<span>' . h(STATUTS[$e['ancien']]) . '</span> → <span>' . h(STATUTS[$e['nouveau']]) . '</span>';
            break;
        case 'paiement':
            if (isset(PAIEMENTS[$e['ancien']], PAIEMENTS[$e['nouveau']])) return '<span>' . h(PAIEMENTS[$e['ancien']]) . '</span> → <span>' . h(PAIEMENTS[$e['nouveau']]) . '</span>';
            break;
        case 'modification':
            if ($e['commande_id'] !== null && (string)$e['nouveau'] !== '') return '<span>' . h((string)$e['nouveau']) . '</span>';
            if ($e['commande_id'] === null && preg_match('/^Fiche modifiée : (.+)$/u', (string)$e['texte'], $m)) {
                // « telephone, ville » → « téléphone, ville », un mot par morceau pour la traduction coréenne
                $noms = array_map(fn($c) => '<span>' . h(CHAMPS_FICHE[$c] ?? $c) . '</span>', explode(', ', $m[1]));
                return implode(', ', $noms);
            }
            break;
        case 'email_recu':
            $x = trim(preg_replace('/\s+/u', ' ', (string)$e['nouveau']) ?? '');
            return $x !== '' ? '« <span translate="no" lang="fr">' . h($court($x)) . '</span> »' : '';
        case 'commentaire':
            return '<span translate="no" lang="fr">' . h($court((string)$e['texte'])) . '</span>';
        case 'creation':
            if ($e['commande_id'] !== null) {
                $k = commande((int)$e['commande_id']);
                if (!$k) return '';
                $x = commande_contenu_texte($k, true);
                return $x === 'Personnalisé' ? '<span>' . h($x) . '</span>' : '<span translate="no">' . h($court($x)) . '</span>';   // les noms des gâteaux ne se traduisent pas
            }
            return '';
    }
    return '<span>' . h($court((string)$e['texte'], 120)) . '</span>';
}
/** Une ligne du bloc d'Aujourd'hui et de « Quoi de neuf ». */
function ligne_pastille(array $o): string {
    $e = $o['evs'][0];
    if ($o['niveau'] === 'action') foreach ($o['evs'] as $x) if ($x['niveau'] === 'action') { $e = $x; break; }
    $qui = pastille_qui($e);
    $lien = $o['genre'] === 'commande' ? u('commande', ['id' => $o['id']]) : u('client', ['id' => $o['id']]);
    $n = (int)$o['n'];
    $detail = pastille_detail($e);
    return '<li><a class="ligne-pastille" href="' . h($lien) . '"><span class="d d-' . $o['niveau'] . '" aria-hidden="true"></span>'
         . '<span class="quoi"><b>' . h($e['libelle']) . '</b>' . ($qui !== '' ? ' <span class="par" translate="no">' . h($qui) . '</span>' : '')
         . ' · <span translate="no">' . h($o['nom'] !== '' ? $o['nom'] : $o['reference']) . '</span>'
         . ($detail !== '' ? '<span class="q">' . $detail . '</span>' : '')
         . ($n > 1 ? '<span class="q autres">' . ($n - 1 === 1 ? '1 autre changement' : ($n - 1) . ' autres changements') . '</span>' : '') . '</span>'
         . '<time datetime="' . h(str_replace(' ', 'T', (string)$e['le'])) . '">' . h(date_fr((string)$e['le'], 'relative')) . '</time></a></li>';
}
/** Les objets signalés, rouges d'abord, puis du plus récent au plus ancien. */
function pastilles_triees(): array {
    $p = pastilles();
    $l = array_merge(array_values($p['commandes']), array_values($p['clients']));
    usort($l, fn($a, $b) => [$a['niveau'] === 'action' ? 0 : 1, $b['le']] <=> [$b['niveau'] === 'action' ? 0 : 1, $a['le']]);
    return $l;
}
function ligne_nouveaute(array $n, bool $nouveau): string {
    return '<li><a class="ligne-pastille" href="' . h(u($n['lien'][0]) . '#' . $n['lien'][1]) . '"><span class="d d-outil" aria-hidden="true">✦</span>'
         . '<span class="quoi"><b>' . h($n['titre']) . '</b>' . ($nouveau ? ' <span class="etiq etiq-outil">Nouveauté</span>' : '') . '<span class="q">' . h($n['texte']) . '</span></span>'
         . '<time datetime="' . h($n['le']) . '">' . h(date_fr($n['le'])) . '</time></a></li>';
}
/** Le bloc « Depuis votre dernière visite » d'Aujourd'hui ; rien s'il n'y a rien. */
function bloc_depuis_visite(): string {
    $p = pastilles();
    $l = pastilles_triees();
    $total = count($l) + count($p['outil']);
    if (!$total) return '';
    $s = '';
    foreach (array_slice($l, 0, 6) as $o) $s .= ligne_pastille($o);
    if (count($l) < 6) foreach (array_slice($p['outil'], 0, 6 - count($l)) as $n) $s .= ligne_nouveaute($n, false);
    $legende = '<p class="legende-pastilles"><span><span class="d d-action" aria-hidden="true"></span>À regarder en premier</span><span><span class="d d-change" aria-hidden="true"></span>A changé</span><span><span class="d d-outil" aria-hidden="true">✦</span>Nouveau dans l\'outil</span></p>';
    return '<section class="bloc depuis-visite" id="depuis" aria-labelledby="depuis-titre"><h2 id="depuis-titre">' . icone('cloche') . '<span>Depuis votre dernière visite</span></h2>'
         . $legende . '<ul class="liste-pastilles">' . $s . '</ul>'
         . '<div class="depuis-bas">' . ($total > 6 || $p['outil'] ? '<a href="' . h(u('nouveautes')) . '">Tout voir (' . $total . ')</a>' : '<span></span>')
         . formulaire('tout_vu', '<input type="hidden" name="retour" value="accueil">' . bouton('Tout marquer comme vu', 'discret'), [], u('pastilles')) . '</div></section>';
}
/** L'encadré de la commande (ou de la fiche) : ce qui a changé depuis la dernière visite de la personne, par d'autres. */
function encadre_changements(string $genre, int $id): string {
    $p = pastilles();
    $o = $p[$genre === 'commande' ? 'commandes' : 'clients'][$id] ?? null;
    if (!$o) return '';
    // Les réponses du client se lisent dans la conversation : ici, un renvoi seulement
    $reponses = count(array_filter($o['evs'], fn($e) => $e['type'] === 'email_recu'));
    $autres = array_values(array_filter($o['evs'], fn($e) => $e['type'] !== 'email_recu'));
    $s = '';
    foreach (array_slice($autres, 0, 8) as $e) {
        $qui = pastille_qui($e);
        $d = pastille_detail($e);
        $s .= '<li><span class="d d-' . $e['niveau'] . '" aria-hidden="true"></span><span><b>' . h($e['libelle']) . '</b>'
            . ($d !== '' ? ' : ' . $d : '') . '<span class="q">' . ($qui !== '' ? '<span class="par" translate="no">' . h($qui) . '</span> · ' : '')
            . '<time>' . h(date_fr((string)$e['le'], 'relative')) . '</time></span></span></li>';
    }
    if ($reponses) $s .= '<li><span class="d d-action" aria-hidden="true"></span><span><b>Le client a répondu</b> : <a href="#echanges">' . ($reponses > 1 ? $reponses . ' nouvelles réponses' : 'voir la réponse') . '</a></span></li>';
    return '<section class="encadre-changements encadre-' . $o['niveau'] . '" role="status" aria-label="Changé depuis votre dernière visite">'
         . '<p class="titre-changements">' . icone('cloche') . '<span>Changé depuis votre dernière visite</span></p><ul>' . $s . '</ul></section>';
}

/** « Quoi de neuf » (menu Plus) : tout ce qui est signalé à la personne, et les nouveautés de l'outil, qui sont alors vues. */
function page_nouveautes(): void {
    $p = pastilles();
    $l = pastilles_triees();
    $c = titre_page('Quoi de neuf', 'Ce qui est nouveau ou a changé depuis votre dernière visite', '', u('plus'));
    if ($l) {
        $s = '';
        foreach ($l as $o) $s .= ligne_pastille($o);
        $c .= bloc('Commandes et clients', '<ul class="liste-pastilles">' . $s . '</ul>'
            . '<div class="depuis-bas"><span></span>' . formulaire('tout_vu', '<input type="hidden" name="retour" value="nouveautes">' . bouton('Tout marquer comme vu', 'discret'), [], u('pastilles')) . '</div>');
    } else {
        $c .= bloc('Commandes et clients', '<p class="vide">Rien de nouveau : vous avez tout vu.</p>');
    }
    $nouv = array_column($p['outil'], 'id');
    $s = '';
    foreach (array_slice(NOUVEAUTES, 0, 12) as $n) $s .= ligne_nouveaute($n, in_array($n['id'], $nouv, true));
    $c .= bloc('Dans l\'outil', '<ul class="liste-pastilles">' . $s . '</ul>');
    foreach ($nouv as $id) pastilles_vu('outil', $id);   // vues : le point de Plus s'éteint à la page suivante
    mise_en_page('Quoi de neuf', $c, 'nouveautes');
}

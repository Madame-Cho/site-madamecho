<?php
/*
 * Madame Cho : espace de gestion. Mise en page et petits morceaux d'écran.
 * Politique de sécurité du site (CSP) : ni style ni script en ligne ; tout passe par gestion.css et gestion.js.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

function entetes_securite(): void {
    if (headers_sent()) return;
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
}

/** Icônes au trait (SVG en ligne : permis par la politique du site, qui ne bloque que styles et scripts). */
function icone(string $n): string {
    $p = match ($n) {
        'jour' => '<path d="M4 5h16v15H4z"/><path d="M4 10h16M9 3v4M15 3v4"/><path d="M8 14h3v3H8z"/>',
        'commandes' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
        'clients' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20c.6-3.6 2.9-5.5 5.5-5.5s4.9 1.9 5.5 5.5"/><path d="M15.5 5.2a3 3 0 0 1 0 5.6M17.5 14.8c1.6.6 2.7 2.3 3 5.2"/>',
        'produits' => '<path d="M4 12h16l-1.5 8h-13z"/><path d="M7 12c0-3 2.2-5 5-5s5 2 5 5"/><path d="M12 4v3"/>',
        'plus' => '<circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>',
        'ajouter' => '<path d="M12 5v14M5 12h14"/>',
        'retour' => '<path d="M15 5l-7 7 7 7"/>',
        'email' => '<path d="M3 6h18v12H3z"/><path d="M3 7l9 6 9-6"/>',
        'tel' => '<path d="M6.5 3.5l3 3-2 2.5c1.3 2.6 3.4 4.7 6 6l2.5-2 3 3-2 2.5C10.6 18.4 5.6 13.4 4 6.5z"/>',
        'agenda' => '<path d="M4 5h16v15H4z"/><path d="M4 10h16M9 3v4M15 3v4"/><path d="M12 13v4M10 15h4"/>',
        'avis' => '<path d="M12 4l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.6-4.8 2.6.9-5.4L4.2 9.7l5.4-.8z"/>',
        'imprimer' => '<path d="M7 9V3h10v6"/><path d="M5 9h14v8h-3v4H8v-4H5z"/>',
        'chercher' => '<circle cx="10.5" cy="10.5" r="6"/><path d="M15 15l5 5"/>',
        'alerte' => '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.5"/>',
        'copier' => '<path d="M8 8h12v12H8z"/><path d="M4 16V4h12"/>',
        'stats' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'reglages' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
        'sortir' => '<path d="M10 4H4v16h6"/><path d="M14 8l4 4-4 4M18 12H9"/>',
        'crayon' => '<path d="M4 20l1-4.5L16 4.5l3.5 3.5L8.5 19z"/><path d="M14 7l3 3"/>',
        default => '',
    };
    return '<svg class="ic" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}

/** Adresse d'un fichier d'habillage avec sa version (date du fichier) : le navigateur reprend le nouveau dès qu'il change. */
function fichier_versionne(string $f): string { return $f . '?v=' . (@filemtime(dirname(__DIR__) . '/' . $f) ?: GESTION_VERSION); }
/** Une page complète. $actif : l'onglet allumé (accueil, commandes, clients, produits, plus). */
function mise_en_page(string $titre, string $corps, string $actif = '', string $classe = ''): void {
    entetes_securite();
    $u = utilisateur_courant();
    $flash = flash_lire();
    $nav = '';
    $onglets = '';
    if ($u) {
        $items = [['accueil', 'Aujourd\'hui', 'jour'], ['commandes', 'Commandes', 'commandes'], ['clients', 'Clients', 'clients'], ['produits', 'Produits', 'produits']];
        foreach ($items as [$p, $l, $i]) {
            $a = $actif === $p ? ' aria-current="page"' : '';
            $nav .= '<a href="' . h(u($p)) . '"' . $a . '>' . h($l) . '</a>';
            $onglets .= '<a href="' . h(u($p)) . '"' . $a . '>' . icone($i) . '<span>' . h($l) . '</span></a>';
        }
        $nav .= '<a href="' . h(u('statistiques')) . '"' . ($actif === 'statistiques' ? ' aria-current="page"' : '') . '>Statistiques</a>'
              . '<a href="' . h(u('production')) . '"' . ($actif === 'production' ? ' aria-current="page"' : '') . '>Production</a>'
              . '<a href="' . h(u('reglages')) . '"' . ($actif === 'reglages' ? ' aria-current="page"' : '') . '>Réglages</a>';
        $onglets .= '<a href="' . h(u('plus')) . '"' . (in_array($actif, ['plus', 'statistiques', 'production', 'reglages', 'ingredients'], true) ? ' aria-current="page"' : '') . '>' . icone('plus') . '<span>Plus</span></a>';
    }
    $f = '';
    if ($flash) $f = '<div class="flash flash-' . h($flash[0]) . '" role="status">' . nl2br(h($flash[1])) . '</div>';
    $essai = GESTION_ESSAI ? '<div class="bandeau-essai" role="note">Essai en local : aucun email ne part, les données sont fictives.</div>' : '';
    $page = '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
       . '<meta name="robots" content="noindex, nofollow, noarchive">'
       . '<title>' . h($titre) . ' · Gestion Madame Cho</title>'
       . '<link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">'
       . '<link rel="apple-touch-icon" href="../assets/apple-touch-icon.png">'
       . '<link rel="manifest" href="' . h(fichier_versionne('gestion.webmanifest')) . '">'
       . '<meta name="theme-color" content="#3B1D0E">'
       . '<link rel="preload" href="../assets/polices/jost-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>'
       . '<link rel="stylesheet" href="' . h(fichier_versionne('gestion.css')) . '">'
       . '</head><body class="' . h(trim(($u ? 'connecte ' : 'deconnecte ') . $classe)) . '">' . $essai
       . '<a class="aller" href="#contenu">Aller au contenu</a>'
       . '<header class="barre"><a class="marque" href="' . h($u ? u('accueil') : u('connexion')) . '"><img src="../assets/logo-chocolat.svg" alt="Madame Cho" width="112" height="33"><span>' . h(GESTION_NOM) . '</span></a>'
       . ($u ? '<nav class="nav-haut" aria-label="Menu">' . $nav . '</nav>' : '')
       . bouton_langue()
       . ($u ? '<a class="nouvelle" href="' . h(u('commande-edition')) . '" aria-label="Nouvelle commande">' . icone('ajouter') . '<span>Nouvelle commande</span></a>' : '')
       . '</header>'
       . '<main id="contenu" class="page">' . $f . rappel_francais($corps) . $corps . '</main>'
       . ($u ? '<nav class="onglets" aria-label="Menu principal">' . $onglets . '</nav>' : '')
       . '<script src="' . h(fichier_versionne('gestion.js')) . '"></script>'
       . '</body></html>';
    echo traduire_page($page);
    exit;
}

function page_indisponible(): void {
    entetes_securite();
    http_response_code(503);
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex, nofollow"><title>Indisponible · Gestion Madame Cho</title><link rel="stylesheet" href="' . h(fichier_versionne('gestion.css')) . '"></head>'
       . '<body class="deconnecte"><main class="page etroite"><h1>La gestion est momentanément indisponible</h1>'
       . '<p>La base de données ne répond pas. Les demandes du site continuent d\'arriver par email à contact@madamecho.com. Prévenez Olivier.</p>'
       . '<h2 lang="ko">관리 화면을 잠시 쓸 수 없어요</h2>'
       . '<p lang="ko">데이터베이스가 응답하지 않아요. 사이트 문의는 계속 contact@madamecho.com으로 이메일이 와요. Olivier에게 알려 주세요.</p></main></body></html>';
    exit;
}

function titre_page(string $titre, string $sous = '', string $actions = '', string $retour = ''): string {
    $r = $retour !== '' ? '<a class="retour" href="' . h($retour) . '">' . icone('retour') . '<span>Retour</span></a>' : '';
    return '<div class="tete">' . $r . '<div class="tete-texte"><h1>' . $titre . '</h1>' . ($sous !== '' ? '<p class="sous">' . $sous . '</p>' : '') . '</div>'
         . ($actions !== '' ? '<div class="tete-actions">' . $actions . '</div>' : '') . '</div>';
}
function chip_statut(string $s, bool $court = true): string {
    $l = ($court ? STATUTS_COURTS : STATUTS)[$s] ?? $s;
    return '<span class="statut s-' . h(str_replace('_', '-', $s)) . '">' . h($l) . '</span>';
}
function chip_paiement(string $p, int $acompte = 0): string {
    if ($p === 'aucun') return '';
    $l = PAIEMENTS[$p] ?? $p;
    if ($acompte > 0 && in_array($p, ['acompte_demande', 'acompte_recu'], true)) $l .= ' · ' . euros($acompte);
    return '<span class="paiement p-' . h(str_replace('_', '-', $p)) . '">' . h($l) . '</span>';
}
function drapeau(string $note = ''): string {
    return '<span class="drapeau" translate="no"' . ($note !== '' ? ' title="' . h($note) . '"' : '') . '>' . icone('alerte') . h(fr_ko('Problème', '문제')) . '</span>';
}
function retrait_texte(array $k, bool $court = false): string {
    $livraison = ($k['mode'] ?? 'retrait') === 'livraison';
    if (!$k['retrait_date']) {
        $statut = $k['statut'] ?? '';
        if ($statut === 'remise' && !empty($k['remise_le'])) return ($livraison ? 'Livrée le ' : 'Remise le ') . date_fr(substr((string)$k['remise_le'], 0, 10), $court ? 'courte' : 'jour');
        if ($statut === 'annulee' || $statut === 'sans_suite') return 'Sans date';
        return $livraison ? 'Date de livraison à fixer' : 'Date de retrait à fixer';
    }
    $d = date_fr($k['retrait_date'], $court ? 'courte' : 'jour');
    if ($court) { $t = strtotime($k['retrait_date']); $d = mb_substr(JOURS[(int)date('w', $t)], 0, 3) . '. ' . $d; }
    $m = $k['retrait_heure'] !== '' ? str_replace(':', ' h ', $k['retrait_heure']) : (MOMENTS_COURTS[$k['retrait_moment']] ?? '');
    return $d . ($m !== '' ? ', ' . $m : '') . (($k['mode'] ?? 'retrait') === 'livraison' ? ' (livraison)' : '');
}
function montant_commande(array $k): int { return $k['prix_final'] !== null ? (int)$k['prix_final'] : (int)$k['total_indicatif']; }
function carte_commande(array $k, bool $avecClient = true): string {
    $alertes = '';
    if (!empty($k['probleme'])) $alertes .= drapeau((string)$k['probleme_note']);
    if (commande_en_retard($k)) $alertes .= '<span class="retard">Sans réponse depuis ' . heures_depuis($k['recue_le']) . ' h</span>';
    elseif (commande_dormante($k)) $alertes .= '<span class="dormant">Rien depuis ' . jours_depuis($k['dernier_echange']) . ' j</span>';
    $nom = $avecClient ? '<b translate="no">' . h(commande_client_nom($k)) . '</b>' : '<b>' . h(TYPES_COMMANDE[$k['type']] ?? 'Commande') . '</b>';
    $montant = montant_commande($k);
    return '<a class="carte-cmd" href="' . h(u('commande', ['id' => $k['id']])) . '">'
         . '<span class="l1">' . $nom . chip_statut($k['statut']) . '</span>'
         . '<span class="l2">' . h(retrait_texte($k, true)) . ($montant ? ' · ' . h(euros($montant)) : '') . chip_paiement($k['paiement'], (int)$k['acompte']) . '</span>'
         . '<span class="l3">' . h(commande_contenu_texte($k, true)) . '</span>'
         . '<span class="l4">' . h($k['reference']) . ' · ' . h(CANAUX[$k['canal']] ?? $k['canal']) . ' · reçue ' . h(date_fr($k['recue_le'], 'relative')) . '</span>'
         . ($alertes !== '' ? '<span class="l5">' . $alertes . '</span>' : '')
         . '</a>';
}
function liste_commandes(array $ks, string $vide, bool $avecClient = true): string {
    if (!$ks) return '<p class="vide">' . h($vide) . '</p>';
    return '<div class="liste">' . implode('', array_map(fn($k) => carte_commande($k, $avecClient), $ks)) . '</div>';
}

/* ---------- Formulaires ---------- */
function attrs(array $a): string {
    $s = '';
    foreach ($a as $k => $v) {
        if ($v === false || $v === null) continue;
        $s .= $v === true ? ' ' . h($k) : ' ' . h($k) . '="' . h($v) . '"';
    }
    return $s;
}
function champ(string $nom, string $label, mixed $valeur = '', array $a = [], string $aide = ''): string {
    $type = $a['type'] ?? 'text'; unset($a['type']);
    $id = $a['id'] ?? 'f-' . preg_replace('/[^a-z0-9]+/i', '-', $nom); unset($a['id']);
    $ai = $aide !== '' ? '<span class="aide" id="' . h($id) . '-aide">' . $aide . '</span>' : '';
    if ($aide !== '') $a['aria-describedby'] = $id . '-aide';
    return '<label class="champ" for="' . h($id) . '"><span class="etiq">' . $label . '</span>'
         . '<input type="' . h($type) . '" name="' . h($nom) . '" id="' . h($id) . '" value="' . h($valeur) . '"' . attrs($a) . '>' . $ai . '</label>';
}
function zone(string $nom, string $label, mixed $valeur = '', array $a = [], string $aide = ''): string {
    $id = $a['id'] ?? 'f-' . preg_replace('/[^a-z0-9]+/i', '-', $nom); unset($a['id']);
    $a += ['rows' => '3'];
    $ai = $aide !== '' ? '<span class="aide" id="' . h($id) . '-aide">' . $aide . '</span>' : '';
    if ($aide !== '') $a['aria-describedby'] = $id . '-aide';
    return '<label class="champ" for="' . h($id) . '"><span class="etiq">' . $label . '</span><textarea name="' . h($nom) . '" id="' . h($id) . '"' . attrs($a) . '>' . h($valeur) . '</textarea>' . $ai . '</label>';
}
function liste_choix(string $nom, string $label, array $options, mixed $valeur = '', array $a = [], string $aide = ''): string {
    $id = $a['id'] ?? 'f-' . preg_replace('/[^a-z0-9]+/i', '-', $nom); unset($a['id']);
    $o = '';
    foreach ($options as $k => $l) {
        if (is_array($l)) {   // groupe
            $o .= '<optgroup label="' . h($k) . '">';
            foreach ($l as $k2 => $l2) $o .= '<option value="' . h($k2) . '"' . ((string)$k2 === (string)$valeur ? ' selected' : '') . '>' . h($l2) . '</option>';
            $o .= '</optgroup>';
        } else $o .= '<option value="' . h($k) . '"' . ((string)$k === (string)$valeur ? ' selected' : '') . '>' . h($l) . '</option>';
    }
    $ai = $aide !== '' ? '<span class="aide">' . $aide . '</span>' : '';
    return '<label class="champ" for="' . h($id) . '"><span class="etiq">' . $label . '</span><select name="' . h($nom) . '" id="' . h($id) . '"' . attrs($a) . '>' . $o . '</select>' . $ai . '</label>';
}
function case_a_cocher(string $nom, string $label, bool $coche = false, string $valeur = '1'): string {
    return '<label class="case"><input type="checkbox" name="' . h($nom) . '" value="' . h($valeur) . '"' . ($coche ? ' checked' : '') . '><span>' . $label . '</span></label>';
}
/** Formulaire POST vers la page courante, avec jeton anti-falsification et action nommée. */
function formulaire(string $action, string $contenu, array $a = [], string $page = ''): string {
    $cible = $page !== '' ? $page : ($_SERVER['REQUEST_URI'] ?? '');
    return '<form method="post" action="' . h($cible) . '"' . attrs($a) . '>' . csrf_champ() . '<input type="hidden" name="_action" value="' . h($action) . '">' . $contenu . '</form>';
}
function bouton(string $label, string $classe = '', array $a = []): string {
    return '<button type="submit" class="btn ' . h($classe) . '"' . attrs($a) . '>' . $label . '</button>';
}
function lien_bouton(string $href, string $label, string $classe = '', array $a = []): string {
    return '<a class="btn ' . h($classe) . '" href="' . h($href) . '"' . attrs($a) . '>' . $label . '</a>';
}
function bloc(string $titre, string $contenu, string $classe = '', string $id = ''): string {
    return '<section class="bloc ' . h($classe) . '"' . ($id !== '' ? ' id="' . h($id) . '"' : '') . '>' . ($titre !== '' ? '<h2>' . $titre . '</h2>' : '') . $contenu . '</section>';
}
function tuile(string $label, string $valeur, string $detail = ''): string {
    return '<div class="tuile"><span class="t-label">' . h($label) . '</span><span class="t-valeur">' . h($valeur) . '</span>' . ($detail !== '' ? '<span class="t-detail">' . h($detail) . '</span>' : '') . '</div>';
}
function definition(array $paires): string {
    $s = '';
    foreach ($paires as $k => $v) if ($v !== '' && $v !== null) $s .= '<div><dt>' . h($k) . '</dt><dd>' . $v . '</dd></div>';
    return '<dl class="defs">' . $s . '</dl>';
}

/* ---------- Historique ---------- */
function fil_historique(array $evs, bool $avecReference = false): string {
    if (!$evs) return '<p class="vide">Rien pour l\'instant.</p>';
    $s = '';
    foreach ($evs as $e) {
        $type = $e['type'];
        $cl = $type === 'commentaire' ? 'ev-commentaire' : ($type === 'probleme' ? 'ev-probleme' : 'ev-systeme');
        $ref = $avecReference && !empty($e['reference']) ? ' · <a href="' . h(u('commande', ['id' => $e['commande_id']])) . '">' . h($e['reference']) . '</a>' : '';
        $txt = $type === 'statut' && isset(STATUTS[$e['nouveau']]) ? chip_statut($e['nouveau'], false) . (($p = mb_strpos($e['texte'], ' : ')) !== false ? ' ' . h(mb_substr($e['texte'], $p + 3)) : '') : nl2br(h($e['texte']));
        $s .= '<li class="' . $cl . '"><div class="ev-texte"' . ($type === 'commentaire' ? ' translate="no" lang="fr"' : '') . '>' . $txt . '</div><div class="ev-meta">' . h($e['auteur']) . ' · ' . h(date_fr($e['le'], 'heure')) . $ref . '</div></li>';
    }
    return '<ol class="fil">' . $s . '</ol>';
}
function formulaire_commentaire(string $aide = ''): string {
    return formulaire('commentaire', zone('texte', 'Ajouter un commentaire', '', ['rows' => '2', 'maxlength' => '2000', 'required' => true],
        $aide !== '' ? $aide : 'Utile et factuel. Jamais d\'information de santé : pour une allergie, notez seulement l\'ingrédient à éviter.') . bouton('Ajouter', 'secondaire'), ['class' => 'form-commentaire']);
}

/* ---------- Textes d'email (brouillons que Youngmi relit et envoie elle-même, R-01) ---------- */
function signature(): string { return parametre('signature', "Youngmi Cho\nMadame Cho · 06 61 31 17 79\nmadamecho.com") ?? ''; }
function salutation(array $k): string {
    $p = trim((string)($k['client_prenom'] ?? ''));
    $n = trim((string)($k['client_nom'] ?? ''));
    if (($k['client_type'] ?? '') === 'professionnel' && ($k['client_contact'] ?? '') !== '') return 'Bonjour ' . $k['client_contact'] . ',';
    return 'Bonjour' . ($p !== '' ? ' ' . $p : ($n !== '' ? ' ' . $n : '')) . ',';
}
function lignes_email(array $k): string {
    $t = [];
    foreach ($k['lignes_tab'] as $l) {
        $t[] = '- ' . $l['q'] . ' × ' . $l['nom'] . ', ' . $l['libelle'] . ' : ' . euros($l['prix'] * $l['q']);
        if (($l['plaque'] ?? '') !== '') $t[] = '  Plaque en chocolat : « ' . $l['plaque'] . ' »';
    }
    if ($k['perso'] !== '') $t[] = '- Gâteau personnalisé : ' . $k['perso'];
    if ($k['besoin'] !== '') $t[] = '- ' . $k['besoin'];
    return implode("\n", $t);
}
function retrait_email(array $k): string {
    if (!$k['retrait_date']) return 'Retrait : jour à convenir ensemble, au 4 avenue du Château, 92190 Meudon.';
    $quand = date_fr($k['retrait_date'], 'jour');
    $h = $k['retrait_heure'] !== '' ? ' à ' . str_replace(':', ' h ', $k['retrait_heure']) : (isset(MOMENTS[$k['retrait_moment']]) ? ', ' . mb_strtolower(MOMENTS[$k['retrait_moment']]) . ' (heure à convenir)' : '');
    return 'Retrait : ' . $quand . $h . ', au 4 avenue du Château, 92190 Meudon.';
}
function email_proposition(array $k): array {
    $total = montant_commande($k);
    $acompte = (int)$k['acompte'];
    $t = salutation($k) . "\n\nMerci pour votre demande " . $k['reference'] . ". Voici ma proposition :\n\n" . lignes_email($k) . "\n\n"
       . 'Total : ' . ($total ? euros($total) . ' TTC' : '[prix à indiquer]') . "\n" . retrait_email($k) . "\n";
    if ($acompte > 0) $t .= 'Pour réserver, un acompte de ' . euros($acompte) . " est demandé ; il est déduit du prix au retrait.\n";
    $t .= "\nPour confirmer, il suffit de répondre à cet email. Vous pouvez annuler sans frais jusqu'à 24 heures avant le retrait.\n\nBelle journée,\n" . signature();
    return ['sujet' => 'Re: Demande ' . $k['reference'], 'corps' => $t];
}
function email_confirmation(array $k): array {
    $total = montant_commande($k);
    $acompte = (int)$k['acompte'];
    $t = salutation($k) . "\n\nVotre commande " . $k['reference'] . " est confirmée :\n\n" . lignes_email($k) . "\n\n"
       . 'Total : ' . euros($total) . ' TTC' . ($k['paiement'] === 'acompte_recu' && $acompte ? ' (acompte de ' . euros($acompte) . ' reçu, reste ' . euros($total - $acompte) . ' à régler au retrait)' : ', à régler au retrait (carte, virement ou espèces)') . "\n"
       . retrait_email($k) . "\n\nÀ bientôt,\n" . signature();
    return ['sujet' => 'Votre commande ' . $k['reference'] . ' est confirmée', 'corps' => $t];
}
function email_avis(array $k, string $lien): array {
    $t = salutation($k) . "\n\nMerci encore pour votre commande. Si vous avez un moment, votre avis sur Google aiderait beaucoup Madame Cho à se faire connaître :\n" . $lien
       . "\n\nMerci et à bientôt,\n" . signature();
    return ['sujet' => 'Merci pour votre commande', 'corps' => $t];
}
function mailto(string $a, array $e): string {
    return 'mailto:' . rawurlencode($a) . '?subject=' . rawurlencode($e['sujet']) . '&body=' . rawurlencode($e['corps']);
}
/** Bloc « email prêt » : texte à relire, bouton pour ouvrir la messagerie, bouton copier. */
function bloc_email(string $id, string $titre, array $e, string $a): string {
    $ouvrir = $a !== '' ? lien_bouton(mailto($a, $e), icone('email') . 'Ouvrir dans ma messagerie', 'principal') : '<p class="aide">Pas d\'email pour ce client : copiez le texte dans un message.</p>';
    return '<details class="email-pret" id="' . h($id) . '"><summary>' . icone('email') . h($titre) . '</summary>'
         . '<p class="aide">Relisez et complétez avant d\'envoyer : c\'est vous qui envoyez, depuis contact@madamecho.com.</p>'
         . '<p class="sujet"><b>Objet :</b> <span translate="no" lang="fr">' . h($e['sujet']) . '</span></p><pre class="corps" id="' . h($id) . '-texte" translate="no" lang="fr">' . h($e['corps']) . '</pre>'
         . '<div class="actions">' . $ouvrir . '<button type="button" class="btn" data-copier="' . h($id) . '-texte">' . icone('copier') . 'Copier le texte</button></div></details>';
}

/* ---------- Agenda ---------- */
function creneau(array $k): ?array {
    if (!$k['retrait_date']) return null;
    $plages = ['matin' => ['09:00', '12:00'], 'midi' => ['12:00', '14:00'], 'apres-midi' => ['14:00', '18:00'], 'soir' => ['18:00', '19:00']];
    if (preg_match('/^\d{2}:\d{2}$/', (string)$k['retrait_heure'])) {
        $debut = $k['retrait_heure'];
        $fin = date('H:i', strtotime($k['retrait_date'] . ' ' . $debut) + 1800);
    } else [$debut, $fin] = $plages[$k['retrait_moment']] ?? ['09:00', '10:00'];
    return [$k['retrait_date'] . ' ' . $debut, $k['retrait_date'] . ' ' . $fin];
}
function agenda_titre(array $k): string {
    $resume = [];
    foreach ($k['lignes_tab'] as $l) $resume[] = $l['q'] . ' ' . $l['nom'];
    if ($k['perso'] !== '') $resume[] = 'personnalisé';
    return 'Retrait ' . commande_client_nom($k) . ($resume ? ' · ' . implode(', ', $resume) : '');
}
function lien_agenda_google(array $k): ?string {
    $c = creneau($k);
    if (!$c) return null;
    $f = fn($s) => date('Ymd\THis', strtotime($s));
    $details = $k['reference'] . "\n" . lignes_email($k) . "\n\n" . gestion_adresse() . u('commande', ['id' => $k['id']]);
    return 'https://calendar.google.com/calendar/render?' . http_build_query(['action' => 'TEMPLATE', 'text' => agenda_titre($k), 'dates' => $f($c[0]) . '/' . $f($c[1]),
        'ctz' => 'Europe/Paris', 'details' => $details, 'location' => '4 avenue du Château, 92190 Meudon', 'authuser' => 'contact@madamecho.com']);
}

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
        'suivant' => '<path d="M9 5l7 7-7 7"/>',
        // L'agenda : un calendrier et ses jours
        'mois' => '<path d="M4 5h16v15H4z"/><path d="M4 10h16M9 3v4M15 3v4"/><path d="M8 13.5h.01M12 13.5h.01M16 13.5h.01M8 17h.01M12 17h.01"/>',
        'email' => '<path d="M3 6h18v12H3z"/><path d="M3 7l9 6 9-6"/>',
        'tel' => '<path d="M6.5 3.5l3 3-2 2.5c1.3 2.6 3.4 4.7 6 6l2.5-2 3 3-2 2.5C10.6 18.4 5.6 13.4 4 6.5z"/>',
        'agenda' => '<path d="M4 5h16v15H4z"/><path d="M4 10h16M9 3v4M15 3v4"/><path d="M12 13v4M10 15h4"/>',
        'avis' => '<path d="M12 4l2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.6-4.8 2.6.9-5.4L4.2 9.7l5.4-.8z"/>',
        'cloche' => '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
        'imprimer' => '<path d="M7 9V3h10v6"/><path d="M5 9h14v8h-3v4H8v-4H5z"/>',
        'chercher' => '<circle cx="10.5" cy="10.5" r="6"/><path d="M15 15l5 5"/>',
        'alerte' => '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18v.5"/>',
        'copier' => '<path d="M8 8h12v12H8z"/><path d="M4 16V4h12"/>',
        'stats' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'reglages' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',   // engrenage, comme sur la plupart des sites (Olivier, 07/11/2026)
        'sortir' => '<path d="M10 4H4v16h6"/><path d="M14 8l4 4-4 4M18 12H9"/>',
        'crayon' => '<path d="M4 20l1-4.5L16 4.5l3.5 3.5L8.5 19z"/><path d="M14 7l3 3"/>',
        'livre' => '<path d="M4 4h6a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H4z"/><path d="M20 4h-6a2 2 0 0 0-2 2v14a2 2 0 0 1 2-2h6z"/>',
        'assistant' => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12h5"/>',
        'sms' => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 10.5h.01M12 10.5h.01M16 10.5h.01"/>',
        'whatsapp' => '<path d="M4.5 19.5l1.2-3.6A8 8 0 1 1 8.4 18.6z"/><path d="M9.2 8.6c0 3 2.4 5.6 5.5 6l1.1-1.3-1.8-1-1 .8c-1-.5-1.8-1.3-2.3-2.3l.8-1-1-1.8z"/>',
        'oral' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20c.6-3.6 2.9-5.5 5.5-5.5s4.9 1.9 5.5 5.5"/><path d="M16.5 6.5c1 .8 1.5 1.8 1.5 3s-.5 2.2-1.5 3M19 4.5c1.6 1.3 2.4 3 2.4 5s-.8 3.7-2.4 5"/>',
        'instagram' => '<rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.6"/><path d="M16.8 7.2h.01"/>',
        'paiement' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/>',
        // Étincelles : le signe habituel d'une IA (demande d'Olivier, 07/11/2026) ; l'assistant est le seul endroit de la gestion où une IA répond
        'ia' => '<path d="M10 3.5l1.6 4.4 4.4 1.6-4.4 1.6L10 15.5l-1.6-4.4L4 9.5l4.4-1.6z"/><path d="M18 13.5l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z"/><path d="M17.5 3v3M16 4.5h3"/>',
        // La flèche d'envoi et la nouvelle conversation, comme dans ChatGPT et Claude
        'envoyer' => '<path d="M12 19V5M5.5 11.5L12 5l6.5 6.5"/>',
        // Retour en bas de la conversation, comme dans Claude (demande d'Olivier, 09/11/2026)
        'vers-le-bas' => '<path d="M12 5v14M5.5 12.5L12 19l6.5-6.5"/>',
        'nouveau' => '<path d="M11 4H5v15h15v-6"/><path d="M18.5 3.5l2 2L12 14l-3 1 1-3z"/>',
        // Particulier (une personne) et professionnel (une boutique), sur les étiquettes de type
        'particulier' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c.8-4 3.6-6 7-6s6.2 2 7 6"/>',
        'pro' => '<path d="M4 10v10h16V10"/><path d="M3 10l2-6h14l2 6z"/><path d="M10 20v-5h4v5"/>',
        // Le mode vocal de l'assistant (parler, arrêter) et la confirmation d'une modification préparée (07/11/2026)
        'micro' => '<path d="M12 3.5a3 3 0 0 1 3 3V12a3 3 0 0 1-6 0V6.5a3 3 0 0 1 3-3z"/><path d="M5.5 11.5a6.5 6.5 0 0 0 13 0M12 18v3"/>',
        'stop' => '<rect x="6.5" y="6.5" width="11" height="11" rx="2"/>',
        'valider' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        // L'écran Obligations : une liste cochée sur un porte-documents (spécification 004)
        'hygiene' => '<path d="M10 4a2 2 0 0 1 4 0v10.3a4 4 0 1 1-4 0z"/><path d="M12 10v7"/><path d="M16.5 6h2M16.5 9h2M16.5 12h2"/>',
        'obligations' => '<path d="M6 4h12v17H6z"/><path d="M9 4V2.5h6V4"/><path d="M8.5 10l1.5 1.5 3-3M8.5 16l1.5 1.5 3-3M15 10.5h1M15 16.5h1"/>',
        'supprimer' => '<path d="M4 7h16M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13M10 11v6M14 11v6"/>',
        default => '',
    };
    return '<svg class="ic" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}

/** Adresse d'un fichier d'habillage avec sa version (date du fichier) : le navigateur reprend le nouveau dès qu'il change. */
function fichier_versionne(string $f): string { return $f . '?v=' . (@filemtime(dirname(__DIR__) . '/' . $f) ?: GESTION_VERSION); }
/** Une page complète. $actif : l'onglet allumé (accueil, agenda, commandes, clients, produits, plus). */
/** Le compte connecté, au bout de la barre du haut sur tous les écrans : nom, email, mot de passe et « Se déconnecter »
 *  (demande d'Olivier, 07/11/2026 : sur un grand écran, le menu Plus, qui portait la déconnexion, n'apparaît plus). */
function menu_compte(array $u): string {
    $nom = trim((string)($u['nom'] ?? ''));
    $initiale = mb_strtoupper(mb_substr($nom !== '' ? $nom : (string)$u['email'], 0, 1));
    return '<details class="menu-compte"><summary aria-label="Mon compte" title="Mon compte"><span aria-hidden="true" translate="no">' . h($initiale) . '</span></summary>'
         . '<div class="menu-compte-liste">'
         . '<p class="menu-compte-qui" translate="no">' . ($nom !== '' ? '<b>' . h($nom) . '</b>' : '') . '<span>' . h((string)$u['email']) . '</span></p>'
         . '<a href="' . h(u('reglages')) . '#compte">' . icone('reglages') . '<span>Mon mot de passe</span></a>'
         . '<a href="' . h(u('deconnexion', ['j' => csrf()])) . '">' . icone('sortir') . '<span>Se déconnecter</span></a>'
         . '</div></details>';
}

function mise_en_page(string $titre, string $corps, string $actif = '', string $classe = ''): void {
    entetes_securite();
    $u = utilisateur_courant();
    $flash = flash_lire();
    $nav = '';
    $onglets = '';
    if ($u) {
        // L'agenda, à côté d'Aujourd'hui (demande d'Olivier, 07/11/2026)
        $items = [['accueil', 'Aujourd\'hui', 'jour'], ['agenda', 'Agenda', 'mois'], ['commandes', 'Commandes', 'commandes'], ['clients', 'Clients', 'clients'], ['produits', 'Produits', 'produits']];
        // Les pastilles (FR-041) : un chiffre rouge pour ce qui demande d'agir, un point ambre pour ce qui a changé, chocolat pour l'outil
        $pa = pastilles();
        $marques = ['commandes' => pastille_menu($pa['action'], $pa['change_commandes'] ? 'change' : ''), 'clients' => pastille_menu(0, $pa['change_clients'] ? 'change' : '')];
        $outil = pastille_menu(0, $pa['outil'] ? 'outil' : '');
        foreach ($items as [$p, $l, $i]) {
            $a = $actif === $p ? ' aria-current="page"' : '';
            $m = $marques[$p] ?? '';
            $nav .= '<a href="' . h(u($p)) . '"' . $a . '>' . icone($i) . h($l) . $m . '</a>';   // une icône par page, aussi sur ordinateur (demande d'Olivier, 07/11/2026)
            $onglets .= '<a href="' . h(u($p)) . '"' . $a . '>' . icone($i) . '<span>' . h($l) . '</span>' . $m . '</a>';
        }
        // Sur ordinateur : les cinq pages du menu Plus en liens directs quand l'écran est large (≥ 1200 px),
        // un seul lien « Plus » sinon, pour que la barre reste sur une ligne
        $dansPlus = in_array($actif, ['plus', 'statistiques', 'production', 'reglages', 'ingredients', 'manuel', 'assistant', 'nouveautes', 'obligations', 'hygiene'], true);
        // Obligations (spécification 004) : un chiffre rouge pour ce qui est en retard ou dans son rappel, un point ambre si l'autre y a touché
        $nOb = function_exists(__NAMESPACE__ . '\\obligations_urgentes') ? obligations_urgentes() : 0;
        $ob = pastille_menu($nOb, $nOb === 0 && function_exists(__NAMESPACE__ . '\\obligations_changees') && (obligations_changees() || obligations_changees('echeance')) ? 'change' : '');
        $acces = function_exists(__NAMESPACE__ . '\\obligations_acces') && obligations_acces();
        foreach (array_merge($acces ? [['obligations', 'Obligations', 'obligations'], ['hygiene', 'Hygiène', 'hygiene']] : [], [['statistiques', 'Statistiques', 'stats'], ['production', 'Production', 'imprimer'], ['manuel', 'Manuel', 'livre'], ['assistant', 'Assistant', 'ia'], ['reglages', 'Paramètres', 'reglages']]) as [$p, $l, $i]) {
            $nav .= '<a class="secondaire" href="' . h(u($p)) . '"' . ($actif === $p ? ' aria-current="page"' : '') . '>' . icone($i) . h($l) . ($p === 'manuel' ? $outil : ($p === 'obligations' ? $ob : '')) . '</a>';
        }
        $plusMarque = $nOb > 0 ? pastille_menu($nOb) : ($outil !== '' ? $outil : $ob);
        $nav .= '<a class="plus" href="' . h(u('plus')) . '"' . ($dansPlus ? ' aria-current="page"' : '') . '>' . icone('plus') . 'Plus' . $plusMarque . '</a>';
        $onglets .= '<a href="' . h(u('plus')) . '"' . ($dansPlus ? ' aria-current="page"' : '') . '>' . icone('plus') . '<span>Plus</span>' . $plusMarque . '</a>';
    }
    $f = '';
    if ($flash) $f = '<div class="flash flash-' . h($flash[0]) . '" role="status">' . nl2br(h($flash[1])) . '</div>';
    $essai = GESTION_ESSAI ? '<div class="bandeau-essai" role="note">Essai en local : aucun email ne part, les données sont fictives.</div>' : '';
    $page = '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
       . '<meta name="google" content="notranslate">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
       . '<meta name="robots" content="noindex, nofollow, noarchive">'
       . '<title>' . h($titre) . ' · Gestion Madame Cho</title>'   // le nombre rouge est ajouté devant après la traduction (fin de mise_en_page)
       // Icônes de la gestion : le 조 du site aux couleurs inversées, chocolat sur ivoire, pour la distinguer du site (demande d'Olivier, 07/11/2026)
       . '<link rel="icon" href="' . h(fichier_versionne('icones/favicon.ico')) . '" sizes="48x48">'
       . '<link rel="icon" href="' . h(fichier_versionne('icones/favicon.svg')) . '" type="image/svg+xml">'
       . '<link rel="apple-touch-icon" href="' . h(fichier_versionne('icones/apple-touch-icon.png')) . '">'
       . '<link rel="manifest" href="' . h(fichier_versionne('gestion.webmanifest')) . '">'
       . '<meta name="theme-color" content="#3B1D0E">'
       . '<link rel="preload" href="../assets/polices/jost-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>'
       . '<link rel="stylesheet" href="' . h(fichier_versionne('gestion.css')) . '">'
       . '</head><body class="' . h(trim(($u ? 'connecte ' : 'deconnecte ') . $classe)) . '"'
       . ($u ? ' data-pastilles="' . (int)pastilles()['action'] . '"' : '')   // l'icône de l'application sur le téléphone (gestion.js)
       // « Corriger mon français » sous les zones de saisie (gestion.js) : seulement si l'assistant est relié
       . ($u && assistant_relie() ? ' data-corriger="' . h(u('corriger')) . '" data-jeton="' . h(csrf()) . '"' : '') . '>' . $essai
       // Les drapeaux des boutons de dictée (gestion.js les recopie)
       . ($u ? '<template id="drapeau-fr">' . DRAPEAU_FR . '</template><template id="drapeau-ko">' . DRAPEAU_KO . '</template>' : '')
       . '<a class="aller" href="#contenu">Aller au contenu</a>'
       . '<header class="barre"><a class="marque" href="' . h($u ? u('accueil') : u('connexion')) . '"><img src="../assets/logo-chocolat.svg" alt="Madame Cho" width="112" height="33"><span>' . h(GESTION_NOM) . '</span></a>'
       . ($u ? '<nav class="nav-haut" aria-label="Menu">' . $nav . '</nav>' : '')
       . bouton_langue()
       . ($u ? '<a class="nouvelle" href="' . h(u('commande-edition')) . '" aria-label="Nouvelle commande" title="Nouvelle commande">' . icone('ajouter') . '<span>Nouvelle commande</span></a>' . menu_compte($u) : '')
       . '</header>'
       . '<main id="contenu" class="page">' . $f . rappel_francais($corps) . $corps . '</main>'
       . ($u ? '<nav class="onglets" aria-label="Menu principal">' . $onglets . '</nav>' : '')
       . '<script src="' . h(fichier_versionne('gestion.js')) . '"></script>'
       . '</body></html>';
    // Ni Chrome ni Edge ne doivent retraduire l'écran : l'outil a son propre choix FR/KO, et la traduction automatique
    // remettait le coréen en français. translate="no" n'est posé sur <html> qu'après la traduction coréenne, qui laisse
    // de côté tout ce qui porte cet attribut.
    $page = preg_replace('/<html lang="(fr|ko)">/', '<html lang="$1" translate="no">', traduire_page($page), 1);
    // « (3) Aujourd'hui » dans l'onglet du navigateur : le nombre de commandes où il faut agir (FR-041)
    $n = $u ? (int)pastilles()['action'] : 0;
    echo $n > 0 ? preg_replace('/<title>/', '<title>(' . $n . ') ', $page, 1) : $page;
    exit;
}

function page_indisponible(): void {
    entetes_securite();
    http_response_code(503);
    echo '<!doctype html><html lang="fr" translate="no"><head><meta charset="utf-8"><meta name="google" content="notranslate"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex, nofollow"><title>Indisponible · Gestion Madame Cho</title><link rel="stylesheet" href="' . h(fichier_versionne('gestion.css')) . '"></head>'
       . '<body class="deconnecte"><main class="page etroite"><h1>La gestion est momentanément indisponible</h1>'
       . '<p>La base de données ne répond pas. Les demandes du site continuent d\'arriver par email à contact@madamecho.com. Prévenez Olivier.</p>'
       . '<h2 lang="ko">관리 화면을 잠시 쓸 수 없어요</h2>'
       . '<p lang="ko">데이터베이스가 응답하지 않아요. 사이트 문의는 계속 contact@madamecho.com으로 이메일이 와요. Olivier에게 알려 주세요.</p></main></body></html>';
    exit;
}

const ICONES_PAGES = ['accueil' => 'jour', 'agenda' => 'mois', 'commandes' => 'commandes', 'commande' => 'commandes', 'commande-edition' => 'commandes',
    'clients' => 'clients', 'client' => 'clients', 'client-edition' => 'clients', 'releve' => 'imprimer', 'produits' => 'produits', 'produit' => 'produits',
    'ingredients' => 'produits', 'ingredient' => 'produits', 'statistiques' => 'stats', 'taxe' => 'stats', 'production' => 'imprimer', 'plus' => 'plus',
    'rapprochement' => 'paiement', 'corbeille' => 'supprimer', 'manuel' => 'livre', 'aide' => 'livre', 'assistant' => 'ia', 'olivier' => 'ia', 'reglages' => 'reglages', 'nouveautes' => 'cloche',
    'obligations' => 'obligations', 'demarche' => 'obligations', 'echeance' => 'obligations',
    'hygiene' => 'hygiene', 'hygiene-fiche' => 'hygiene', 'hygiene-appareil' => 'hygiene', 'hygiene-etiquettes' => 'imprimer', 'hygiene-probleme' => 'alerte'];
function titre_page(string $titre, string $sous = '', string $actions = '', string $retour = ''): string {
    $r = $retour !== '' ? '<a class="retour" href="' . h($retour) . '">' . icone('retour') . '<span>Retour</span></a>' : '';
    // Chaque titre de page porte l'icône de sa rubrique, la même que dans le menu (retour d'Olivier, 07/11/2026)
    $p = is_string($_GET['p'] ?? null) ? $_GET['p'] : 'accueil';
    if (isset(ICONES_PAGES[$p]) && !str_contains($titre, '<svg')) $titre = icone(ICONES_PAGES[$p]) . $titre;
    return '<div class="tete">' . $r . '<div class="tete-texte"><h1>' . $titre . '</h1>' . ($sous !== '' ? '<p class="sous">' . $sous . '</p>' : '') . '</div>'
         . ($actions !== '' ? '<div class="tete-actions">' . $actions . '</div>' : '') . '</div>';
}
function chip_statut(string $s, bool $court = true, bool $livraison = false): string {
    $l = $livraison && $s === 'remise' ? 'Livrée' : (($court ? STATUTS_COURTS : STATUTS)[$s] ?? $s);   // une livraison à un restaurant est « livrée », pas « remise »
    return '<span class="statut s-' . h(str_replace('_', '-', $s)) . '">' . h($l) . '</span>';
}
/** Particulier (B2C) ou Pro (B2B), au premier coup d'œil, sur chaque carte et chaque fiche (demande d'Olivier, 07/11/2026). */
function chip_type(bool $pro): string {
    return $pro ? '<span class="type-client t-pro">' . icone('pro') . 'Pro · B2B</span>' : '<span class="type-client t-part">' . icone('particulier') . 'Particulier · B2C</span>';
}
/** Le montant d'une commande tel qu'on le lit : TTC pour un particulier, HT pour un professionnel (comme sa facture). */
function montant_lu(array $k): string {
    $m = montant_commande($k);
    if (!$m) return '';
    return commande_est_pro($k) ? euros(commande_ht_ttc($k, tva_produits())['ht']) . ' HT' : euros($m) . ' TTC';
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
/** « TVA 5,5 % » à partir des dixièmes de pour cent de la base (55). */
function nombre_tva(int $t): string { return str_replace('.', ',', rtrim(rtrim(number_format($t / 10, 1, '.', ''), '0'), '.')) . ' %'; }
/** « 2026-11 » → « novembre 2026 ». */
function mois_texte(string $mois): string {
    return preg_match('/^(\d{4})-(\d{2})$/', $mois, $x) && isset(MOIS[(int)$x[2]]) && (int)$x[2] ? MOIS[(int)$x[2]] . ' ' . $x[1] : $mois;
}
/** « Même téléphone, mêmes prénom et nom » (en coréen sur l'écran de Youngmi). */
function doublon_raisons(array $r): string {
    $ko = ['même téléphone' => '같은 전화번호', 'mêmes prénom et nom' => '같은 이름', 'même entreprise' => '같은 회사'];
    $t = implode(', ', array_map(fn($x) => fr_ko($x, $ko[$x] ?? $x), $r));
    return coreen_actif() ? $t : mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
}
/** Sous le nom du client d'une commande : l'avertissement d'un doublon possible, avec le lien vers sa fiche. */
function avertissement_doublon(int $clientId): string {
    $cl = client($clientId);
    $dbl = $cl ? client_doublons($cl) : [];
    if (!$dbl) return '';
    $autres = implode(', ', array_map(fn($o) => h($o['numero']) . ' (' . h(doublon_raisons($o['raisons'])) . ')', $dbl));
    $lien = h(u('client', ['id' => $clientId])) . '#doublon';
    return '<p class="doublon-possible">' . icone('alerte') . '<span>' . fr_ko('Doublon possible : ' . $autres . '. <a href="' . $lien . '">Vérifier sur la fiche du client</a>',
                                                                         '중복 가능성: ' . $autres . '. <a href="' . $lien . '">고객 정보에서 확인하기</a>') . '</span></p>';
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
    // La pastille de la personne (FR-041) : « Nouvelle demande », « Le client a répondu », « Commande modifiée »… avec un liseré de couleur
    $pa = pastilles()['commandes'][(int)$k['id']] ?? null;
    if ($pa) $alertes = etiquette_pastille($pa) . $alertes;
    if (commande_en_retard($k)) $alertes .= '<span class="retard">Sans réponse depuis ' . heures_depuis($k['recue_le']) . ' h</span>';
    elseif (commande_dormante($k)) $alertes .= '<span class="dormant">Rien depuis ' . jours_depuis($k['dernier_echange']) . ' j</span>';
    $nom = $avecClient ? '<b translate="no">' . h(commande_client_nom($k)) . '</b>' : '<b>' . h(livraison_pro($k) ? 'Livraison' : (TYPES_COMMANDE[$k['type']] ?? 'Commande')) . '</b>';
    $montant = montant_lu($k);
    return '<a class="carte-cmd' . ($pa ? ' est-' . $pa['niveau'] : '') . '" href="' . h(u('commande', ['id' => $k['id']])) . '">'
         . '<span class="l1">' . ($avecClient ? chip_type(commande_est_pro($k)) : '') . $nom . chip_statut($k['statut'], true, livraison_pro($k)) . '</span>'
         . '<span class="l2">' . h(retrait_texte($k, true)) . ($montant !== '' ? ' · ' . h($montant) : '') . chip_paiement($k['paiement'], (int)$k['acompte']) . '</span>'
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
/** Une icône par sujet, toujours la même partout (demande d'Olivier du 07/11/2026 : « une commande toujours la même icône ») :
 *  commande = commandes, client = clients, gâteau/produit = produits, échanges = bulle (assistant), paiement = carte, retrait du jour = jour,
 *  agenda = mois, avis = avis, mail = email, statistiques = stats, paramètres = reglages, assistant IA = ia. Les titres des cadres la prennent ici. */
const ICONES_TITRES = [
    'Client' => 'clients', 'Coordonnées' => 'clients', 'Fiche et droits du client' => 'clients', 'Qui a accès' => 'clients',
    'Contenu' => 'produits', 'À préparer en tout' => 'produits', 'Ventes par gâteau' => 'produits',
    'Échanges' => 'assistant', 'Tous les échanges' => 'assistant', 'Notes et historique' => 'crayon',
    'Montant et paiement' => 'paiement', 'Facture et paiement' => 'paiement', 'Acomptes attendus' => 'paiement', 'Paiements pas encore' => 'paiement',
    'Relevés' => 'paiement', 'À recopier dans la facture' => 'paiement', 'Chiffre d' => 'paiement',
    'Commandes' => 'commandes', 'Demandes' => 'commandes', 'Propositions sans réponse' => 'commandes', 'Les livraisons' => 'commandes', 'Où en sont les demandes' => 'commandes',
    'Retraits' => 'jour', 'Les 7 derniers jours' => 'jour',
    'Avis Google' => 'avis', 'Emails préparés et avis Google' => 'avis', 'Réponses des clients' => 'email',
    'Assistant' => 'ia', 'Commandes et clients' => 'cloche', 'Dans l\'outil' => 'cloche',
    'Statistiques' => 'stats', 'Les visites du site' => 'stats', 'Visites par jour' => 'stats', 'Pages les plus vues' => 'stats', 'Téléphone ou ordinateur' => 'stats',
    'Doublon possible' => 'alerte',
    'Mon mot de passe' => 'reglages', 'Dernières connexions' => 'reglages', 'Langue de l' => 'reglages', 'Exporter' => 'reglages', 'Reprise des données' => 'reglages',
    'Conservation des données' => 'reglages', 'Miroir pour le Drive' => 'reglages', 'Contrôle technique' => 'reglages', 'QR code' => 'reglages',
];
function icone_titre(string $titre): string {
    if (str_contains($titre, "<svg")) return "";   // le titre a déjà son icône
    $t = trim(html_entity_decode(strip_tags($titre), ENT_QUOTES, 'UTF-8'));
    foreach (ICONES_TITRES as $debut => $ic) if (str_starts_with($t, $debut)) return icone($ic);
    return '';
}
function bloc(string $titre, string $contenu, string $classe = '', string $id = ''): string {
    return '<section class="bloc ' . h($classe) . '"' . ($id !== '' ? ' id="' . h($id) . '"' : '') . '>' . ($titre !== '' ? '<h2>' . icone_titre($titre) . $titre . '</h2>' : '') . $contenu . '</section>';
}
function tuile(string $label, string $valeur, string $detail = '', string $lien = ''): string {
    $dedans = '<span class="t-label">' . h($label) . '</span><span class="t-valeur">' . h($valeur) . '</span>' . ($detail !== '' ? '<span class="t-detail">' . h($detail) . '</span>' : '');
    return $lien !== '' ? '<a class="tuile" href="' . h($lien) . '">' . $dedans . '</a>' : '<div class="tuile">' . $dedans . '</div>';
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
        $cl = match ($type) { 'commentaire' => 'ev-commentaire', 'probleme' => 'ev-probleme', 'email_client' => 'ev-email', 'email_recu' => 'ev-email ev-recu', default => 'ev-systeme' };
        if ($type === 'email_recu' && isset($e['lu']) && !(int)$e['lu']) $cl .= ' ev-nouveau';
        $ref = $avecReference && !empty($e['reference']) ? ' · <a href="' . h(u('commande', ['id' => $e['commande_id']])) . '">' . h($e['reference']) . '</a>' : '';
        $txt = $type === 'statut' && isset(STATUTS[$e['nouveau']]) ? chip_statut($e['nouveau'], false) . (($p = mb_strpos($e['texte'], ' : ')) !== false ? ' ' . h(mb_substr($e['texte'], $p + 3)) : '') : nl2br(h($e['texte']));
        $par = '';
        if ($type === 'commentaire' && preg_match('/^par:(\w+)/', (string)$e['ancien'], $m) && isset(CANAUX_ECHANGE[$m[1]])) $par = '<span class="chip chip-par">' . h(CANAUX_ECHANGE[$m[1]]) . '</span> ';
        // Un email envoyé : son texte exact, replié. Un email reçu : la réponse du client, visible (la partie citée est retirée), et « Répondre »
        if ($type === 'email_client' && $e['nouveau'] !== '') $txt .= '<details class="replie"><summary>Voir le mail</summary><pre class="corps" translate="no" lang="fr">' . h($e['nouveau']) . '</pre></details>';
        if ($type === 'email_recu') {
            $txt = (isset($e['lu']) && !(int)$e['lu'] ? '<span class="chip chip-nouveau">Nouvelle réponse</span> ' : '') . $txt
                 . ($e['nouveau'] !== '' ? '<pre class="corps corps-recu" translate="no" lang="fr">' . h($e['nouveau']) . '</pre>' : '')
                 . (!$avecReference && !empty($e['commande_id']) ? '<div class="actions"><a class="btn secondaire petit-btn" href="#ecrire" data-ouvrir="ecrire">' . icone('email') . 'Préparer une réponse</a> <span class="petit">Rien ne part avant « Envoyer ».</span></div>' : '');
        }
        $s .= '<li class="' . $cl . '"><div class="ev-texte"' . ($type === 'commentaire' ? ' translate="no" lang="fr"' : '') . '>' . $par . $txt . '</div><div class="ev-meta">' . h($e['auteur']) . ' · ' . h(date_fr($e['le'], 'heure')) . $ref . '</div></li>';
    }
    return '<ol class="fil">' . $s . '</ol>';
}
/** La conversation d'une commande, façon messagerie (retour d'Olivier en test, 07/11/2026) : du plus ancien au plus récent ;
 *  le client à gauche, Madame Cho à droite, les échanges notés au milieu ; une étiquette (Proposition, WhatsApp…) et l'heure ;
 *  le texte tout de suite (sans l'objet, toujours le même, ni la signature). $evs : du plus récent au plus ancien, comme historique(). */
function fil_conversation(array $evs, bool $avecReference = false): string {
    if (!$evs) return '<p class="vide">Pas encore d\'échange avec le client.</p>';
    $sig = trim(strtok(signature(), "\n") ?: '');
    // Comme une messagerie : le plus récent en haut (retour d'Olivier, 07/11/2026)
    $s = ''; $trait = false; $vus = false;
    foreach ($evs as $e) {
        $type = $e['type'];
        $doute = false;
        $corps = trim((string)$e['nouveau']);
        $nouveau = $type === 'email_recu' && isset($e['lu']) && !(int)$e['lu'];
        if ($type === 'email_client') {
            $t = preg_match('/^type:(\w+)/', (string)$e['ancien'], $m) ? $m[1] : '';
            $etiq = $t === 'gmail' ? 'Mail envoyé depuis Gmail' : (isset(MAIL_TYPES[$t]) ? MAIL_TYPES[$t] : 'Mail');
            // Sans la signature : à partir de sa première ligne (« Youngmi Cho »)
            if ($sig !== '' && ($p = mb_strrpos($corps, "\n" . $sig)) !== false) $corps = rtrim(mb_substr($corps, 0, $p));
            if ($corps === '') $corps = (string)$e['texte'];
            $cl = 'msg-mc'; $qui = 'Madame Cho'; $ico = icone('email');
            // L'objet seulement s'il sort de l'ordinaire (« Votre plaque en chocolat (MC-…) »)
            if (preg_match('/« (.+) »$/u', (string)$e['texte'], $m) && !preg_match('/^(Re: )?Votre commande MC-\d{6}-[0-9A-F]{4} · Madame Cho$/u', $m[1])) $corps = 'Objet : ' . $m[1] . "\n\n" . $corps;
        } elseif ($type === 'email_recu') {
            $etiq = 'Mail du client'; $cl = 'msg-client'; $qui = (string)$e['auteur']; $ico = icone('email');
            if ($corps === '') $corps = (string)$e['texte'];
            if (str_contains((string)$e['ancien'], 'expediteur:autre')) { $etiq = 'Mail reçu'; $cl .= ' msg-autre'; $doute = true; }
        } else {
            $par = preg_match('/^par:(\w+)/', (string)$e['ancien'], $m) && isset(CANAUX_ECHANGE[$m[1]]) ? CANAUX_ECHANGE[$m[1]] : '';
            $etiq = $type === 'probleme' ? 'Problème' : ($par !== '' ? $par : 'Note');
            $cl = 'msg-note' . ($type === 'probleme' ? ' msg-probleme' : ''); $qui = (string)$e['auteur']; $ico = icone($type === 'probleme' ? 'alerte' : (isset($m[1]) && isset(ICONES_CANAUX[$m[1]]) ? ICONES_CANAUX[$m[1]] : 'crayon'));
            $corps = (string)$e['texte'];
        }
        $lignes = explode("\n", $corps);
        $texte = count($lignes) > 9
            ? '<p class="msg-corps" translate="no" lang="fr">' . nl2br(h(rtrim(implode("\n", array_slice($lignes, 0, 7))))) . '…</p>'
              . '<details class="mini"><summary>Voir tout le mail</summary><p class="msg-corps" translate="no" lang="fr">' . nl2br(h($corps)) . '</p></details>'
            : '<p class="msg-corps" translate="no" lang="fr">' . nl2br(h($corps)) . '</p>';
        // Les nouveaux en haut sous un trait « Nouveaux messages », puis un trait « Déjà vus » avant le reste
        if ($nouveau && !$trait) { $s .= '<li class="msg-trait"><span>Nouveaux messages</span></li>'; $trait = true; }
        if (!$nouveau && $trait && !$vus) { $s .= '<li class="msg-trait msg-trait-vus"><span>Déjà vus</span></li>'; $vus = true; }
        $s .= '<li class="msg ' . $cl . ($nouveau ? ' msg-nouveau' : '') . '"><div class="msg-tete">' . ($nouveau ? '<span class="chip chip-nouveau">Nouvelle réponse</span> ' : '')
            . (!empty($doute) ? '<span class="chip chip-doute">' . icone('alerte') . 'Expéditeur différent du client : à vérifier</span> ' : '')
            . '<span class="msg-etiq">' . $ico . h($etiq) . '</span><span class="msg-qui" translate="no">' . h($qui) . '</span></div>'
            . $texte . '<div class="msg-heure">' . h(date_fr($e['le'], 'heure'))
            . ($avecReference && !empty($e['reference']) ? ' · <a href="' . h(u('commande', ['id' => $e['commande_id']])) . '" translate="no">' . h($e['reference']) . '</a>' : '') . '</div></li>';
    }
    return '<ol class="conversation">' . $s . '</ol>';
}
/** Les canaux d'un échange noté, toujours avec la même icône (la conversation reprend la même). */
const ICONES_CANAUX = ['telephone' => 'tel', 'sms' => 'sms', 'whatsapp' => 'whatsapp', 'oral' => 'oral', 'instagram' => 'instagram', 'autre' => 'plus'];
/** « Par » : des boutons à toucher avec leur icône, plutôt qu'une liste (retour d'Olivier en test, 07/11/2026). */
function choix_par(): string {
    $s = '';
    foreach (CANAUX_ECHANGE as $v => $l) $s .= '<label class="puce-choix"><input type="radio" name="par" value="' . h($v) . '"><span>' . icone(ICONES_CANAUX[$v]) . h($l) . '</span></label>';
    return '<fieldset class="choix-par"><legend>Par</legend><div class="puces">' . $s . '</div></fieldset>';
}
/** « Noter un échange » : ce qui ne passe pas par les mails (appel, SMS, WhatsApp, de vive voix…). Micro et « Corriger mon français » sous la zone. */
function formulaire_commentaire(string $aide = ''): string {
    return formulaire('commentaire', zone('texte', 'Noter un échange', '', ['rows' => '2', 'maxlength' => '2000', 'required' => true, 'placeholder' => 'Elle a appelé : retrait à 16 h plutôt qu\'à 15 h…', 'lang' => 'fr', 'data-aide-saisie' => '1'],
        $aide !== '' ? $aide : 'Allergie : seulement l\'ingrédient à éviter, jamais d\'information de santé.')
        . choix_par()
        . bouton(icone('valider') . 'Ajouter la note', 'principal'), ['class' => 'form-commentaire']);
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
/* ---------- Les mails aux clients (demandes d'Olivier du 07/11/2026) ----------
 * Tout se fait dans la gestion, Gmail en secours : chaque mail part de la gestion au toucher de Youngmi sur « Envoyer »
 * (jamais par une IA, R-01), une copie arrive dans contact@, « Ouvrir dans ma messagerie » reste possible, et ce que Youngmi
 * envoie depuis Gmail revient dans la commande (script de contact@, page_reponses).
 * Un seul design (proposition A « La lettre ») : logo, filet chocolat, une étiquette qui dit le type (PROPOSITION, COMMANDE
 * CONFIRMÉE, MESSAGE, MERCI), le contenu propre au type, le même pied. HTML d'email : tableaux, styles en ligne, une seule image
 * (le logo), tout en vrai texte, liens vers madamecho.com (et la fiche Google pour l'avis) : rien qui ressemble à une publicité.
 * Un seul objet par commande (« Votre commande MC-… · Madame Cho ») et les en-têtes de réponse : chez le client, une seule conversation.
 * Aperçu dans la gestion ($apercu) : sa politique de sécurité refuse les styles en ligne, chaque style devient une classe « m-… »
 * dont les règles sont dans gestion.css (bloc « Aperçu du mail », généré par apercu_mail_css() : à refaire si un style change). */
const MAIL_TYPES = ['proposition' => 'Proposition', 'confirmation' => 'Commande confirmée', 'rappel' => 'Rappel', 'message' => 'Message', 'avis' => 'Merci'];
const MAIL_F = "font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;";
function sujet_commande(array $k): string { return 'Votre commande ' . $k['reference'] . ' · Madame Cho'; }
/** L'objet d'un message libre : celui de la commande par défaut ; la référence MC-… y reste toujours (pour retrouver la conversation). */
function sujet_libre(array $k, string $sujet): string {
    $sujet = une_ligne(trim($sujet));
    if ($sujet === '') return sujet_commande($k);
    if (!str_contains($sujet, $k['reference'])) $sujet .= ' (' . $k['reference'] . ')';
    return $sujet;
}
/** La phrase d'accueil proposée, que Youngmi peut changer. */
function accueil_mail(array $k, string $type): string {
    $b = salutation($k) . "\n\n";
    return match ($type) {
        'proposition' => $b . 'Merci pour votre demande. Voici ma proposition :',
        'confirmation' => $b . 'Votre commande ' . $k['reference'] . ' est confirmée. Je vous attends :',
        'rappel' => $b . 'Petit rappel : votre commande vous attend ' . rappel_quand($k) . '.',
        'avis' => $b . 'Merci encore pour votre commande. Si vous avez un moment, votre avis sur Google aiderait beaucoup Madame Cho à se faire connaître.',
        default => $b,
    };
}
function accueil_confirmation(array $k): string { return accueil_mail($k, 'confirmation'); }
/** Rappel de retrait (demande d'Olivier, 08/11/2026) : « demain », « aujourd'hui » ou « samedi 14 novembre ». */
function rappel_quand(array $k): string {
    $d = (string)($k['retrait_date'] ?? '');
    if ($d === aujourdhui()) return 'aujourd\'hui';
    if ($d === date('Y-m-d', strtotime('+1 day'))) return 'demain';
    return $d !== '' ? 'le ' . date_fr($d, 'jour') : 'bientôt';
}
/** La clé du rendez-vous annoncé (jour, heure, moment, retrait ou livraison) : un rappel ou une confirmation ne vaut que pour
 *  ce rendez-vous ; s'il change, le rappel redevient à faire (audit n° 4, A02). */
function rdv_cle(array $k): string {
    return substr(hash('sha256', implode('|', [(string)($k['retrait_date'] ?? ''), (string)($k['retrait_heure'] ?? ''), (string)($k['retrait_moment'] ?? ''), (string)($k['mode'] ?? '')])), 0, 12);
}
/** Ce qu'il reste à régler au retrait (audit n° 4, A01) : rien si la commande est déjà payée, le total moins l'acompte reçu, sinon le total. */
function reste_a_regler(array $k): int {
    $total = montant_commande($k);
    if (($k['paiement'] ?? '') === 'paye') return 0;
    if (($k['paiement'] ?? '') === 'acompte_recu' && (int)$k['acompte'] > 0) return max(0, $total - (int)$k['acompte']);
    return $total;
}
/** La ligne « Total » de la version texte (confirmation, rappel), calculée comme l'encadré. */
function total_email(array $k): string {
    $total = montant_commande($k); $acompte = (int)$k['acompte'];
    if (($k['paiement'] ?? '') === 'paye') return 'Total : ' . euros($total) . ' TTC, déjà réglé. Merci !';
    if (($k['paiement'] ?? '') === 'acompte_recu' && $acompte) return 'Total : ' . euros($total) . ' TTC (acompte de ' . euros($acompte) . ' reçu, reste ' . euros(reste_a_regler($k)) . ' à régler au retrait)';
    return 'Total : ' . euros($total) . ' TTC, à régler au retrait';
}
/** L'empreinte de ce que montre l'aperçu (destinataire, rendez-vous, montants, contenu, « demain ») : vérifiée au toucher sur « Envoyer » ;
 *  si la commande a changé entre-temps, rien ne part et Youngmi revoit l'aperçu (audit n° 4, A05). */
function empreinte_mail(array $k, string $type): string {
    return substr(hash('sha256', implode('|', [$type, (string)$k['client_email'], rdv_cle($k), montant_commande($k), (string)$k['paiement'], (int)$k['acompte'],
        (string)$k['lignes'], (string)$k['perso'], $type === 'rappel' ? rappel_quand($k) : ''])), 0, 16);
}
/** La fin du rappel : « À demain, » ou « À tout à l'heure, ». */
function rappel_fin(array $k): string {
    return match (rappel_quand($k)) { 'demain' => 'À demain,', 'aujourd\'hui' => 'À tout à l\'heure,', default => 'À bientôt,' };
}
/** La version courte du rappel, à copier dans un SMS ou WhatsApp (client sans email, ou qui préfère). */
function rappel_sms(array $k): string {
    $d = (string)($k['retrait_date'] ?? '');
    $quand = rappel_quand($k);
    $jour = $d !== '' ? JOURS[(int)date('w', strtotime($d))] . ' ' . date('d/m', strtotime($d)) : '';
    $h = $k['retrait_heure'] !== '' ? ' à ' . (int)substr($k['retrait_heure'], 0, 2) . ' h' . (substr($k['retrait_heure'], 3, 2) !== '00' ? ' ' . substr($k['retrait_heure'], 3, 2) : '')
       : (isset(MOMENTS[$k['retrait_moment']]) ? ', ' . mb_strtolower(MOMENTS[$k['retrait_moment']]) . ' (heure à convenir)' : '');
    $prenom = explode("\n", trim(signature()))[0] ?? '';
    $prenom = trim(explode(' ', trim($prenom))[0] ?? '') ?: 'Youngmi';
    return salutation($k) . ' petit rappel de Madame Cho : votre commande ' . $k['reference'] . ' vous attend '
         . ($quand === 'demain' || $quand === 'aujourd\'hui' ? $quand . ' ' . $jour : $quand) . $h . ', 4 avenue du Château à Meudon. '
         . ($quand === 'demain' ? 'À demain !' : ($quand === 'aujourd\'hui' ? 'À tout à l\'heure !' : 'À bientôt !')) . ' ' . $prenom;
}
function mail_style(bool $apercu): \Closure {
    return function (string $css) use ($apercu): string {
        if (!$apercu) return ' style="' . $css . '"';
        $GLOBALS['mc_styles_mail'][$css] = true;
        return ' class="m-' . substr(md5($css), 0, 8) . '"';
    };
}
function mail_paragraphe(\Closure $S): \Closure {
    return fn(string $t, string $style = '') => '<p' . $S(MAIL_F . 'font-size:15px;line-height:23px;color:#2B2623;margin:0;' . $style) . '>' . $t . '</p>';
}
function texte_mail_html(string $t, callable $p, string $style): string {
    $html = '';
    foreach (preg_split("/\n\s*\n/", trim($t)) as $para) if (trim($para) !== '') $html .= $p(nl2br(h(trim($para)), false), $style);
    return $html;
}
function mail_signature_nom(): string { return h(trim(explode("\n", trim(signature()))[0] ?? '') ?: 'Youngmi Cho'); }
/** Quand : « Vendredi 20 novembre, 15 h 30 », sinon le moment (« le matin, heure à convenir »). */
function mail_quand(array $k): string {
    if (!$k['retrait_date']) return 'Jour à convenir ensemble';
    $q = ucfirst(date_fr($k['retrait_date'], 'jour'));
    if ($k['retrait_heure'] !== '') return $q . ', ' . str_replace(':', ' h ', $k['retrait_heure']);
    return isset(MOMENTS[$k['retrait_moment']]) ? $q . ', ' . mb_strtolower(MOMENTS[$k['retrait_moment']]) . ' (heure à convenir)' : $q;
}
/** L'encadré ivoire : quand, où, les gâteaux, le total (proposition et confirmation). */
function mail_recap(array $k, string $type, \Closure $S): string {
    $F = MAIL_F; $TX = '#2B2623'; $DX = '#736A64';
    $td = $S($F . 'font-size:14px;line-height:22px;color:' . $TX . ';padding:2px 0');
    $l = '';
    foreach ($k['lignes_tab'] as $x) {
        $l .= '<tr><td' . $td . '>' . h($x['q'] . ' × ' . $x['nom'] . ($x['libelle'] !== '' ? ', ' . $x['libelle'] : '')) . '</td><td align="right"' . $td . '>' . h(euros($x['prix'] * $x['q'])) . '</td></tr>';
        if (($x['plaque'] ?? '') !== '') $l .= '<tr><td colspan="2"' . $S($F . 'font-size:13px;line-height:20px;color:' . $DX . ';padding:0 0 2px') . '>Plaque en chocolat : « ' . h($x['plaque']) . ' »</td></tr>';
    }
    if ($k['perso'] !== '') $l .= '<tr><td colspan="2"' . $td . '>' . nl2br(h($k['perso']), false) . '</td></tr>';
    $total = montant_commande($k);
    $acompte = (int)$k['acompte'];
    $bord = $S($F . 'font-size:14px;color:' . $TX . ';border-top:1px solid #DDD3C6;padding:8px 0 0');
    if ($type === 'proposition') {
        $l .= '<tr><td' . $bord . '><b>Total TTC</b></td><td align="right"' . $bord . '><b>' . h($total ? euros($total) : 'à préciser') . '</b></td></tr>';
        if ($acompte > 0) $l .= '<tr><td' . $td . '>Acompte pour réserver, déduit au retrait</td><td align="right"' . $td . '>' . h(euros($acompte)) . '</td></tr>';
    } else {
        // Audit n° 4, A01 : une commande déjà payée ne demande plus rien
        $paye = $k['paiement'] === 'paye';
        $avecAcompte = !$paye && $k['paiement'] === 'acompte_recu' && $acompte;
        $l .= '<tr><td' . $bord . '><b>Total TTC</b>, ' . ($paye ? 'déjà réglé' : ($avecAcompte ? 'acompte de ' . h(euros($acompte)) . ' reçu' : 'à régler au retrait')) . '</td><td align="right"' . $bord . '><b>' . h(euros($total)) . '</b></td></tr>';
        if ($avecAcompte) $l .= '<tr><td' . $td . '>Reste à régler au retrait</td><td align="right"' . $td . '>' . h(euros(reste_a_regler($k))) . '</td></tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"' . $S('background:#F3EDE4;border-radius:6px;margin-top:6px') . '><tr><td' . $S('padding:18px 20px') . '>'
        . '<p' . $S($F . 'font-size:17px;line-height:24px;color:#3B1D0E;font-weight:bold;margin:0') . '>' . h(mail_quand($k)) . '</p>'
        . '<p' . $S($F . 'font-size:14px;line-height:21px;color:' . $TX . ';margin:2px 0 14px') . '>4 avenue du Château, 92190 Meudon</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $l . '</table></td></tr></table>';
}
/** Le cadre commun : logo, filet chocolat, étiquette du type, contenu, pied (qui écrit, pourquoi, ajouter contact@ aux contacts). */
function mail_cadre(string $titre, string $etiquette, string $contenu, \Closure $S, bool $apercu): string {
    $DX = '#736A64'; $site = 'https://' . GESTION_DOMAINE;
    $petit = MAIL_F . 'font-size:12px;line-height:18px;color:' . $DX . ';margin:0';
    $pied = '<p' . $S($petit) . '>Madame Cho · pâtisserie sur commande · 4 avenue du Château, 92190 Meudon<br>'
          . '06 61 31 17 79 · <a href="' . $site . '"' . $S('color:' . $DX . ';text-decoration:underline') . '>madamecho.com</a></p>'
          . '<p' . $S($petit . ';margin-top:10px') . '>Vous recevez ce message parce que vous avez commandé chez Madame Cho. '
          . 'Pour que nos messages arrivent bien, <a href="' . $site . '/madame-cho.vcf"' . $S('color:' . $DX . ';text-decoration:underline') . '>ajoutez Madame Cho à vos contacts</a> (contact@madamecho.com).</p>';
    $corps = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"' . $S('background:#ffffff') . '><tr><td align="center"' . $S('padding:32px 16px') . '>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"' . $S('max-width:560px') . '>'
        . '<tr><td' . $S('padding:0 0 20px;border-bottom:2px solid #3B1D0E') . '><img src="' . $site . '/assets/logo-email.png" width="150" height="42" alt="Madame Cho"' . $S('display:block;border:0;width:150px;height:auto;margin:0 auto') . '></td></tr>'
        . '<tr><td' . $S('padding:22px 0 0') . '><p' . $S(MAIL_F . 'font-size:11px;line-height:16px;letter-spacing:2px;color:' . $DX . ';margin:0 0 18px') . '>' . h(mb_strtoupper($etiquette)) . '</p>' . $contenu . '</td></tr>'
        . '<tr><td' . $S('padding:28px 0 0') . '><div' . $S('border-top:1px solid #E9E5E1;padding-top:16px') . '>' . $pied . '</div></td></tr>'
        . '</table></td></tr></table>';
    if ($apercu) return '<div class="apercu-mail" translate="no" lang="fr">' . $corps . '</div>';
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($titre) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#ffffff">' . $corps . '</body></html>';
}
/**
 * Un mail au client, en trois morceaux : objet, texte, HTML (ou l'aperçu). $type : proposition, confirmation, message, avis.
 * $o : accueil (phrase d'accueil ou, pour un message, le message), mot (petit mot), sujet (message), lien (avis), apercu.
 */
function mail_commande(array $k, string $type, array $o = []): array {
    $apercu = !empty($o['apercu']);
    $S = mail_style($apercu);
    $p = mail_paragraphe($S);
    $accueil = trim((string)($o['accueil'] ?? '')) !== '' ? (string)$o['accueil'] : accueil_mail($k, $type);
    $mot = trim((string)($o['mot'] ?? ''));
    $etiquette = MAIL_TYPES[$type] . ($type === 'avis' ? '' : ' · ' . $k['reference']);
    $sujet = $type === 'message' ? sujet_libre($k, (string)($o['sujet'] ?? '')) : sujet_commande($k);
    $lien = fn(string $href, string $t) => '<a href="' . h($href) . '"' . $S('color:#6E2A10;font-weight:bold') . '>' . $t . '</a>';
    $html = texte_mail_html($accueil, $p, 'margin-bottom:14px');
    $texte = trim($accueil) . "\n\n";
    $fin = 'À bientôt,';
    switch ($type) {
        case 'proposition':
            $html .= mail_recap($k, $type, $S) . ($mot !== '' ? texte_mail_html($mot, $p, 'margin-top:20px') : '')
                   . $p('<b>Pour confirmer, il suffit de répondre à ce mail.</b> Annulation sans frais jusqu\'à 24 heures avant le retrait.', 'margin-top:20px');
            $total = montant_commande($k);
            $texte .= lignes_email($k) . "\n\nTotal : " . ($total ? euros($total) . ' TTC' : 'à préciser') . "\n" . retrait_email($k) . "\n"
                    . ((int)$k['acompte'] > 0 ? 'Pour réserver, un acompte de ' . euros((int)$k['acompte']) . " est demandé ; il est déduit du prix au retrait.\n" : '')
                    . ($mot !== '' ? "\n" . $mot . "\n" : '') . "\nPour confirmer, il suffit de répondre à ce mail. Annulation sans frais jusqu'à 24 heures avant le retrait.\n\n";
            $fin = 'Belle journée,';
            break;
        case 'confirmation':
            $agenda = rdv_client_possible($k) ? $p('Si vous le souhaitez, ajoutez le retrait à votre agenda : ' . $lien(lien_rdv_client($k, 'google'), 'Google Agenda') . ' ou ' . $lien(lien_rdv_client($k, 'ics'), 'iPhone, Outlook et autres') . '.', 'margin-top:20px') : '';
            $regle = ($k['paiement'] === 'paye' ? '' : 'Paiement par carte, virement ou espèces. ') . 'Annulation sans frais jusqu\'à 24 heures avant le retrait.';
            $html .= mail_recap($k, $type, $S) . ($mot !== '' ? texte_mail_html($mot, $p, 'margin-top:20px') : '') . $agenda
                   . $p(h($regle), 'margin-top:14px');
            $texte .= lignes_email($k) . "\n\n" . total_email($k) . "\n"
                    . retrait_email($k) . "\n" . ($mot !== '' ? "\n" . $mot . "\n" : '') . rdv_client_texte($k)
                    . "\n" . $regle . "\n\n";
            break;
        case 'rappel':
            $empeche = ($k['paiement'] === 'paye' ? '' : 'Paiement par carte, virement ou espèces. ') . 'Un empêchement ? Répondez simplement à ce mail ou appelez le 06 61 31 17 79.';
            $html .= mail_recap($k, $type, $S) . ($mot !== '' ? texte_mail_html($mot, $p, 'margin-top:20px') : '')
                   . $p(h($empeche), 'margin-top:20px');
            $texte .= lignes_email($k) . "\n\n" . retrait_email($k) . "\n" . total_email($k) . "\n"
                    . ($mot !== '' ? "\n" . $mot . "\n" : '') . "\n" . $empeche . "\n\n";
            $fin = rappel_fin($k);
            break;
        case 'avis':
            $l = (string)($o['lien'] ?? parametre('lien_avis', ''));
            $html .= ($l !== '' ? $p($lien($l, 'Laisser un avis sur Google'), 'margin-top:4px') : '') . ($mot !== '' ? texte_mail_html($mot, $p, 'margin-top:20px') : '');
            $texte .= ($l !== '' ? 'Laisser un avis sur Google : ' . $l . "\n\n" : '') . ($mot !== '' ? $mot . "\n\n" : '');
            $fin = 'Merci et à bientôt,';
            break;
        default:   // message
            $texte = trim($accueil) . "\n\n";
    }
    $html .= $p(h($fin) . '<br>' . mail_signature_nom(), 'margin-top:22px');
    $texte .= $fin . "\n" . signature();
    return ['sujet' => $sujet, 'corps' => $texte, 'html' => mail_cadre($sujet, $etiquette, $html, $S, $apercu), 'type' => $type];
}
/** Les Message-ID déjà échangés sur une commande (mails envoyés et reçus), du plus ancien au plus récent. */
function mail_refs(int $commande): array {
    $r = [];
    foreach (lignes("SELECT ancien FROM evenements WHERE commande_id = ? AND type IN ('email_client', 'email_recu') AND ancien LIKE '%mid:<%' ORDER BY id", [$commande]) as $e)
        if (preg_match('/mid:(<[^<>\s]+>)/', $e['ancien'], $m)) $r[] = $m[1];
    return $r;
}
/** Envoie un mail de la commande (au toucher de Youngmi, R-01) et le range dans ses Échanges. Renvoie true s'il est parti. */
function envoyer_mail_commande(array $k, string $type, array $o = []): bool {
    $a = (string)$k['client_email'];
    if (!email_valide($a)) return false;
    $m = mail_commande($k, $type, $o);
    // Audit n° 3, C04 : un double toucher ou un réseau qui hésite ne renvoie pas le même mail (même type, même texte, moins de 2 minutes)
    if (val("SELECT 1 FROM evenements WHERE commande_id = ? AND type = 'email_client' AND ancien LIKE ? AND nouveau = ? AND le > ?",
            [(int)$k['id'], 'type:' . $type . ' %', $m['corps'], date('Y-m-d H:i:s', time() - 120)])) { $GLOBALS['mc_doublon'] = true; return true; }
    // Audit n° 4, A04 : un verrou de 2 minutes par commande et par type, pris avant l'envoi, pour deux onglets ou deux appareils à la fois
    //  (pas pour « Écrire au client » : deux messages différents peuvent se suivre)
    $verrou = $type === 'message' ? '' : 'envoi_' . (int)$k['id'] . '_' . $type;
    if ($verrou !== '') {
        q("DELETE FROM parametres WHERE cle LIKE 'envoi\\_%' ESCAPE '\\' AND CAST(valeur AS INTEGER) < ?", [time() - 120]);   // les verrous passés, tous
        $st = db()->prepare("INSERT OR IGNORE INTO parametres (cle, valeur) VALUES (?, ?)");
        $st->execute([$verrou, (string)time()]);
        if ($st->rowCount() === 0) { $GLOBALS['mc_doublon'] = true; return true; }
    }
    $mid = envoyer_email_client($a, $m['sujet'], $m['corps'], $m['html'], mail_refs((int)$k['id']));
    if ($mid === '') { if ($verrou !== '') q("DELETE FROM parametres WHERE cle = ?", [$verrou]); return false; }
    // Le rendez-vous annoncé est gardé avec la confirmation et le rappel : s'il change, le rappel redevient à faire (A02)
    evenement('email_client', 'Email envoyé à ' . $a . ' (' . mb_strtolower(MAIL_TYPES[$type]) . ') : « ' . $m['sujet'] . ' »', ['commande' => (int)$k['id'], 'client' => (int)$k['client_id']],
              'type:' . $type . ' mid:' . $mid . (in_array($type, ['confirmation', 'rappel'], true) ? ' rdv:' . rdv_cle($k) : ''), $m['corps']);
    $now = maintenant();
    q("UPDATE commandes SET dernier_echange = ?, maj_le = ? WHERE id = ?", [$now, $now, (int)$k['id']]);
    client_echange((int)$k['client_id'], $now);
    return true;
}
/** Le bandeau après un envoi : « parti », ou « déjà parti, pas renvoyé » si c'était un double toucher (audit n° 3, C04). */
function flash_envoi(string $ok): void {
    flash('ok', !empty($GLOBALS['mc_doublon']) ? 'Ce mail était déjà parti il y a moins de 2 minutes : il n\'a pas été envoyé une deuxième fois.' : $ok);
}
/** Le dernier envoi d'un type de mail sur la commande (ou null). */
function mail_deja(int $commande, string $type): ?array {
    $l = ligne("SELECT le FROM evenements WHERE commande_id = ? AND type = 'email_client' AND (ancien LIKE ? OR (? = 'confirmation' AND texte LIKE '%est confirmée%')) ORDER BY id DESC LIMIT 1",
               [$commande, 'type:' . $type . ' %', $type]);
    return $l ?: null;
}
/* Les noms d'avant, gardés pour le reste de la gestion */
function email_proposition(array $k): array { return mail_commande($k, 'proposition'); }
function email_confirmation(array $k, ?string $accueil = null, string $mot = ''): array { return mail_commande($k, 'confirmation', ['accueil' => $accueil, 'mot' => $mot]); }
function email_avis(array $k, string $lien): array { return mail_commande($k, 'avis', ['lien' => $lien]); }
/** Les règles CSS de l'aperçu (à recopier dans gestion.css quand un style du mail change) : des mails d'essai qui passent par tous les cas. */
function apercu_mail_css(): string {
    $GLOBALS['mc_styles_mail'] = [];
    $k = ['reference' => 'MC-000000-0000', 'retrait_date' => '2026-01-01', 'retrait_heure' => '10:00', 'retrait_moment' => '', 'mode' => 'retrait', 'statut' => 'confirmee',
          'id' => 1, 'acompte' => 100, 'paiement' => 'acompte_recu', 'perso' => 'x', 'besoin' => '', 'prix_final' => 1000, 'client_prenom' => 'A', 'client_nom' => 'B',
          'lignes_tab' => [['q' => 1, 'nom' => 'x', 'libelle' => 'y', 'prix' => 100, 'plaque' => 'z']]];
    foreach (array_keys(MAIL_TYPES) as $t) mail_commande($k, $t, ['accueil' => "a\n\nb", 'mot' => 'c', 'lien' => 'https://g.page/r/x', 'apercu' => true]);
    $css = '';
    foreach (array_keys($GLOBALS['mc_styles_mail']) as $r) $css .= '.apercu-mail .m-' . substr(md5($r), 0, 8) . '{' . $r . "}\n";
    return $css;
}
function mailto(string $a, array $e): string {
    return 'mailto:' . rawurlencode($a) . '?subject=' . rawurlencode($e['sujet']) . '&body=' . rawurlencode($e['corps']);
}
/** Bloc « email prêt » : texte à relire, bouton pour ouvrir la messagerie, bouton copier. */
function bloc_email(string $id, string $titre, array $e, string $a): string {
    $ouvrir = $a !== '' ? lien_bouton(mailto($a, $e), icone('email') . 'Ouvrir dans ma messagerie', 'principal') : '<p class="aide">Pas d\'email pour ce client : copiez le texte dans un message.</p>';
    return '<details class="email-pret" id="' . h($id) . '"><summary>' . icone('email') . h($titre) . '</summary>'
         . '<p class="aide">À relire, puis à envoyer vous-même depuis contact@madamecho.com.</p>'
         . '<p class="sujet"><b>Objet :</b> <span translate="no" lang="fr">' . h($e['sujet']) . '</span></p><pre class="corps" id="' . h($id) . '-texte" translate="no" lang="fr">' . h($e['corps']) . '</pre>'
         . '<div class="actions">' . $ouvrir . '<button type="button" class="btn" data-copier="' . h($id) . '-texte">' . icone('copier') . 'Copier le texte</button></div></details>';
}

/* ---------- Agenda ---------- */
function creneau(array $k): ?array {
    if (!$k['retrait_date']) return null;
    if (preg_match('/^\d{2}:\d{2}$/', (string)$k['retrait_heure'])) {
        $debut = $k['retrait_heure'];
        $fin = date('H:i', strtotime($k['retrait_date'] . ' ' . $debut) + 1800);
    } else [$debut, $fin] = MOMENTS_PLAGES[$k['retrait_moment']] ?? ['09:00', '10:00'];
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

/* ---------- Le retrait dans l'agenda du client (demande d'Olivier du 07/11/2026) ----------
 * L'email de confirmation propose au client, s'il le souhaite, d'ajouter son retrait à son agenda : Google Agenda,
 * ou un fichier agenda (.ics) pour iPhone, Outlook et les autres. Les deux liens passent par ?p=rdv&c=… (page publique) :
 * une adresse courte, impossible à deviner (empreinte HMAC avec le sel du serveur), qui donne toujours le jour et l'heure
 * à jour, et plus rien si la commande est annulée. Jamais le lien de la gestion, le nom du client ni les prix dans l'événement.
 * Seulement pour un retrait à Meudon avec une heure précise (une livraison ou un moment « à convenir » n'a pas de lien). */
function rdv_client_possible(array $k): bool {
    return ($k['mode'] ?? 'retrait') !== 'livraison' && (string)$k['retrait_date'] !== '' && preg_match('/^\d{2}:\d{2}$/', (string)$k['retrait_heure']) === 1
        && !in_array($k['statut'], ['annulee', 'sans_suite', 'remise'], true);
}
function rdv_jeton(array $k): string { return substr(hash_hmac('sha256', 'rdv|' . (int)$k['id'] . '|' . $k['reference'], sel()), 0, 24); }
function lien_rdv_client(array $k, string $vers): string {
    // Adresse courte madamecho.com/rdv/… (réécrite vers ?p=rdv par le .htaccess de la racine) ; en essai local, l'adresse longue
    if (GESTION_ESSAI) return gestion_adresse() . u('rdv', ['c' => (int)$k['id'] . '-' . rdv_jeton($k)] + ($vers === 'google' ? ['vers' => 'google'] : []));
    return 'https://' . GESTION_DOMAINE . '/rdv/' . (int)$k['id'] . '-' . rdv_jeton($k) . ($vers === 'google' ? '/google' : '');
}
function rdv_client_texte(array $k): string {
    if (!rdv_client_possible($k)) return '';
    return "\nPour l'ajouter à votre agenda, si vous le souhaitez :\n- Google Agenda : " . lien_rdv_client($k, 'google')
         . "\n- iPhone, Outlook ou un autre agenda : " . lien_rdv_client($k, 'ics') . "\n";
}
function rdv_client_titre(array $k): string { return 'Retrait chez Madame Cho · ' . $k['reference']; }
function rdv_client_details(array $k): string {
    $t = ['Commande ' . $k['reference']];
    foreach ($k['lignes_tab'] as $l) $t[] = '- ' . $l['q'] . ' × ' . $l['nom'] . ($l['libelle'] !== '' ? ', ' . $l['libelle'] : '');
    if ($k['perso'] !== '') $t[] = '- Commande personnalisée';
    $t[] = '';
    $t[] = 'Une question, un retard : 06 61 31 17 79 (appel, SMS ou WhatsApp) ou contact@madamecho.com.';
    $t[] = 'madamecho.com';
    return implode("\n", $t);
}

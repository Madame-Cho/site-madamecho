<?php
/*
 * Madame Cho : espace de gestion. Statistiques, menu « Plus », réglages (compte, comptes, emails, conservation RGPD,
 * miroir pour le Drive, reprise des données, sauvegarde, contrôle technique), exports CSV et miroir.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

/* ---------- Statistiques ---------- */
/** Petit graphique en barres (SVG, une seule série) : $points = [[étiquette, valeur, infobulle]]. */
function barres(array $points, string $titre): string {
    $max = max(1, ...array_map(fn($p) => (int)$p[1], $points));
    $n = count($points);
    $l = 100 / max(1, $n);
    $s = '';
    foreach ($points as $i => [$etiq, $v, $info]) {
        $hauteur = $v > 0 ? max(1.5, $v / $max * 100) : 0;
        $x = $i * $l + $l * 0.18;
        $s .= '<g><title>' . h($info) . '</title><rect class="fond-barre" x="' . round($i * $l, 3) . '" y="0" width="' . round($l, 3) . '" height="100"/>'
            . ($hauteur ? '<rect class="barre" x="' . round($x, 3) . '" y="' . round(100 - $hauteur, 3) . '" width="' . round($l * 0.64, 3) . '" height="' . round($hauteur, 3) . '" rx="0.8"/>' : '') . '</g>';
    }
    $etiquettes = '';
    $dernier = $n - 1;
    foreach ($points as $i => [$etiq, $v]) {
        $valeur = $v > 0 && ($v === $max || $i === $dernier) ? '<b>' . h($v >= 100000 ? number_format($v / 100000, 1, ',', '') . ' k€' : euros($v)) . '</b>' : '';
        $etiquettes .= '<span>' . h($etiq) . $valeur . '</span>';
    }
    return '<figure class="graphique"><svg viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="' . h($titre) . '">' . $s . '<line class="axe" x1="0" y1="100" x2="100" y2="100"/></svg>'
         . '<div class="etiquettes n' . $n . '">' . $etiquettes . '</div></figure>';
}

function page_statistiques(): void {
    $s = statistiques();
    $t = $s['total'];
    $ca = array_sum(array_map(fn($m) => (int)$m['ca'], $s['mois']));
    $transfo = ($t['remises'] + $t['perdues']) ? $t['remises'] / ($t['remises'] + $t['perdues']) : null;
    $rep = $s['reponse'];
    $avis = ligne("SELECT SUM(CASE WHEN avis_demande_le IS NOT NULL THEN 1 ELSE 0 END) AS demandes, SUM(CASE WHEN avis_recu_le IS NOT NULL THEN 1 ELSE 0 END) AS recus FROM clients WHERE statut <> 'efface'") ?? [];
    $c = titre_page('Statistiques', 'Depuis le début de la gestion · montants TTC des commandes remises', '', u('plus'));
    $c .= '<div class="tuiles">'
        . tuile('Commandes remises', (string)$t['remises'], $t['n'] . ' demande(s) en tout')
        . tuile('Chiffre d\'affaires', euros($ca), 'TTC, 24 derniers mois')
        . tuile('Panier moyen', $t['remises'] ? euros(intdiv($ca, max(1, array_sum(array_map(fn($m) => (int)$m['n'], $s['mois']))))) : '—')
        . tuile('Demandes abouties', $transfo === null ? '—' : number_format($transfo * 100, 0) . ' %', 'remises ÷ (remises + sans suite + annulées)')
        . tuile('Réponse aux demandes du site', $rep['mediane'] === null ? '—' : ($rep['mediane'] < 1 ? 'moins d\'1 h' : number_format($rep['mediane'], 0) . ' h'), $rep['n'] ? $rep['sous_24h'] . ' sur ' . $rep['n'] . ' en moins de 24 h' : 'médiane')
        . tuile('Clients', (string)(int)$s['clients']['n'], (int)$s['clients']['pros'] . ' professionnel(s)')
        . tuile('Clients fidèles', (string)$s['fideles'], 'au moins 2 commandes remises')
        . tuile('Avis Google', (string)(int)($avis['recus'] ?? 0), (int)($avis['demandes'] ?? 0) . ' demandé(s)')
        . '</div>';
    // Chiffre d'affaires des 12 derniers mois (mois sans vente compris)
    $parMois = [];
    foreach ($s['mois'] as $m) $parMois[$m['mois']] = $m;
    $pts = [];
    for ($i = 11; $i >= 0; $i--) {
        $cle = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
        $m = $parMois[$cle] ?? ['n' => 0, 'ca' => 0];
        $mo = (int)substr($cle, 5, 2);
        $pts[] = [MOIS_COURTS[$mo], (int)$m['ca'], ucfirst(MOIS[$mo]) . ' ' . substr($cle, 0, 4) . ' : ' . euros((int)$m['ca']) . ', ' . (int)$m['n'] . ' commande(s)'];
    }
    $rows = '';
    foreach ($s['mois'] as $m) {
        $mo = (int)substr($m['mois'], 5, 2);
        $rows .= '<tr><td>' . h(ucfirst(MOIS[$mo]) . ' ' . substr($m['mois'], 0, 4)) . '</td><td class="nombre">' . (int)$m['n'] . '</td><td class="nombre">' . h(euros((int)$m['ca'])) . '</td><td class="nombre">' . h(euros((int)$m['panier'])) . '</td></tr>';
    }
    $c .= bloc('Chiffre d\'affaires par mois', ($ca ? barres($pts, 'Chiffre d\'affaires des 12 derniers mois') : '')
        . ($rows !== '' ? '<details class="replie"' . ($ca ? '' : ' open') . '><summary>Le tableau</summary><table class="tableau"><thead><tr><th>Mois</th><th class="nombre">Commandes</th><th class="nombre">TTC</th><th class="nombre">Panier</th></tr></thead><tbody>' . $rows . '</tbody></table></details>' : '<p class="vide">Pas encore de commande remise.</p>'));
    // Canaux
    $rows = '';
    foreach ($s['canaux'] as $k) $rows .= '<tr><td>' . h(CANAUX[$k['canal']] ?? $k['canal']) . '</td><td class="nombre">' . (int)$k['n'] . '</td><td class="nombre">' . (int)$k['remises'] . '</td><td class="nombre">' . h(pourcent((int)$k['remises'], (int)$k['n'])) . '</td><td class="nombre">' . h(euros((int)$k['ca'])) . '</td></tr>';
    if ($rows !== '') $c .= bloc('D\'où viennent les commandes', '<div class="defile"><table class="tableau"><thead><tr><th>Canal</th><th class="nombre">Demandes</th><th class="nombre">Remises</th><th class="nombre">Abouties</th><th class="nombre">TTC</th></tr></thead><tbody>' . $rows . '</tbody></table></div>');
    // Produits
    $rows = '';
    foreach ($s['produits'] as $p) $rows .= '<tr><td>' . h($p['nom']) . '</td><td class="nombre">' . (int)$p['q'] . '</td><td class="nombre">' . h(euros((int)$p['ca'])) . '</td></tr>';
    if ($rows !== '') $c .= bloc('Gâteaux vendus', '<table class="tableau"><thead><tr><th>Gâteau</th><th class="nombre">Quantité</th><th class="nombre">TTC (prix de la carte)</th></tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<p class="aide">Les gâteaux personnalisés et les commandes professionnelles sans ligne de la carte ne sont comptés que dans le chiffre d\'affaires.</p>');
    // Étapes
    $rows = '';
    usort($s['statuts'], fn($a, $b) => array_search($a['statut'], array_keys(STATUTS), true) <=> array_search($b['statut'], array_keys(STATUTS), true));
    foreach ($s['statuts'] as $k) $rows .= '<tr><td>' . chip_statut($k['statut'], false) . '</td><td class="nombre">' . (int)$k['n'] . '</td></tr>';
    if ($rows !== '') $c .= bloc('Où en sont les demandes', '<table class="tableau"><tbody>' . $rows . '</tbody></table>');
    $c .= bloc('Exporter', '<p class="aide">Pour un tableur : sans email ni téléphone. Le fichier contient des noms de clients : à ranger dans le Drive de Madame Cho, pas ailleurs.</p><div class="actions">'
        . lien_bouton(u('export', ['jeu' => 'commandes']), 'Commandes (CSV)', 'discret') . lien_bouton(u('export', ['jeu' => 'clients']), 'Clients (CSV)', 'discret') . '</div>');
    mise_en_page('Statistiques', $c, 'statistiques');
}

/* ---------- Plus ---------- */
function page_plus(): void {
    $u = utilisateur_courant();
    $l = [[u('statistiques'), 'stats', 'Statistiques', 'Ventes, canaux, délais de réponse, avis'],
          [u('production'), 'imprimer', 'Fiche de production', 'Ce qu\'il faut préparer, jour par jour'],
          [u('ingredients'), 'produits', 'Ingrédients', 'Prix d\'achat et allergènes des recettes'],
          [u('reglages'), 'reglages', 'Réglages', 'Mot de passe, signature des emails, lien d\'avis, données'],
          [u('aide'), 'avis', 'Comment ça marche', 'Le parcours d\'une commande, en bref']];
    $s = '';
    foreach ($l as [$href, $ic, $titre, $sous]) $s .= '<a class="ligne-menu" href="' . h($href) . '">' . icone($ic) . '<span><b>' . h($titre) . '</b><span class="petit">' . h($sous) . '</span></span></a>';
    $s .= '<a class="ligne-menu" href="' . h(u('deconnexion', ['j' => csrf()])) . '">' . icone('sortir') . '<span><b>Se déconnecter</b><span class="petit">' . h($u['email']) . '</span></span></a>';
    $c = titre_page('Plus', '') . '<nav class="menu" aria-label="Plus">' . $s . '</nav><p class="aide centre">Gestion Madame Cho · version du ' . h(date_fr(GESTION_VERSION, 'longue')) . '</p>';
    mise_en_page('Plus', $c, 'plus');
}

function page_aide(): void {
    $etapes = [
        ['À répondre', 'Une demande arrive par le site (elle s\'ajoute toute seule) ou vous la saisissez avec « Nouvelle commande » (Instagram, téléphone, marché…). Le site promet une réponse sous 24 heures.'],
        ['Proposition', 'Dans la commande, « Préparer la proposition » donne un email prêt : vous le relisez, vous l\'envoyez depuis contact@, puis vous appuyez sur « J\'ai envoyé la proposition ».'],
        ['Confirmée', 'Quand le client accepte par écrit, « Confirmer la commande » avec le jour et l\'heure du retrait. Ajoutez le retrait à votre agenda en un geste.'],
        ['Remise', 'Le jour du retrait, « Commande remise au client », avec le paiement. Un client content ? Demandez-lui un avis Google.'],
        ['Sans suite ou annulée', 'Pas de réponse du client, ou une annulation : la demande sort des listes. Les demandes sans suite sont effacées au bout de 12 mois.'],
    ];
    $s = '';
    foreach ($etapes as $i => [$t, $d]) $s .= '<li><b>' . ($i + 1) . '. ' . h($t) . '</b><p>' . h($d) . '</p></li>';
    $c = titre_page('Comment ça marche', 'Le parcours d\'une commande', '', u('plus'))
       . bloc('Les étapes', '<ol class="etapes">' . $s . '</ol>')
       . bloc('Bon à savoir', '<ul class="puces"><li>Un <b>problème</b> (retard, réclamation, paiement) se signale dans la commande : il reste visible partout jusqu\'à ce qu\'il soit résolu.</li>'
            . '<li>Les <b>commentaires</b> se mettent sur la commande ou sur la fiche du client. Jamais d\'information de santé : pour une allergie, seulement l\'ingrédient à éviter.</li>'
            . '<li>Aucun email ne part tout seul : la gestion prépare les textes, c\'est vous qui les envoyez.</li>'
            . '<li>La <b>fiche de production</b> (menu Plus) liste ce qu\'il faut préparer, sans les coordonnées des clients.</li>'
            . '<li>Mathieu fait le point des commandes avec vous le mardi et le vendredi.</li></ul>');
    mise_en_page('Aide', $c, 'plus');
}

/* ---------- Exports ---------- */
function page_export(): void {
    $jeu = is_string($_GET['jeu'] ?? null) ? $_GET['jeu'] : '';
    if ($jeu === 'sauvegarde') {
        if (!est_admin()) rediriger('reglages');
        $d = ['madamecho_gestion' => GESTION_VERSION, 'exportee_le' => maintenant()];
        foreach (['clients', 'commandes', 'evenements', 'produits', 'ingredients', 'recettes'] as $t) $d[$t] = q("SELECT * FROM $t")->fetchAll();
        $d['parametres'] = q("SELECT * FROM parametres WHERE cle IN ('signature', 'lien_avis', 'schema')")->fetchAll();
        evenement('export', 'Sauvegarde complète téléchargée');
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="sauvegarde-gestion-madamecho-' . date('Y-m-d') . '.json"');
        header('Cache-Control: no-store, private');
        echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    [$l, $e, $nom] = match ($jeu) {
        'clients' => [miroir_clients(), MIROIR_CLIENTS_ENTETES, 'clients'],
        default => [miroir_commandes(), MIROIR_COMMANDES_ENTETES, 'commandes'],
    };
    evenement('export', 'Export CSV des ' . $nom);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="madamecho-' . $nom . '-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store, private');
    echo csv($l, $e);
    exit;
}

/** Miroir pour le Google Sheet du Drive (dossier 10) : appelé par un script Google en POST, avec la clé créée dans Réglages. */
function page_miroir(): void {
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    $empreinte = (string)parametre('miroir_empreinte', '');
    $cle = is_string($_POST['cle'] ?? null) ? $_POST['cle'] : '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $empreinte === '' || connexion_bloquee('miroir')) { http_response_code(404); exit; }
    if (!hash_equals($empreinte, hash('sha256', $cle))) { connexion_noter('miroir', false, 'miroir'); http_response_code(403); exit; }
    $jeu = is_string($_POST['jeu'] ?? null) ? $_POST['jeu'] : 'commandes';
    [$l, $e] = $jeu === 'clients' ? [miroir_clients(), MIROIR_CLIENTS_ENTETES] : [miroir_commandes(), MIROIR_COMMANDES_ENTETES];
    parametre_ecrire('miroir_dernier', maintenant());
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['jeu' => $jeu === 'clients' ? 'clients' : 'commandes', 'le' => maintenant(), 'entetes' => $e, 'lignes' => $l], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- Réglages ---------- */
function page_reglages(): void {
    $u = utilisateur_courant();
    $admin = est_admin();
    $info = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $a = texte('_action', 40);
        $ancre = '';
        switch ($a) {
            case 'mdp':
                $ancien = is_string($_POST['ancien'] ?? null) ? $_POST['ancien'] : '';
                $m1 = is_string($_POST['mdp1'] ?? null) ? $_POST['mdp1'] : '';
                $m2 = is_string($_POST['mdp2'] ?? null) ? $_POST['mdp2'] : '';
                $ancre = 'compte';
                if (!password_verify($ancien, $u['mdp'])) { flash('erreur', 'Le mot de passe actuel n\'est pas le bon.'); break; }
                if ($m1 !== $m2) { flash('erreur', 'Les deux nouveaux mots de passe ne sont pas identiques.'); break; }
                if ($e = mdp_acceptable($m1)) { flash('erreur', $e); break; }
                mdp_changer((int)$u['id'], $m1);
                session_pour($u, !empty($_COOKIE['mc_souvenir']));
                evenement('compte', 'Mot de passe changé');
                flash('ok', 'Mot de passe changé. Les autres appareils devront se reconnecter.');
                break;
            case 'emails':
                $lien = une_ligne(texte('lien_avis', 300));
                $ancre = 'avis';
                if ($lien !== '' && !preg_match('#^https://(g\.page|search\.google\.com|www\.google\.[a-z.]+|maps\.app\.goo\.gl|goo\.gl)/#', $lien)) { flash('erreur', 'Le lien d\'avis doit être un lien Google (https://g.page/r/…/review).'); break; }
                parametre_ecrire('signature', texte('signature', 500));
                parametre_ecrire('lien_avis', $lien);
                evenement('reglage', 'Signature des emails ou lien d\'avis modifié');
                flash('ok', 'Enregistré.');
                break;
            case 'inviter':
                if (!$admin) break;
                $ancre = 'comptes';
                $email = mb_strtolower(une_ligne(texte('email', 120)));
                $nom = une_ligne(texte('nom', 60));
                if (!email_valide($email) || $nom === '') { flash('erreur', 'Indiquez un email valide et un nom.'); break; }
                $ex = ligne("SELECT * FROM utilisateurs WHERE email = ?", [$email]);
                if ($ex && $ex['mdp'] !== '') { flash('erreur', 'Ce compte existe déjà.'); break; }
                if (!$ex) { q("INSERT INTO utilisateurs (email, nom, role, cree_le) VALUES (?, ?, ?, ?)", [$email, $nom, choix('role', ['membre' => 1, 'admin' => 1], 'membre'), maintenant()]); $ex = ligne("SELECT * FROM utilisateurs WHERE email = ?", [$email]); }
                q("UPDATE jetons SET utilise_le = ? WHERE utilisateur_id = ? AND type = 'mdp' AND utilise_le IS NULL", [maintenant(), $ex['id']]);
                $ok = email_lien_mot_de_passe($ex, jeton_creer((int)$ex['id'], 'mdp', 3 * 86400), true);
                evenement('compte', 'Invitation envoyée à ' . $email);
                flash($ok ? 'ok' : 'erreur', $ok ? 'Invitation envoyée à ' . $email . ' (lien valable 3 jours).' : 'L\'email d\'invitation n\'a pas pu partir.');
                break;
            case 'compte_actif':
                if (!$admin) break;
                $ancre = 'comptes';
                $cid = entier('compte', 1);
                if ($cid && $cid !== (int)$u['id']) {
                    q("UPDATE utilisateurs SET actif = 1 - actif WHERE id = ?", [$cid]);
                    q("DELETE FROM jetons WHERE utilisateur_id = ? AND type = 'session'", [$cid]);
                    evenement('compte', 'Compte ' . val("SELECT email FROM utilisateurs WHERE id = ?", [$cid]) . (val("SELECT actif FROM utilisateurs WHERE id = ?", [$cid]) ? ' réactivé' : ' désactivé'));
                }
                break;
            case 'demandes_site':
                if (!$admin) break;
                $ancre = 'demandes-site';
                $on = parametre('demandes_site') !== '1';
                parametre_ecrire('demandes_site', $on ? '1' : '0');
                evenement('reglage', $on ? 'Enregistrement des demandes du site activé' : 'Enregistrement des demandes du site arrêté');
                flash('ok', $on ? 'Les nouvelles demandes du site s\'ajouteront toutes seules ici (l\'email à contact@ continue d\'arriver).' : 'Les demandes du site ne s\'ajoutent plus ici ; elles arrivent toujours par email.');
                break;
            case 'conservation':
                if (!$admin) break;
                $ancre = 'conservation';
                $cr = conservation_executer(auteur());
                flash('ok', 'Fait : ' . $cr['demandes'] . ' demande(s) anonymisée(s), ' . $cr['clients'] . ' client(s) effacé(s), ' . $cr['journal'] . ' ligne(s) de journal supprimée(s).');
                break;
            case 'miroir':
                if (!$admin) break;
                $ancre = 'miroir';
                if (!empty($_POST['arreter'])) { parametre_ecrire('miroir_empreinte', ''); evenement('reglage', 'Miroir pour le Drive arrêté'); flash('ok', 'Miroir arrêté : l\'ancienne clé ne marche plus.'); break; }
                $cle = bin2hex(random_bytes(24));
                parametre_ecrire('miroir_empreinte', hash('sha256', $cle));
                evenement('reglage', 'Nouvelle clé du miroir pour le Drive');
                session_ouvrir();
                $_SESSION['cle_miroir'] = $cle;   // montrée une seule fois
                break;
            case 'importer':
                if (!$admin) break;
                $ancre = 'reprise';
                $f = $_FILES['fichier'] ?? null;
                if (!is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || ($f['size'] ?? 0) > 2_000_000) { flash('erreur', 'Choisissez le fichier JSON de reprise (2 Mo au plus).'); break; }
                $data = json_decode((string)file_get_contents($f['tmp_name']), true);
                if (!is_array($data)) { flash('erreur', 'Ce fichier n\'est pas un JSON lisible.'); break; }
                try { $cr = importer($data); }
                catch (\Throwable $e) { error_log('madamecho gestion import : ' . $e->getMessage()); flash('erreur', 'La reprise a échoué : rien n\'a été enregistré.' . (GESTION_ESSAI ? ' ' . $e->getMessage() : '')); break; }
                flash($cr['erreurs'] ? 'erreur' : 'ok', 'Reprise : ' . $cr['clients_crees'] . ' client(s) créé(s), ' . $cr['clients_repris'] . ' déjà connu(s), ' . $cr['commandes_creees'] . ' commande(s) créée(s), ' . $cr['commandes_sautees'] . ' déjà présente(s).'
                      . ($cr['erreurs'] ? "\nÀ revoir : " . implode(' ; ', array_slice($cr['erreurs'], 0, 10)) : ''));
                break;
        }
        rediriger('reglages', [], $ancre);
    }
    $c = titre_page('Réglages', h($u['nom']) . ' · ' . h($u['email']), '', u('plus'));
    // Mon compte
    $c .= bloc('Mon mot de passe', formulaire('mdp', '<input type="text" name="username" value="' . h($u['email']) . '" autocomplete="username" hidden>'
        . champ('ancien', 'Mot de passe actuel', '', ['type' => 'password', 'autocomplete' => 'current-password', 'required' => true])
        . '<div class="ligne-champs">' . champ('mdp1', 'Nouveau mot de passe', '', ['type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'minlength' => (string)GESTION_MDP_MIN])
        . champ('mdp2', 'Le même, une seconde fois', '', ['type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'minlength' => (string)GESTION_MDP_MIN]) . '</div>'
        . '<p class="aide">Au moins ' . GESTION_MDP_MIN . ' caractères. Gardez-le dans Bitwarden. Oublié ? « Mot de passe oublié » sur la page de connexion envoie un lien à ' . h($u['email']) . '.</p>'
        . bouton('Changer le mot de passe', 'secondaire')), '', 'compte');
    // Emails préparés et avis
    $c .= bloc('Emails préparés et avis Google', formulaire('emails',
          zone('signature', 'Signature des emails préparés', signature(), ['rows' => '3', 'maxlength' => '500'])
        . champ('lien_avis', 'Lien pour laisser un avis Google', (string)parametre('lien_avis', ''), ['type' => 'url', 'placeholder' => 'https://g.page/r/…/review'],
                'Dans la fiche Google de Madame Cho : « Demander des avis », puis copier le lien. Tant qu\'il est vide, la gestion ne propose pas de demander d\'avis.')
        . bouton('Enregistrer', 'secondaire')), '', 'avis');
    // Comptes
    if ($admin) {
        $rows = '';
        foreach (lignes("SELECT * FROM utilisateurs ORDER BY id") as $x) {
            $etat = !$x['actif'] ? 'désactivé' : ($x['mdp'] === '' ? 'invitation en attente' : ($x['derniere_connexion'] ? 'vu ' . date_fr($x['derniere_connexion'], 'relative') : 'jamais connecté'));
            $rows .= '<tr><td><b>' . h($x['nom']) . '</b><br><span class="petit">' . h($x['email']) . '</span></td><td>' . h($x['role'] === 'admin' ? 'Administratrice' : 'Membre') . '<br><span class="petit">' . h($etat) . '</span></td><td>'
                   . ((int)$x['id'] !== (int)$u['id'] ? formulaire('compte_actif', '<input type="hidden" name="compte" value="' . (int)$x['id'] . '">' . bouton($x['actif'] ? 'Désactiver' : 'Réactiver', 'discret')) : '') . '</td></tr>';
        }
        $c .= bloc('Qui a accès', '<table class="tableau"><tbody>' . $rows . '</tbody></table>'
            . '<details class="replie"><summary>Donner un accès à quelqu\'un</summary><p class="aide">Seulement pour une personne qui travaille pour Madame Cho. Elle reçoit un lien pour choisir son mot de passe. Une IA n\'a jamais de compte ici.</p>'
            . formulaire('inviter', '<div class="ligne-champs">' . champ('email', 'Email', '', ['type' => 'email', 'required' => true]) . champ('nom', 'Nom', '', ['required' => true]) . '</div>'
                . liste_choix('role', 'Rôle', ['membre' => 'Membre (commandes, clients, produits)', 'admin' => 'Administratrice (aussi les accès et les données)'], 'membre') . bouton('Envoyer l\'invitation', 'secondaire')) . '</details>', '', 'comptes');
    }
    // Demandes du site
    if ($admin) {
        $on = parametre('demandes_site') === '1';
        $c .= bloc('Demandes du site', '<p>' . ($on ? '<span class="ok">Activé</span> : chaque demande envoyée par le formulaire du site s\'ajoute ici toute seule, avec son client, une fois l\'email parti à contact@.'
                : '<span class="dormant">Pas encore activé</span> : les demandes du site arrivent seulement par email ; le lien « Dans la gestion » de l\'email permet de les créer ici.') . '</p>'
            . formulaire('demandes_site', bouton($on ? 'Arrêter l\'enregistrement' : 'Activer l\'enregistrement des demandes du site', $on ? 'discret' : 'secondaire')), '', 'demandes-site');
    }
    // Conservation et RGPD
    if ($admin) {
        $ap = conservation_apercu();
        $der = json_decode((string)parametre('derniere_purge', ''), true);
        $nuit = parametre('derniere_nuit');
        $refs = conservation_references_emails();
        $t = '<p>Chaque nuit, la gestion applique les durées annoncées dans la politique de confidentialité : demandes sans suite effacées 12 mois après le dernier échange, clients sans commande ni échange effacés au bout de 3 ans, journal des connexions gardé 6 mois.</p>'
           . definition(['Dernier passage de nuit' => h($nuit ? date_fr($nuit, 'heure') : 'pas encore'), 'Dernier effacement' => h(is_array($der) ? date_fr($der['le'], 'heure') . ' : ' . $der['demandes'] . ' demande(s), ' . $der['clients'] . ' client(s)' : 'aucun'),
                         'À effacer maintenant' => h(count($ap['demandes']) . ' demande(s), ' . count(array_unique(array_merge(array_column($ap['clients'], 'id'), array_column($ap['orphelins'], 'id')))) . ' client(s), ' . $ap['journal'] . ' ligne(s) de journal')]);
        if ($nuit && (time() - strtotime($nuit)) > 2 * 86400) $t .= '<p class="erreur">La tâche de nuit ne passe plus depuis le ' . h(date_fr($nuit)) . ' : prévenez Olivier (tâche planifiée OVH).</p>';
        if ($refs) $t .= '<p class="aide">Demandes du site sans suite depuis plus de 11 mois : supprimez aussi leurs emails dans contact@ (P-02, premier lundi du mois) :</p><p class="refs">' . h(implode(' · ', $refs)) . '</p>';
        $t .= formulaire('conservation', bouton('Lancer l\'effacement maintenant', 'discret'), ['data-confirmer' => 'Effacer maintenant ce qui a dépassé sa durée de conservation ?']);
        $c .= bloc('Conservation des données (RGPD)', $t, '', 'conservation');
        // Miroir
        $cle = '';
        session_ouvrir();
        if (!empty($_SESSION['cle_miroir'])) { $cle = (string)$_SESSION['cle_miroir']; unset($_SESSION['cle_miroir']); }
        $actif = (string)parametre('miroir_empreinte', '') !== '';
        $m = '<p>Le miroir recopie chaque nuit une version réduite des registres (sans email, téléphone ni commentaires) dans un tableur du Drive (dossier 10), pour Mathieu et les récapitulatifs.</p>'
           . definition(['État' => h($actif ? 'actif' : 'arrêté'), 'Dernière copie' => h(($d = parametre('miroir_dernier')) ? date_fr($d, 'heure') : 'jamais'), 'Adresse' => '<code>' . h(gestion_adresse() . '?p=miroir') . '</code>']);
        if ($cle !== '') $m .= '<div class="cle-miroir"><p><b>Clé à coller dans le script Google</b> (elle ne sera plus jamais affichée) :</p><pre id="cle-miroir">' . h($cle) . '</pre><button type="button" class="btn" data-copier="cle-miroir">' . icone('copier') . 'Copier</button></div>';
        $m .= formulaire('miroir', bouton($actif ? 'Créer une nouvelle clé (l\'ancienne cesse de marcher)' : 'Créer la clé du miroir', 'discret')) . ($actif ? formulaire('miroir', '<input type="hidden" name="arreter" value="1">' . bouton('Arrêter le miroir', 'discret')) : '');
        $c .= bloc('Miroir pour le Drive', $m, '', 'miroir');
        // Reprise et sauvegarde
        $c .= bloc('Reprise des données et sauvegarde', '<p class="aide">Reprise : le fichier JSON préparé à partir de Qonto et des demandes déjà reçues (jamais dans le dépôt du site). Sans risque à relancer : ce qui est déjà là est sauté.</p>'
            . formulaire('importer', '<label class="champ"><span class="etiq">Fichier de reprise (.json)</span><input type="file" name="fichier" accept="application/json,.json" required></label>' . bouton('Importer', 'secondaire'), ['enctype' => 'multipart/form-data'])
            . '<p class="aide">Sauvegarde : tout le contenu de la gestion dans un fichier, à ranger dans le Drive de Madame Cho chaque mois (P-24). Elle contient des données de clients.</p>'
            . '<p>' . lien_bouton(u('export', ['jeu' => 'sauvegarde']), 'Télécharger une sauvegarde', 'discret') . '</p>', '', 'reprise');
    }
    // Contrôle technique
    $sqlite = (string)val('SELECT sqlite_version()');
    $taille = is_file(gestion_fichier_base()) ? filesize(gestion_fichier_base()) : 0;
    $dkim = is_file(gestion_site() . '/ma-demande/dkim.php');
    $cat = catalogue();
    $site = ligne("SELECT MAX(recue_le) AS d, COUNT(*) AS n FROM commandes WHERE canal = 'site'") ?? [];
    $ok = fn(bool $b, string $oui, string $non) => '<span class="' . ($b ? 'ok' : 'retard') . '">' . h($b ? $oui : $non) . '</span>';
    $c .= bloc('Contrôle technique', definition([
        'PHP' => $ok(PHP_VERSION_ID >= 80000, PHP_VERSION, PHP_VERSION . ' (8.0 au moins)'),
        'Base de données' => $ok($sqlite !== '', 'SQLite ' . $sqlite . ' · ' . number_format($taille / 1024, 0, ',', ' ') . ' Ko', 'indisponible'),
        'Données rangées' => $ok(gestion_hors_site(), 'hors du dossier du site', 'dans un dossier fermé du site (repli)'),
        'Connexion chiffrée' => $ok(gestion_https() || GESTION_ESSAI, gestion_https() ? 'https' : 'essai local', 'pas de https'),
        'Emails signés (DKIM)' => $ok($dkim, 'signature du site présente', 'dkim.php absent'),
        'Carte du site' => $ok(count($cat['produits']) > 0, count($cat['produits']) . ' produits · version ' . ($cat['version'] ?? '?'), 'carte absente'),
        'Demandes du site' => h(((int)($site['n'] ?? 0)) . ' enregistrée(s)' . (!empty($site['d']) ? ', dernière le ' . date_fr($site['d'], 'heure') : '')),
    ]));
    // Journal des connexions
    if ($admin) {
        $rows = '';
        foreach (lignes("SELECT * FROM connexions ORDER BY id DESC LIMIT 25") as $x) $rows .= '<tr><td>' . h(date_fr($x['le'], 'heure')) . '</td><td>' . h($x['email']) . '</td><td>' . ($x['ok'] ? 'oui' : '<span class="retard">non</span>') . ($x['detail'] !== '' ? ' · ' . h($x['detail']) : '') . '</td></tr>';
        $c .= bloc('Dernières connexions', $rows !== '' ? '<div class="defile"><table class="tableau"><thead><tr><th>Quand</th><th>Email</th><th>Réussie</th></tr></thead><tbody>' . $rows . '</tbody></table></div><p class="aide">Gardées 6 mois. Une connexion que vous ne reconnaissez pas : changez le mot de passe et prévenez Olivier (P-17).</p>' : '<p class="vide">Aucune.</p>');
    }
    mise_en_page('Réglages', $c, 'reglages');
}

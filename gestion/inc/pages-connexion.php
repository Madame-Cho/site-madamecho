<?php
/*
 * Madame Cho : espace de gestion. Connexion, première connexion et mot de passe oublié, déconnexion.
 * Compte de Youngmi : contact@madamecho.com ; elle choisit son mot de passe par le lien reçu à cette adresse.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

function retour_valide(string $r): string { return preg_match('/^\?p=[a-z\-]+(&[a-zA-Z0-9_\-]+=[a-zA-Z0-9_%.\-]*)*$/', $r) ? $r : u('accueil'); }

function page_connexion(): void {
    if (utilisateur_courant()) rediriger('accueil');
    $erreur = ''; $email = '';
    $retour = retour_valide(is_string($_GET['retour'] ?? null) ? $_GET['retour'] : '');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $email = mb_strtolower(une_ligne(texte('email', 120)));
        $mdp = is_string($_POST['mdp'] ?? null) ? $_POST['mdp'] : '';
        if (connexion_bloquee($email)) $erreur = 'Trop d\'essais en peu de temps : attendez un quart d\'heure, ou choisissez un nouveau mot de passe.';
        elseif (connexion_tenter($email, $mdp, !empty($_POST['souvenir']))) { header('Location: ' . $retour); exit; }
        else $erreur = 'Email ou mot de passe incorrect.';
    }
    $aucun = (int)val("SELECT COUNT(*) FROM utilisateurs") === 0;
    $c = '<div class="carte-connexion">'
       . '<h1>Gestion de Madame Cho</h1><p class="sous">Clients, commandes et produits. Accès réservé.</p>'
       . ($erreur !== '' ? '<p class="erreur" role="alert">' . h($erreur) . '</p>' : '')
       . ($aucun ? '<p class="info">Première connexion : choisissez d\'abord votre mot de passe avec le lien ci-dessous, envoyé à contact@madamecho.com.</p>' : '')
       . formulaire('connexion',
            champ('email', 'Email', $email, ['type' => 'email', 'autocomplete' => 'username', 'required' => true, 'placeholder' => 'contact@madamecho.com', 'inputmode' => 'email'])
          . champ('mdp', 'Mot de passe', '', ['type' => 'password', 'autocomplete' => 'current-password', 'required' => true])
          . case_a_cocher('souvenir', 'Rester connectée sur cet appareil (30 jours)', true)
          . bouton('Se connecter', 'principal large'), [], u('connexion', $retour !== u('accueil') ? ['retour' => $retour] : []))
       . '<p class="lien-bas"><a href="' . h(u('oubli')) . '">' . ($aucun ? 'Première connexion : choisir mon mot de passe' : 'Première connexion ou mot de passe oublié ?') . '</a></p>'
       . '</div>';
    mise_en_page('Connexion', $c, '', 'page-connexion');
}

function page_oubli(): void {
    $envoye = false; $email = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $email = mb_strtolower(une_ligne(texte('email', 120)));
        $recentes = (int)val("SELECT COUNT(*) FROM connexions WHERE detail = 'oubli' AND le > ? AND (email = ? OR ip = ?)", [date('Y-m-d H:i:s', time() - 3600), $email, ip_empreinte()]);
        connexion_noter($email, true, 'oubli');
        if (email_valide($email) && $recentes < 3) {
            $u = ligne("SELECT * FROM utilisateurs WHERE email = ? AND actif = 1", [$email]);
            if (!$u && $email === GESTION_COMPTE_INITIAL && (int)val("SELECT COUNT(*) FROM utilisateurs") === 0) {
                // Tout premier compte : celui de Youngmi, sur l'adresse de Madame Cho, administratrice
                q("INSERT INTO utilisateurs (email, nom, role, cree_le) VALUES (?, 'Youngmi Cho', 'admin', ?)", [$email, maintenant()]);
                $u = ligne("SELECT * FROM utilisateurs WHERE email = ?", [$email]);
                evenement('compte', 'Compte de ' . $email . ' créé (première connexion)', [], '', '', 'système');
            }
            if ($u) {
                q("UPDATE jetons SET utilise_le = ? WHERE utilisateur_id = ? AND type = 'mdp' AND utilise_le IS NULL", [maintenant(), $u['id']]);   // un seul lien valable à la fois
                $j = jeton_creer((int)$u['id'], 'mdp', 3600);
                email_lien_mot_de_passe($u, $j, false);
            }
        }
        $envoye = true;
    }
    $c = '<div class="carte-connexion"><h1>Choisir mon mot de passe</h1>';
    if ($envoye) {
        $c .= '<p class="info" role="status">Si cette adresse a un accès, un email vient de partir avec un lien valable une heure. Regardez aussi dans les indésirables.</p>';
        if (GESTION_ESSAI && !empty($_SESSION['essai_email'])) {
            $e = $_SESSION['essai_email']; unset($_SESSION['essai_email']);
            $c .= '<div class="essai-email"><p><b>Essai : l\'email qui serait parti à ' . h($e['a']) . '</b></p><pre>' . h($e['corps']) . '</pre></div>';
        }
        $c .= '<p class="lien-bas"><a href="' . h(u('connexion')) . '">Retour à la connexion</a></p>';
    } else {
        $c .= '<p class="sous">Indiquez l\'adresse de votre accès : vous recevrez un lien pour choisir votre mot de passe. Pour Madame Cho, c\'est contact@madamecho.com.</p>'
            . formulaire('oubli', champ('email', 'Email', $email !== '' ? $email : '', ['type' => 'email', 'autocomplete' => 'username', 'required' => true, 'placeholder' => 'contact@madamecho.com', 'inputmode' => 'email'])
              . bouton('Recevoir le lien', 'principal large'))
            . '<p class="lien-bas"><a href="' . h(u('connexion')) . '">Retour à la connexion</a></p>';
    }
    $c .= '</div>';
    mise_en_page('Mot de passe', $c, '', 'page-connexion');
}

function page_mot_de_passe(): void {
    $jeton = is_string($_GET['j'] ?? null) ? $_GET['j'] : '';
    $j = jeton_verifier($jeton, 'mdp');
    $erreur = '';
    if (!$j) {
        mise_en_page('Lien expiré', '<div class="carte-connexion"><h1>Ce lien ne marche plus</h1><p class="sous">Il a expiré ou a déjà servi. Demandez-en un nouveau : il arrive en une minute.</p>'
            . '<p>' . lien_bouton(u('oubli'), 'Recevoir un nouveau lien', 'principal') . '</p></div>', '', 'page-connexion');
    }
    $u = ligne("SELECT * FROM utilisateurs WHERE id = ? AND actif = 1", [$j['utilisateur_id']]);
    if (!$u) rediriger('connexion');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $m1 = is_string($_POST['mdp1'] ?? null) ? $_POST['mdp1'] : '';
        $m2 = is_string($_POST['mdp2'] ?? null) ? $_POST['mdp2'] : '';
        if ($m1 !== $m2) $erreur = 'Les deux mots de passe ne sont pas identiques.';
        elseif ($e = mdp_acceptable($m1)) $erreur = $e;
        else {
            mdp_changer((int)$u['id'], $m1);
            q("UPDATE jetons SET utilise_le = ? WHERE id = ?", [maintenant(), $j['id']]);
            connexion_noter($u['email'], true, 'nouveau mot de passe');
            evenement('compte', 'Mot de passe choisi par ' . $u['email'], [], '', '', $u['nom']);
            session_pour($u, !empty($_POST['souvenir']));
            flash('ok', 'Votre mot de passe est enregistré. Bienvenue dans la gestion de Madame Cho.');
            rediriger('accueil');
        }
    }
    $c = '<div class="carte-connexion"><h1>Choisir mon mot de passe</h1><p class="sous">Pour ' . h($u['email']) . '. Au moins ' . GESTION_MDP_MIN . ' caractères : une petite phrase est plus facile à retenir et plus sûre.</p>'
       . ($erreur !== '' ? '<p class="erreur" role="alert">' . h($erreur) . '</p>' : '')
       . formulaire('mdp', '<input type="text" name="username" value="' . h($u['email']) . '" autocomplete="username" hidden>'
            . champ('mdp1', 'Nouveau mot de passe', '', ['type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'minlength' => (string)GESTION_MDP_MIN])
            . champ('mdp2', 'Le même, une seconde fois', '', ['type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'minlength' => (string)GESTION_MDP_MIN])
            . case_a_cocher('souvenir', 'Rester connectée sur cet appareil (30 jours)', true)
            . bouton('Enregistrer et entrer', 'principal large'))
       . '</div>';
    mise_en_page('Mot de passe', $c, '', 'page-connexion');
}

function page_deconnexion(): void {
    $j = is_string($_GET['j'] ?? null) ? $_GET['j'] : '';
    if (hash_equals(csrf(), $j)) session_fermer();
    rediriger('connexion');
}

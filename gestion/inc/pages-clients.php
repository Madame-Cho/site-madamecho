<?php
/*
 * Madame Cho : espace de gestion. Clients : liste, fiche (commandes, commentaires, avis Google, droits RGPD), saisie.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

function carte_client(array $c): string {
    $pro = $c['type'] === 'professionnel' ? '<span class="statut s-pro">Pro</span>' : '';
    $etat = $c['statut'] === 'efface' ? '<span class="statut s-efface">Effacé</span>' : ($c['statut'] === 'inactif' ? '<span class="statut s-inactif">Inactif</span>' : '');
    $avis = $c['avis_recu_le'] ? '<span class="avis-ok">' . icone('avis') . 'Avis reçu</span>' : ($c['avis_demande_le'] ? '<span class="avis-attente">' . icone('avis') . 'Avis demandé</span>' : '');
    $chiffres = (int)$c['remises'] ? (int)$c['remises'] . ' commande' . ((int)$c['remises'] > 1 ? 's' : '') . ' · ' . euros((int)$c['total']) . ($c['derniere'] ? ' · dernière le ' . date_fr($c['derniere']) : '') : 'Pas encore de commande remise';
    $enCours = (int)$c['en_cours'] ? '<span class="compte">' . (int)$c['en_cours'] . ' en cours</span>' : '';
    return '<a class="carte-cmd carte-client" href="' . h(u('client', ['id' => $c['id']])) . '">'
         . '<span class="l1"><b translate="no">' . h(client_nom($c)) . '</b>' . $pro . $etat . $enCours . '</span>'
         . '<span class="l2">' . h($chiffres) . '</span>'
         . '<span class="l4">' . h($c['numero']) . ($c['ville'] !== '' ? ' · <span translate="no">' . h($c['ville']) . '</span>' : '') . ' · ' . h(CANAUX[$c['canal']] ?? $c['canal']) . ' · dernier échange ' . h(date_fr($c['dernier_echange'], 'relative')) . '</span>'
         . ($avis !== '' ? '<span class="l5">' . $avis . '</span>' : '')
         . '</a>';
}

function page_clients(): void {
    $filtres = ['actifs' => 'Tous', 'particuliers' => 'Particuliers', 'pros' => 'Professionnels', 'tous' => 'Avec les effacés'];
    $f = choix('f', $filtres, 'actifs');
    $r = une_ligne(texte('r', 80, $_GET));
    $cs = clients_lister($r, $f);
    $n = (int)val("SELECT COUNT(*) FROM clients WHERE statut <> 'efface'");
    $pros = (int)val("SELECT COUNT(*) FROM clients WHERE statut <> 'efface' AND type = 'professionnel'");
    $tabs = '';
    foreach ($filtres as $k => $l) $tabs .= '<a href="' . h(u('clients', ['f' => $k] + ($r !== '' ? ['r' => $r] : []))) . '"' . ($k === $f ? ' aria-current="page"' : '') . '>' . h($l) . '</a>';
    $c = titre_page('Clients', $n . ' client' . ($n > 1 ? 's' : '') . ($pros ? ', dont ' . $pros . ' professionnel' . ($pros > 1 ? 's' : '') : ''), lien_bouton(u('client-edition'), icone('ajouter') . 'Nouveau', 'principal'));
    $c .= '<form class="recherche" method="get" action=""><input type="hidden" name="p" value="clients"><input type="hidden" name="f" value="' . h($f) . '">'
        . '<label class="champ-recherche">' . icone('chercher') . '<span class="sr">Chercher</span><input type="search" name="r" value="' . h($r) . '" placeholder="Nom, email, téléphone, ville, CL-…"></label></form>';
    $c .= '<nav class="sous-onglets" aria-label="Filtrer les clients">' . $tabs . '</nav>';
    $c .= $cs ? '<div class="liste">' . implode('', array_map(__NAMESPACE__ . '\\carte_client', $cs)) . '</div>'
              : '<p class="vide">' . h($r !== '' ? 'Aucun client ne correspond à « ' . $r . ' ».' : 'Aucun client pour l\'instant : ils arrivent avec les demandes du site, ou par « Nouveau ».') . '</p>';
    mise_en_page('Clients', $c, 'clients');
}

function page_client(): void {
    $id = entier('id', 1);
    $cl = $id ? client($id) : null;
    if (!$cl) rediriger('clients');
    if (($_GET['export'] ?? '') === 'json') {
        evenement('export', 'Données du client exportées (droit d\'accès ou de portabilité)', ['client' => $id]);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="donnees-' . strtolower($cl['numero']) . '.json"');
        header('Cache-Control: no-store, private');
        echo json_encode(client_exporter($id), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        switch (texte('_action', 40)) {
            case 'commentaire':
                commentaire(texte('texte', 2000), null, $id);
                flash('ok', 'Commentaire ajouté.');
                break;
            case 'avis_demande':
                q("UPDATE clients SET avis_demande_le = ?, maj_le = ? WHERE id = ?", [aujourdhui(), maintenant(), $id]);
                evenement('avis', 'Avis Google demandé', ['client' => $id]);
                flash('ok', 'Demande d\'avis notée.');
                break;
            case 'avis_recu':
                q("UPDATE clients SET avis_recu_le = ?, maj_le = ? WHERE id = ?", [aujourdhui(), maintenant(), $id]);
                evenement('avis', 'Avis Google reçu', ['client' => $id]);
                flash('ok', 'Avis reçu noté. Merci !');
                break;
            case 'inactif':
                q("UPDATE clients SET statut = CASE statut WHEN 'actif' THEN 'inactif' WHEN 'inactif' THEN 'actif' ELSE statut END, maj_le = ? WHERE id = ?", [maintenant(), $id]);
                evenement('modification', $cl['statut'] === 'actif' ? 'Client passé inactif' : 'Client réactivé', ['client' => $id]);
                break;
            case 'fusionner':
                $num = strtoupper(une_ligne(texte('doublon', 12)));
                $src = (int)val("SELECT id FROM clients WHERE numero = ? AND statut <> 'efface'", [$num]);
                if (!$src || $src === $id) { flash('erreur', 'Le numéro ' . $num . ' ne correspond à aucun autre client.'); break; }
                client_fusionner($id, $src);
                flash('ok', 'Le doublon ' . $num . ' est fusionné dans cette fiche.');
                break;
            case 'effacer':
                if (texte('confirmation', 10) !== 'EFFACER') { flash('erreur', 'Pour effacer, tapez EFFACER en majuscules.'); break; }
                $enCours = (int)val("SELECT COUNT(*) FROM commandes WHERE client_id = ? AND statut IN ('demande','proposition','confirmee')", [$id]);
                if ($enCours) { flash('erreur', 'Ce client a encore ' . $enCours . ' commande(s) en cours : terminez-les ou annulez-les avant d\'effacer.'); break; }
                client_effacer($id, une_ligne(texte('motif', 200)) ?: 'demande du client');
                flash('ok', 'Données personnelles effacées. Les commandes restent pour les comptes et les statistiques, sans nom ni coordonnées. Pensez aussi aux emails de ce client dans contact@ et, s\'il y a des factures, gardez-les (obligation comptable).');
                break;
        }
        rediriger('client', ['id' => $id]);
    }
    $cl = client($id);
    $ch = client_chiffres($id);
    $efface = $cl['statut'] === 'efface';
    $chips = ($cl['type'] === 'professionnel' ? '<span class="statut s-pro">Pro</span>' : '') . ($efface ? '<span class="statut s-efface">Effacé</span>' : ($cl['statut'] === 'inactif' ? '<span class="statut s-inactif">Inactif</span>' : ''));
    $c = titre_page('<span translate="no">' . h(client_nom($cl)) . '</span>', h($cl['numero']) . ' · client depuis le ' . h(date_fr($cl['premier_contact'], 'longue')) . '<span class="chips">' . $chips . '</span>',
        $efface ? '' : lien_bouton(u('commande-edition', ['client' => $id]), icone('ajouter') . 'Nouvelle commande', 'principal'), u('clients'));
    $c .= '<div class="tuiles">' . tuile('Commandes remises', (string)$ch['remises'], $ch['commandes'] > $ch['remises'] ? ($ch['commandes'] - $ch['remises']) . ' autre(s) demande(s)' : '')
        . tuile('Total', $ch['total'] ? euros($ch['total']) : '—', 'TTC') . tuile('Panier moyen', $ch['panier'] ? euros($ch['panier']) : '—')
        . tuile('Dernière commande', $ch['derniere'] ? date_fr($ch['derniere']) : '—', $ch['derniere'] ? date_fr($ch['derniere'], 'relative') : '') . '</div>';
    // Coordonnées
    if (!$efface) {
        $tel = $cl['telephone'] !== '' ? '<a href="tel:' . h(preg_replace('/[^0-9+]/', '', $cl['telephone'])) . '">' . icone('tel') . h($cl['telephone']) . '</a>' : '';
        $mail = $cl['email'] !== '' ? '<a href="mailto:' . h($cl['email']) . '">' . icone('email') . h($cl['email']) . '</a>' : '';
        $tel_quel = fn(string $t): string => $t !== '' ? '<span translate="no">' . h($t) . '</span>' : '';   // noms et ville : jamais traduits
        $c .= bloc('Coordonnées', definition(['Entreprise' => $tel_quel($cl['societe']), 'Contact' => $tel_quel($cl['contact']), 'Email' => $mail, 'Téléphone' => $tel, 'Ville' => $tel_quel($cl['ville']),
                                               'Premier contact' => h(CANAUX[$cl['canal']] ?? $cl['canal']) . ', le ' . h(date_fr($cl['premier_contact'], 'longue')),
                                               'Dernier échange' => h(date_fr($cl['dernier_echange'], 'heure')), 'Dans Qonto' => $cl['qonto'] !== '' ? 'Oui' : ''])
             . '<p>' . lien_bouton(u('client-edition', ['id' => $id]), 'Modifier', 'discret') . '</p>');
    } else {
        $c .= bloc('Coordonnées', '<p class="aide">Données personnelles effacées le ' . h(date_fr($cl['efface_le'], 'longue')) . '. Il ne reste que le numéro, les dates et les montants des commandes.</p>');
    }
    // Commandes
    $ks = commandes_du_client($id);
    foreach ($ks as &$k) { $k['client_statut'] = $cl['statut']; $k['client_societe'] = $cl['societe']; $k['client_prenom'] = $cl['prenom']; $k['client_nom'] = $cl['nom']; $k['client_type'] = $cl['type']; $k['client_numero'] = $cl['numero']; }
    unset($k);
    $c .= bloc('Commandes et demandes (' . count($ks) . ')', liste_commandes($ks, 'Aucune commande.', false));
    // Avis Google
    if (!$efface) {
        $lien = (string)parametre('lien_avis', '');
        if ($cl['avis_recu_le']) $av = '<p>' . icone('avis') . ' Avis reçu le ' . h(date_fr($cl['avis_recu_le'], 'longue')) . '.</p>';
        elseif ($cl['avis_demande_le']) $av = '<p>Avis demandé le ' . h(date_fr($cl['avis_demande_le'], 'longue')) . '.</p>' . formulaire('avis_recu', bouton(icone('avis') . 'L\'avis est arrivé', 'secondaire'));
        elseif (!$ch['remises']) $av = '<p class="aide">On demande un avis après une commande remise, à un client content.</p>';
        else {
            $dk = $ks[0] ?? null;
            foreach ($ks as $k) if ($k['statut'] === 'remise') { $dk = $k; break; }
            $av = $lien === '' ? '<p class="aide">' . fr_ko('Ajoutez d\'abord le lien d\'avis de la fiche Google dans <a href="' . h(u('reglages')) . '#avis">Réglages</a>.',
                    '먼저 <a href="' . h(u('reglages')) . '#avis">설정</a>에 구글 프로필의 리뷰 링크를 넣어 주세요.') . '</p>'
                : '<p class="aide">Seulement à un client content, sans contrepartie et sans demander de note (règles de Google).</p>'
                  . bloc_email('email-avis', 'Demander un avis Google', email_avis($dk + ['client_prenom' => $cl['prenom'], 'client_nom' => $cl['nom'], 'client_type' => $cl['type'], 'client_contact' => $cl['contact']], $lien), $cl['email'])
                  . formulaire('avis_demande', bouton(icone('avis') . 'J\'ai demandé un avis', 'secondaire'));
        }
        $c .= bloc('Avis Google', $av);
    }
    // Commentaires et historique
    $c .= bloc('Commentaires et historique', ($efface ? '' : formulaire_commentaire('Goûts, habitudes, préférences de retrait… Jamais d\'information de santé : pour une allergie, seulement l\'ingrédient à éviter.'))
        . fil_historique(historique(null, $id), true));
    // Droits et doublons
    if (!$efface) {
        $d = '<div class="actions-colonne">'
           . '<details class="replie"><summary>Un doublon ? Fusionner une autre fiche dans celle-ci</summary>'
           . formulaire('fusionner', champ('doublon', 'Numéro de l\'autre fiche (elle sera vidée)', '', ['placeholder' => 'CL-0012', 'pattern' => 'CL-[0-9]{4,}', 'required' => true]) . bouton('Fusionner', 'secondaire'), ['data-confirmer' => 'Fusionner l\'autre fiche dans celle-ci ? Ses commandes passent ici.']) . '</details>'
           . '<details class="replie"><summary>Le client demande ses données (droit d\'accès)</summary><p class="aide">Le fichier contient tout ce que la gestion garde sur lui : à lui envoyer depuis contact@ dans le mois.</p>'
           . '<p>' . lien_bouton(u('client', ['id' => $id, 'export' => 'json']), 'Télécharger ses données', 'discret') . '</p></details>'
           . '<details class="replie"><summary>Le client demande l\'effacement</summary>'
           . '<p class="aide">Son nom, ses coordonnées, ses commentaires et les textes de ses demandes sont effacés tout de suite. Les montants et les dates restent, sans rien qui le désigne (comptes et statistiques). Les factures Qonto ne sont pas touchées : elles doivent être gardées 10 ans.</p>'
           . formulaire('effacer', champ('motif', 'Motif', 'demande du client', ['maxlength' => '200']) . champ('confirmation', 'Tapez EFFACER pour confirmer', '', ['required' => true, 'autocomplete' => 'off', 'pattern' => 'EFFACER']) . bouton('Effacer ses données', 'danger')) . '</details>'
           . formulaire('inactif', bouton($cl['statut'] === 'actif' ? 'Marquer inactif (ne commande plus)' : 'Réactiver', 'discret'))
           . '</div>';
        $c .= bloc('Fiche et droits du client', $d);
    }
    mise_en_page(client_nom($cl), $c, 'clients');
}

function page_client_edition(): void {
    $id = entier('id', 1);
    $cl = $id ? client($id) : null;
    if ($id && (!$cl || $cl['statut'] === 'efface')) rediriger('clients');
    $erreurs = [];
    $d = $cl ?? ['type' => 'particulier', 'prenom' => '', 'nom' => '', 'societe' => '', 'contact' => '', 'email' => '', 'telephone' => '', 'ville' => '', 'canal' => 'instagram', 'qonto' => ''];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $d = ['type' => choix('type', TYPES_CLIENT, 'particulier'), 'prenom' => une_ligne(texte('prenom', 60)), 'nom' => une_ligne(texte('nom', 80)),
              'societe' => une_ligne(texte('societe', 100)), 'contact' => une_ligne(texte('contact', 100)), 'email' => mb_strtolower(une_ligne(texte('email', 120))),
              'telephone' => une_ligne(texte('telephone', 30)), 'ville' => une_ligne(texte('ville', 60)), 'canal' => choix('canal', CANAUX, 'autre'), 'qonto' => une_ligne(texte('qonto', 100))];
        if ($d['nom'] === '' && $d['societe'] === '') $erreurs[] = 'Indiquez au moins un nom ou une entreprise.';
        if ($d['email'] !== '' && !email_valide($d['email'])) $erreurs[] = 'L\'email n\'est pas valide.';
        if ($d['email'] !== '' && ($e = ligne("SELECT id, numero FROM clients WHERE email = ? AND statut <> 'efface' AND id <> ?", [$d['email'], $id]))) {
            $lienDoublon = '<a href="' . h(u('client', ['id' => $e['id']])) . '">' . h($e['numero']) . '</a>';
            $erreurs[] = fr_ko('Cet email est déjà celui du client ' . $lienDoublon . ' : ouvrez sa fiche, ou fusionnez les deux fiches.',
                               '이 이메일은 이미 ' . $lienDoublon . ' 고객의 이메일이에요. 그 고객 정보를 열거나, 두 고객 정보를 합치세요.');
        }
        if (!$erreurs) {
            if ($cl) { client_modifier($id, $d); flash('ok', 'Fiche enregistrée.'); }
            else { $id = client_creer($d); flash('ok', 'Client créé.'); }
            rediriger('client', ['id' => $id]);
        }
    }
    $titre = $cl ? 'Modifier ' . client_nom($cl) : 'Nouveau client';
    $c = titre_page(h($titre), $cl ? h($cl['numero']) : 'Seulement ce qui sert à préparer et remettre les commandes', '', $cl ? u('client', ['id' => $id]) : u('clients'));
    if ($erreurs) $c .= '<div class="erreur" role="alert">' . implode('<br>', $erreurs) . '</div>';
    $f = liste_choix('type', 'Type', TYPES_CLIENT, $d['type'])
       . '<div class="ligne-champs">' . champ('prenom', 'Prénom', $d['prenom'], ['autocomplete' => 'off']) . champ('nom', 'Nom de famille', $d['nom'], ['autocomplete' => 'off']) . '</div>'
       . champ('societe', 'Entreprise ou restaurant (professionnels)', $d['societe'], ['autocomplete' => 'off'])
       . champ('contact', 'Personne à contacter (professionnels)', $d['contact'], ['autocomplete' => 'off'])
       . '<div class="ligne-champs">' . champ('email', 'Email', $d['email'], ['type' => 'email', 'autocomplete' => 'off']) . champ('telephone', 'Téléphone', $d['telephone'], ['type' => 'tel', 'autocomplete' => 'off']) . '</div>'
       . '<div class="ligne-champs">' . champ('ville', 'Ville', $d['ville'], ['autocomplete' => 'off']) . liste_choix('canal', 'Premier contact par', CANAUX, $d['canal']) . '</div>'
       . champ('qonto', 'Nom du client dans Qonto (s\'il a une facture)', $d['qonto'], ['autocomplete' => 'off'])
       . '<p class="aide">Ni date de naissance, ni adresse si elle ne sert pas, ni information de santé (R-38 et politique de confidentialité).</p>'
       . '<div class="barre-enregistrer">' . bouton($cl ? 'Enregistrer' : 'Créer le client', 'principal large') . '</div>';
    $c .= formulaire('enregistrer', bloc('', $f));
    mise_en_page($titre, $c, 'clients');
}

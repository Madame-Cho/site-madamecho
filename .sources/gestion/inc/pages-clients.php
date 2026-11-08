<?php
/*
 * Madame Cho : espace de gestion. Clients : liste, fiche (commandes, commentaires, avis Google, droits RGPD), saisie.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

function carte_client(array $c): string {
    $estPro = $c['type'] === 'professionnel';
    $etat = $c['statut'] === 'efface' ? '<span class="statut s-efface">Effacé</span>' : ($c['statut'] === 'inactif' ? '<span class="statut s-inactif">Inactif</span>' : '');
    $avis = $c['avis_recu_le'] ? '<span class="avis-ok">' . icone('avis') . 'Avis reçu</span>' : ($c['avis_demande_le'] ? '<span class="avis-attente">' . icone('avis') . 'Avis demandé</span>' : '');
    $chiffres = (int)$c['remises'] ? (int)$c['remises'] . ' commande' . ((int)$c['remises'] > 1 ? 's' : '') . ' · ' . ($estPro ? euros((int)$c['total_ht']) . ' HT' : euros((int)$c['total']) . ' TTC') . ($c['derniere'] ? ' · dernière le ' . date_fr($c['derniere']) : '') : 'Pas encore de commande remise';
    $enCours = (int)$c['en_cours'] ? '<span class="compte">' . (int)$c['en_cours'] . ' en cours</span>' : '';
    $pa = pastilles()['clients'][(int)$c['id']] ?? null;   // la pastille de la personne (FR-041)
    if ($pa) $avis = etiquette_pastille($pa) . $avis;
    return '<a class="carte-cmd carte-client' . ($pa ? ' est-' . $pa['niveau'] : '') . '" href="' . h(u('client', ['id' => $c['id']])) . '">'
         . '<span class="l1">' . chip_type($estPro) . '<b translate="no">' . h(client_nom($c)) . '</b>' . $etat . $enCours . '</span>'
         . '<span class="l2">' . h($chiffres) . '</span>'
         . '<span class="l4">' . h($c['numero']) . ($c['ville'] !== '' ? ' · <span translate="no">' . h($c['ville']) . '</span>' : '') . ' · ' . h(CANAUX[$c['canal']] ?? $c['canal']) . ' · dernier échange ' . h(date_fr($c['dernier_echange'], 'relative')) . '</span>'
         . ($avis !== '' ? '<span class="l5">' . $avis . '</span>' : '')
         . '</a>';
}

function page_clients(): void {
    $filtres = ['actifs' => 'Tous', 'particuliers' => 'Particuliers (B2C)', 'pros' => 'Professionnels (B2B)', 'tous' => 'Avec les effacés'];
    $f = choix('f', $filtres, 'actifs');
    $r = une_ligne(texte('r', 80, $_GET));
    $cs = clients_lister($r, $f);
    $n = (int)val("SELECT COUNT(*) FROM clients WHERE statut <> 'efface'");
    $pros = (int)val("SELECT COUNT(*) FROM clients WHERE statut <> 'efface' AND type = 'professionnel'");
    $parts = $n - $pros;
    $comptes = ['actifs' => $n, 'particuliers' => $parts, 'pros' => $pros];
    $tabs = '';
    foreach ($filtres as $k => $l) $tabs .= '<a href="' . h(u('clients', ['f' => $k] + ($r !== '' ? ['r' => $r] : []))) . '"' . ($k === $f ? ' aria-current="page"' : '') . '>' . h($l)
        . (isset($comptes[$k]) ? ' <span class="compte">' . $comptes[$k] . '</span>' : '') . '</a>';
    $c = titre_page('Clients', $n . ' client' . ($n > 1 ? 's' : '') . ' : ' . $parts . ' particulier' . ($parts > 1 ? 's' : '') . ' (B2C), ' . $pros . ' professionnel' . ($pros > 1 ? 's' : '') . ' (B2B)',
        lien_bouton(u('client-edition'), icone('ajouter') . 'Nouveau', 'principal'));
    $c .= '<form class="recherche" method="get" action=""><input type="hidden" name="p" value="clients"><input type="hidden" name="f" value="' . h($f) . '">'
        . '<label class="champ-recherche">' . icone('chercher') . '<span class="sr">Chercher</span><input type="search" name="r" value="' . h($r) . '" placeholder="Nom, email, téléphone, ville, CL-…"></label></form>';
    $c .= '<nav class="sous-onglets" aria-label="Filtrer les clients">' . $tabs . '</nav>';
    if (!$cs) $c .= '<p class="vide">' . h($r !== '' ? 'Aucun client ne correspond à « ' . $r . ' ».' : 'Aucun client pour l\'instant : ils arrivent avec les demandes du site, ou par « Nouveau ».') . '</p>';
    elseif ($f === 'particuliers' || $f === 'pros') $c .= '<div class="liste">' . implode('', array_map(__NAMESPACE__ . '\\carte_client', $cs)) . '</div>';
    else {
        // « Tous » : deux listes bien séparées, les professionnels (B2B, montants HT) puis les particuliers (B2C, montants TTC)
        foreach (['professionnel' => ['Professionnels (B2B)', 'Restaurants et entreprises · montants HT, comme leurs factures'], 'particulier' => ['Particuliers (B2C)', 'Montants TTC']] as $type => [$titre, $aide]) {
            $liste = array_filter($cs, fn($x) => $x['type'] === $type);
            if (!$liste) continue;
            $c .= '<h2 class="titre-section type-' . $type . '">' . chip_type($type === 'professionnel') . ' ' . h($titre) . ' <span class="compte">' . count($liste) . '</span></h2>'
                . '<p class="aide">' . h($aide) . '</p><div class="liste">' . implode('', array_map(__NAMESPACE__ . '\\carte_client', $liste)) . '</div>';
        }
    }
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
                commentaire(texte('texte', 2000), null, $id, choix('par', CANAUX_ECHANGE, ''));
                flash('ok', 'Échange noté.');
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
            case 'fusionner_doublon':   // on garde la fiche la plus ancienne, avec les commandes et l'historique des deux
                $autre = client(entier('autre', 1));
                if (!$autre || $autre['statut'] === 'efface' || (int)$autre['id'] === $id) { flash('erreur', 'Cette autre fiche n\'existe plus.'); break; }
                [$garde, $vide] = (int)$autre['id'] < $id ? [(int)$autre['id'], $id] : [$id, (int)$autre['id']];
                client_fusionner($garde, $vide);
                flash('ok', 'Les deux fiches sont fusionnées dans ' . client($garde)['numero'] . '.');
                rediriger('client', ['id' => $garde]);
            case 'doublon_ecarte':
                client_doublon_ecarter($id, entier('autre', 1));
                flash('ok', 'Noté : ces deux fiches ne seront plus signalées comme doublon.');
                break;
            case 'supprimer':
                if (texte('confirmation', 12) !== 'SUPPRIMER') { flash('erreur', 'Pour supprimer, tapez SUPPRIMER en majuscules.'); break; }
                if (!client_supprimable($id)) { flash('erreur', 'Ce client a des commandes : utilisez plutôt « Demande d\'effacement ».'); break; }
                $num = client($id)['numero'];
                client_supprimer($id, une_ligne(texte('motif', 200)) ?: 'essai');
                flash('ok', 'Fiche ' . $num . ' mise à la corbeille : 30 jours pour la restaurer (Plus › Corbeille).');
                rediriger('clients');
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
    $chips = chip_type($cl['type'] === 'professionnel') . ($efface ? '<span class="statut s-efface">Effacé</span>' : ($cl['statut'] === 'inactif' ? '<span class="statut s-inactif">Inactif</span>' : ''));
    $c = titre_page('<span translate="no">' . h(client_nom($cl)) . '</span>', h($cl['numero']) . ' · client depuis le ' . h(date_fr($cl['premier_contact'], 'longue')) . '<span class="chips">' . $chips . '</span>',
        $efface ? '' : ($cl['type'] === 'professionnel'
            ? lien_bouton(u('commande-edition', ['client' => $id, 'livraison' => 1]), icone('ajouter') . 'Nouvelle livraison', 'principal') . lien_bouton(u('commande-edition', ['client' => $id]), 'Autre commande', 'discret')
            : lien_bouton(u('commande-edition', ['client' => $id]), icone('ajouter') . 'Nouvelle commande', 'principal')), u('clients'));
    // Ce qui a changé sur la fiche depuis la dernière visite de la personne (FR-041), puis la fiche est vue pour elle
    $c .= encadre_changements('client', $id);
    pastilles_vu('client', (string)$id);
    $c .= '<div class="tuiles">' . tuile('Commandes remises', (string)$ch['remises'], $ch['commandes'] > $ch['remises'] ? ($ch['commandes'] - $ch['remises']) . ' autre(s) demande(s)' : '')
        . ($cl['type'] === 'professionnel'   // un professionnel en HT, comme ses factures ; un particulier en TTC (demande d'Olivier, 07/11/2026)
            ? tuile('Total', $ch['total_ht'] ? euros($ch['total_ht']) : '—', 'HT') . tuile('Livraison moyenne', $ch['panier_ht'] ? euros($ch['panier_ht']) : '—', 'HT')
            : tuile('Total', $ch['total'] ? euros($ch['total']) : '—', 'TTC') . tuile('Panier moyen', $ch['panier'] ? euros($ch['panier']) : '—', 'TTC'))
        . tuile('Dernière commande', $ch['derniere'] ? date_fr($ch['derniere']) : '—', $ch['derniere'] ? date_fr($ch['derniere'], 'relative') : '') . '</div>';
    // Doublon possible : même téléphone, mêmes prénom et nom, ou même entreprise ; rien n'est fusionné sans Youngmi
    $dbl = $efface ? [] : client_doublons($cl);
    if ($dbl) {
        $t = '';
        foreach ($dbl as $o) {
            $garde = (int)$o['id'] < $id ? $o : $cl;
            $coord = implode(' · ', array_filter([$o['email'], $o['telephone']]));
            $t .= '<li><p><a href="' . h(u('client', ['id' => $o['id']])) . '"><b translate="no">' . h(client_nom($o)) . '</b></a> <span class="petit">' . h($o['numero']) . '</span>'
                . ($coord !== '' ? '<br><span class="petit" translate="no">' . h($coord) . '</span>' : '') . '<br><span class="raisons">' . h(doublon_raisons($o['raisons'])) . '</span></p>'
                . '<div class="actions">'
                . formulaire('fusionner_doublon', '<input type="hidden" name="autre" value="' . (int)$o['id'] . '">' . bouton('Fusionner les deux fiches', 'secondaire'),
                             ['data-confirmer' => 'C\'est la même personne ? On garde la fiche ' . $garde['numero'] . ', la plus ancienne, avec les commandes et l\'historique des deux.'])
                . formulaire('doublon_ecarte', '<input type="hidden" name="autre" value="' . (int)$o['id'] . '">' . bouton('Ce n\'est pas la même personne', 'discret'))
                . '</div></li>';
        }
        $c .= bloc('Doublon possible', '<p class="aide">Une autre fiche ressemble à celle-ci. Si c\'est la même personne, fusionnez : on garde la fiche la plus ancienne, avec toutes les commandes. Rien n\'est fusionné tout seul.</p>'
            . '<ul class="doublons">' . $t . '</ul>', 'alerte', 'doublon');
    }
    // Coordonnées
    if (!$efface) {
        $tel = $cl['telephone'] !== '' ? '<a href="tel:' . h(preg_replace('/[^0-9+]/', '', $cl['telephone'])) . '">' . icone('tel') . h($cl['telephone']) . '</a>' : '';
        $mail = $cl['email'] !== '' ? '<a href="mailto:' . h($cl['email']) . '">' . icone('email') . h($cl['email']) . '</a>' : '';
        $tel_quel = fn(string $t): string => $t !== '' ? '<span translate="no">' . h($t) . '</span>' : '';   // noms et ville : jamais traduits
        $c .= bloc('Coordonnées', definition(['Type' => h(TYPES_CLIENT_LONGS[$cl['type']] ?? $cl['type']) . ' · ' . ($cl['type'] === 'professionnel' ? 'montants HT' : 'montants TTC'), 'Entreprise' => $tel_quel($cl['societe']), 'Contact' => $tel_quel($cl['contact']), 'Email' => $mail, 'Téléphone' => $tel, 'Ville' => $tel_quel($cl['ville']),
                                               'Premier contact' => h(CANAUX[$cl['canal']] ?? $cl['canal']) . ', le ' . h(date_fr($cl['premier_contact'], 'longue')),
                                               'Dernier échange' => h(date_fr($cl['dernier_echange'], 'heure')), 'Dans Qonto' => $cl['qonto'] !== '' ? 'Oui' : ''])
             . '<p>' . lien_bouton(u('client-edition', ['id' => $id]), 'Modifier', 'discret') . '</p>');
    } else {
        $c .= bloc('Coordonnées', '<p class="aide">Données personnelles effacées le ' . h(date_fr($cl['efface_le'], 'longue')) . '. Il ne reste que le numéro, les dates et les montants des commandes.</p>');
    }
    // Restaurant : les relevés du mois, pour la facture Qonto de fin de mois
    if ($cl['type'] === 'professionnel' && !$efface) {
        $rel = '';
        foreach (releves_du_client($id) as $r) {
            $etat = $r['facturees'] === $r['n'] ? 'facture ' . implode(', ', $r['factures']) . ($r['payees'] === $r['n'] ? ', payée' : ', pas encore payée') : ($r['facturees'] ? 'facturé en partie' : 'pas encore facturé');
            $rel .= '<a class="carte-cmd" href="' . h(u('releve', ['id' => $id, 'mois' => $r['mois']])) . '"><span class="l1"><b>' . h(ucfirst(mois_texte($r['mois']))) . '</b>'
                  . '<span class="statut ' . ($r['payees'] === $r['n'] ? 's-confirmee' : ($r['facturees'] === $r['n'] ? 's-proposition' : 's-demande')) . '">' . h($r['payees'] === $r['n'] ? 'Payé' : ($r['facturees'] === $r['n'] ? 'Facturé' : 'À facturer')) . '</span></span>'
                  . '<span class="l2">' . h($r['n'] . ' livraison(s) · ' . euros($r['livre_ht']) . ' HT · ' . euros($r['livre_ttc']) . ' TTC') . '</span><span class="l3">' . h($etat) . '</span></a>';
        }
        $c .= bloc('Relevés du mois', ($rel !== '' ? '<div class="liste">' . $rel . '</div>' : '<p class="vide">Aucune livraison notée.</p>')
            . '<p class="aide">Une livraison = une commande. Le relevé du mois sert à faire la facture Qonto.</p>');
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
        elseif (!$ch['remises']) $av = '<p class="aide">On propose un avis après une commande remise, à tous les clients de la même façon.</p>';
        else {
            $dk = $ks[0] ?? null;
            foreach ($ks as $k) if ($k['statut'] === 'remise') { $dk = $k; break; }
            $av = $lien === '' ? '<p class="aide">' . fr_ko('Ajoutez d\'abord le lien d\'avis de la fiche Google dans <a href="' . h(u('reglages')) . '#avis">Paramètres</a>.',
                    '먼저 <a href="' . h(u('reglages')) . '#avis">설정</a>에 구글 프로필의 리뷰 링크를 넣어 주세요.') . '</p>'
                : '<p class="aide">À proposer à tous les clients, sans tri, sans contrepartie ni note demandée (règles de Google).</p>'
                  . ($dk && email_valide((string)$cl['email']) ? '<div class="actions">' . lien_bouton(u('commande', ['id' => $dk['id']]) . '#mail-avis', icone('email') . 'Préparer le mail (commande ' . h($dk['reference']) . ')', 'principal') . '</div>'
                     : bloc_email('email-avis', 'Demander un avis Google', email_avis($dk + ['client_prenom' => $cl['prenom'], 'client_nom' => $cl['nom'], 'client_type' => $cl['type'], 'client_contact' => $cl['contact'], 'client_email' => $cl['email']], $lien), (string)$cl['email']))
                  . formulaire('avis_demande', bouton(icone('avis') . 'J\'ai demandé un avis', 'secondaire'));
        }
        $c .= bloc('Avis Google', $av);
    }
    // Tous les échanges avec ce client, toutes commandes confondues, le plus récent en haut (audit n° 3, C12) ; le journal technique reste à part
    $evsClient = historique(null, $id);
    $convClient = array_values(array_filter($evsClient, fn($e) => in_array($e['type'], ['email_client', 'email_recu', 'commentaire', 'probleme'], true)));
    $c .= bloc('Tous les échanges', '<p class="aide">Mails envoyés et reçus, et échanges notés, de toutes ses commandes. Le numéro MC-… ouvre la commande.</p>'
        . fil_conversation($convClient, true), '', 'tous-echanges');
    // Notes sur le client et journal
    $c .= bloc('Notes et historique', ($efface ? '' : formulaire_commentaire('Goûts, habitudes, retrait préféré… Allergie : l\'ingrédient à éviter, sans info de santé.'))
        . '<details class="replie"><summary>Journal détaillé (étapes, paiements, modifications)</summary>' . fil_historique($evsClient, true) . '</details>');
    // Droits et doublons
    if (!$efface) {
        $d = '<div class="actions-colonne">'
           . '<details class="replie"><summary>Fusionner un doublon</summary>'
           . formulaire('fusionner', champ('doublon', 'Numéro de l\'autre fiche (elle sera vidée)', '', ['placeholder' => 'CL-0012', 'pattern' => 'CL-[0-9]{4,}', 'required' => true]) . bouton('Fusionner', 'secondaire'), ['data-confirmer' => 'Fusionner l\'autre fiche dans celle-ci ? Ses commandes passent ici.']) . '</details>'
           . '<details class="replie"><summary>Demande d\'accès à ses données</summary><p class="aide">Tout ce que la gestion garde sur lui, à lui envoyer depuis contact@ sous un mois.</p>'
           . '<p>' . lien_bouton(u('client', ['id' => $id, 'export' => 'json']), 'Télécharger ses données', 'discret') . '</p></details>'
           . '<details class="replie"><summary>Demande d\'effacement</summary>'
           . '<p class="aide">Nom, coordonnées, notes et demandes sont effacés tout de suite. Montants et dates restent, anonymes. Les factures Qonto ne changent pas (gardées 10 ans).</p>'
           . formulaire('effacer', champ('motif', 'Motif', 'demande du client', ['maxlength' => '200']) . champ('confirmation', 'Tapez EFFACER pour confirmer', '', ['required' => true, 'autocomplete' => 'off', 'pattern' => 'EFFACER']) . bouton('Effacer ses données', 'danger')) . '</details>'
           . (client_supprimable($id) ? '<details class="replie"><summary>' . icone('supprimer') . 'Supprimer la fiche (essai, erreur de saisie)</summary>'
               . '<p class="aide">Possible seulement pour une fiche sans aucune commande. Elle va à la corbeille (Plus › Corbeille) : 30 jours pour la restaurer.</p>'
               . formulaire('supprimer', champ('motif', 'Motif', 'essai', ['maxlength' => '200']) . champ('confirmation', 'Tapez SUPPRIMER pour confirmer', '', ['required' => true, 'autocomplete' => 'off', 'pattern' => 'SUPPRIMER'])
                   . bouton(icone('supprimer') . 'Supprimer la fiche', 'danger'), ['data-confirmer' => 'Supprimer définitivement cette fiche client ?']) . '</details>' : '')
           . formulaire('inactif', bouton($cl['statut'] === 'actif' ? 'Marquer inactif (ne commande plus)' : 'Réactiver', 'discret'))
           . '</div>';
        $c .= bloc('Fiche et droits du client', $d);
    }
    mise_en_page(client_nom($cl), $c, 'clients');
}

/* ---------- Relevé du mois d'un restaurant : les livraisons à facturer ensemble dans Qonto ---------- */
function page_releve(): void {
    $id = entier('id', 1);
    $cl = $id ? client($id) : null;
    if (!$cl) rediriger('clients');
    $mois = is_string($_GET['mois'] ?? null) && preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', $_GET['mois']) ? $_GET['mois'] : date('Y-m');
    $r = releve($id, $mois);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        if (texte('_action', 40) === 'facture') {
            $facture = strtoupper(une_ligne(texte('facture', 40)));
            $paye = ($_POST['paye'] ?? '') === '1';
            if (!$r['n']) flash('erreur', 'Aucune livraison ce mois-là.');
            elseif ($facture === '' && !$paye) flash('erreur', 'Indiquez le numéro de la facture Qonto, ou cochez « Facture payée ».');
            elseif ($facture !== '' && !$r['a_facturer'] && !in_array($facture, $r['factures'], true)) flash('erreur', 'Toutes les livraisons livrées de ce mois ont déjà une facture : pour corriger un numéro, ouvrez la livraison.');
            else {
                $n = releve_noter($r, $facture, $paye, choix('moyen', MOYENS, 'virement'));
                if (!$n) flash('ok', 'Rien à changer : c\'était déjà noté.');
                else flash('ok', $n . ' livraison(s) notée(s)' . ($facture !== '' ? ' avec la facture ' . $facture : '') . ($paye ? ', payée(s)' : '') . '.');
            }
        }
        rediriger('releve', ['id' => $id, 'mois' => $mois]);
    }
    $t = strtotime($mois . '-01');
    $avant = date('Y-m', strtotime('-1 month', $t)); $apres = date('Y-m', strtotime('+1 month', $t));
    $nav = '<p class="nav-mois"><a href="' . h(u('releve', ['id' => $id, 'mois' => $avant])) . '">‹ ' . h(mois_texte($avant)) . '</a>'
         . ($apres <= date('Y-m') ? '<a href="' . h(u('releve', ['id' => $id, 'mois' => $apres])) . '">' . h(mois_texte($apres)) . ' ›</a>' : '') . '</p>';
    $c = titre_page('Relevé de ' . h(mois_texte($mois)), '<span translate="no">' . h(client_nom($cl)) . '</span> · ' . h($cl['numero']),
        lien_bouton(u('commande-edition', ['client' => $id, 'livraison' => 1]), icone('ajouter') . 'Nouvelle livraison', 'discret'), u('client', ['id' => $id])) . $nav;
    if (!$r['n']) {
        $c .= '<p class="vide">Aucune livraison en ' . h(mois_texte($mois)) . '.</p>';
        mise_en_page('Relevé de ' . mois_texte($mois), $c, 'clients');
    }
    $tvas = array_unique(array_column($r['lignes'], 'tva'));
    $c .= '<div class="tuiles">' . tuile('Livraisons', (string)$r['n'], $r['a_facturer'] . ' à facturer') . tuile('Total HT', euros($r['ht']))
        . tuile('TVA', euros($r['tva']), count($tvas) === 1 ? nombre_tva((int)reset($tvas)) : '') . tuile('Total TTC', euros($r['ttc'])) . '</div>';
    if ($r['livrees'] < $r['n']) $c .= '<p class="erreur">' . icone('alerte') . ' ' . (($x = $r['n'] - $r['livrees']) > 1 ? $x . ' livraisons pas encore « Livrées » : hors total, à vérifier avant la facture.' : '1 livraison pas encore « Livrée » : hors total, à vérifier avant la facture.') . '</p>';
    if ($r['facturees']) $c .= '<p class="aide">' . $r['facturees'] . ($r['facturees'] > 1 ? ' livraisons déjà facturées (' : ' livraison déjà facturée (') . h(implode(', ', $r['factures'])) . '), hors total.</p>';
    // Les lignes, regroupées comme sur la facture
    $rows = '';
    foreach ($r['lignes'] as $l) {
        $rows .= '<tr><td><b translate="no">' . h($l['nom']) . '</b>' . ($l['libelle'] !== '' ? '<br><span class="petit" translate="no">' . h($l['libelle']) . '</span>' : '') . '</td>'
               . '<td class="nombre">' . (int)$l['q'] . '</td><td class="nombre">' . h(euros((int)$l['ht'])) . '</td><td class="nombre">' . h(euros((int)$l['ht'] * (int)$l['q'])) . '</td></tr>';
    }
    $pied = '<tr><td colspan="3">Total HT</td><td class="nombre">' . h(euros($r['ht'])) . '</td></tr>'
          . '<tr><td colspan="3">TVA' . (count($tvas) === 1 ? ' ' . h(nombre_tva((int)reset($tvas))) : '') . '</td><td class="nombre">' . h(euros($r['tva'])) . '</td></tr>'
          . '<tr><td colspan="3"><b>Total TTC</b></td><td class="nombre"><b>' . h(euros($r['ttc'])) . '</b></td></tr>';
    $c .= !$r['a_facturer'] ? '<p class="ok">Rien à facturer ce mois-ci.</p>' : bloc('À recopier dans la facture Qonto', '<table class="tableau releve"><thead><tr><th>Produit</th><th class="nombre">Quantité</th><th class="nombre">Prix HT</th><th class="nombre">Total HT</th></tr></thead><tbody>' . $rows . '</tbody><tfoot>' . $pied . '</tfoot></table>'
        . '<p class="aide">Les prix HT viennent de la gamme pro (Produits). La TVA est calculée sur le total HT, comme dans Qonto : la facture doit arriver au même total TTC, sinon un prix a changé.</p>');
    // Les livraisons du mois
    foreach ($r['livraisons'] as &$k) { $k['client_statut'] = $cl['statut']; $k['client_societe'] = $cl['societe']; $k['client_prenom'] = $cl['prenom']; $k['client_nom'] = $cl['nom']; $k['client_type'] = $cl['type']; $k['client_numero'] = $cl['numero']; }
    unset($k);
    $c .= bloc('Les livraisons (' . $r['n'] . ')', liste_commandes($r['livraisons'], '', false));
    // Facture et paiement, notés sur toutes les livraisons du mois
    $etat = $r['facturees'] === $r['n'] ? 'Facture ' . implode(', ', $r['factures']) . ($r['payees'] === $r['n'] ? ', payée.' : ', pas encore payée.')
          : ($r['facturees'] ? $r['facturees'] . ' livraison(s) sur ' . $r['n'] . ' facturée(s).' : 'Pas encore facturé.');
    $c .= bloc('Facture et paiement', '<p><b>' . h($etat) . '</b></p>'
        . formulaire('facture', '<div class="ligne-champs">' . champ('facture', 'Numéro de la facture Qonto', !$r['a_facturer'] && count($r['factures']) === 1 ? $r['factures'][0] : '', ['placeholder' => 'F-2026-013', 'maxlength' => '40', 'autocomplete' => 'off'])
            . liste_choix('moyen', 'Payée par', MOYENS, 'virement') . '</div>'
            . case_a_cocher('paye', 'Facture payée', $r['payees'] === $r['n'] && $r['n'] > 0)
            . bouton($r['a_facturer'] ? 'Noter la facture sur les ' . $r['a_facturer'] . ' livraison(s) à facturer' : 'Noter le paiement', 'secondaire'))
        . '<p class="aide">Notez ici le numéro de la facture Qonto. Cochez « Facture payée » quand le virement arrive.</p>');
    mise_en_page('Relevé de ' . mois_texte($mois), $c, 'clients');
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
    $c = titre_page(h($titre), $cl ? h($cl['numero']) : '', '', $cl ? u('client', ['id' => $id]) : u('clients'));
    if ($erreurs) $c .= '<div class="erreur" role="alert">' . implode('<br>', $erreurs) . '</div>';
    $f = liste_choix('type', 'Type', TYPES_CLIENT_LONGS, $d['type'])
       . '<div class="ligne-champs">' . champ('prenom', 'Prénom', $d['prenom'], ['autocomplete' => 'off']) . champ('nom', 'Nom de famille', $d['nom'], ['autocomplete' => 'off']) . '</div>'
       . champ('societe', 'Entreprise ou restaurant (professionnels)', $d['societe'], ['autocomplete' => 'off'])
       . champ('contact', 'Personne à contacter (professionnels)', $d['contact'], ['autocomplete' => 'off'])
       . '<div class="ligne-champs">' . champ('email', 'Email', $d['email'], ['type' => 'email', 'autocomplete' => 'off']) . champ('telephone', 'Téléphone', $d['telephone'], ['type' => 'tel', 'autocomplete' => 'off']) . '</div>'
       . '<div class="ligne-champs">' . champ('ville', 'Ville', $d['ville'], ['autocomplete' => 'off']) . liste_choix('canal', 'Premier contact par', CANAUX, $d['canal']) . '</div>'
       . champ('qonto', 'Nom du client dans Qonto (s\'il a une facture)', $d['qonto'], ['autocomplete' => 'off'])
       . '<p class="aide">Ni date de naissance, ni adresse inutile, ni info de santé.</p>'
       . '<div class="barre-enregistrer">' . bouton($cl ? 'Enregistrer' : 'Créer le client', 'principal large') . '</div>';
    $c .= formulaire('enregistrer', bloc('', $f));
    mise_en_page($titre, $c, 'clients');
}

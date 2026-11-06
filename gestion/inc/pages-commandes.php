<?php
/*
 * Madame Cho : espace de gestion. Aujourd'hui (tableau de bord), commandes, une commande, nouvelle commande,
 * agenda (.ics) et fiche de production.
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

/* ---------- Aujourd'hui ---------- */
function page_accueil(): void {
    $t = tableau_de_bord();
    $auj = aujourdhui();
    $moisNom = MOIS[(int)date('n')];
    $kpi = '<div class="tuiles">'
         . tuile('À répondre', (string)count($t['a_repondre']), count(array_filter($t['a_repondre'], fn($k) => commande_en_retard($k))) ? count(array_filter($t['a_repondre'], fn($k) => commande_en_retard($k))) . ' en retard' : 'réponse promise sous 24 h')
         . tuile('Retraits à venir', (string)count($t['retraits']), '7 prochains jours')
         . tuile('Remises en ' . $moisNom, (string)$t['mois']['n'], euros($t['mois']['ca']) . ' TTC')
         . tuile('Panier moyen', $t['mois']['n'] ? euros($t['mois']['panier']) : '—', $moisNom)
         . '</div>';
    $c = titre_page('Aujourd\'hui', h(ucfirst(date_fr($auj, 'jour'))), lien_bouton(u('production'), icone('imprimer') . 'Fiche de production', 'discret'));
    $c .= $kpi;
    if ($t['depasses']) $c .= bloc('Retraits passés à clôturer', '<p class="aide">Ces commandes confirmées ont une date de retrait passée : marquez-les remises ou annulées.</p>' . liste_commandes($t['depasses'], ''), 'alerte');
    if ($t['problemes']) $c .= bloc(icone('alerte') . ' Problèmes ouverts', liste_commandes($t['problemes'], ''), 'alerte');
    $c .= bloc('Demandes à répondre', liste_commandes($t['a_repondre'], 'Aucune demande en attente.'));
    // Retraits groupés par jour
    $jours = [];
    foreach ($t['retraits'] as $k) $jours[$k['retrait_date']][] = $k;
    $r = '';
    foreach ($jours as $d => $ks) {
        $l = $d === $auj ? 'Aujourd\'hui' : ($d === date('Y-m-d', strtotime('+1 day')) ? 'Demain' : ucfirst(date_fr($d, 'jour')));
        $r .= '<h3 class="jour">' . h($l) . ' <span class="compte">' . count($ks) . '</span></h3>' . liste_commandes($ks, '');
    }
    $c .= bloc('Retraits des 7 prochains jours', $r !== '' ? $r : '<p class="vide">Aucun retrait confirmé cette semaine.</p>');
    if ($t['propositions']) $c .= bloc('Propositions en attente de réponse du client', liste_commandes($t['propositions'], ''));
    if ($t['acomptes']) $c .= bloc('Acomptes attendus', liste_commandes($t['acomptes'], ''));
    $c .= '<p class="aide centre">' . (int)$t['demandes_mois'] . ' demande(s) reçue(s) en ' . h($moisNom) . '. ' . '<a href="' . h(u('statistiques')) . '">Voir les statistiques</a></p>';
    mise_en_page('Aujourd\'hui', $c, 'accueil');
}

/* ---------- Commandes ---------- */
function page_commandes(): void {
    $vues = ['en_cours' => 'En cours', 'a_venir' => 'À venir', 'terminees' => 'Terminées', 'problemes' => 'Problèmes', 'toutes' => 'Toutes'];
    $vue = choix('vue', $vues, 'en_cours');
    $r = une_ligne(texte('r', 80, $_GET));
    $ks = commandes_lister($vue, $r);
    $comptes = ['en_cours' => (int)val("SELECT COUNT(*) FROM commandes WHERE statut IN ('demande','proposition','confirmee')"),
                'problemes' => (int)val("SELECT COUNT(*) FROM commandes WHERE probleme = 1")];
    $tabs = '';
    foreach ($vues as $k => $l) {
        $n = isset($comptes[$k]) && $comptes[$k] ? ' <span class="compte">' . $comptes[$k] . '</span>' : '';
        $tabs .= '<a href="' . h(u('commandes', ['vue' => $k] + ($r !== '' ? ['r' => $r] : []))) . '"' . ($k === $vue ? ' aria-current="page"' : '') . '>' . h($l) . $n . '</a>';
    }
    $c = titre_page('Commandes', 'Demandes et commandes, de la demande à la remise', lien_bouton(u('commande-edition'), icone('ajouter') . 'Nouvelle', 'principal'));
    $c .= '<form class="recherche" method="get" action="">' . '<input type="hidden" name="p" value="commandes"><input type="hidden" name="vue" value="' . h($vue) . '">'
        . '<label class="champ-recherche">' . icone('chercher') . '<span class="sr">Chercher</span><input type="search" name="r" value="' . h($r) . '" placeholder="Nom, référence, facture…"></label></form>';
    $c .= '<nav class="sous-onglets" aria-label="Filtrer les commandes">' . $tabs . '</nav>';
    $vide = $r !== '' ? 'Aucune commande ne correspond à « ' . $r . ' ».' : match ($vue) {
        'en_cours' => 'Aucune commande en cours.', 'a_venir' => 'Aucun retrait confirmé à venir.', 'problemes' => 'Aucun problème ouvert.', default => 'Aucune commande.' };
    $c .= liste_commandes($ks, $vide);
    mise_en_page('Commandes', $c, 'commandes');
}

function commande_depuis_requete(): ?array {
    if (!empty($_GET['ref']) && is_string($_GET['ref'])) return commande_par_reference(strtoupper(trim($_GET['ref'])));
    $id = entier('id', 1);
    return $id ? commande($id) : null;
}

function page_commande(): void {
    $k = commande_depuis_requete();
    if (!$k) {
        $ref = is_string($_GET['ref'] ?? null) ? $_GET['ref'] : '';
        mise_en_page('Introuvable', titre_page('Commande introuvable', '', '', u('commandes'))
            . '<p>' . ($ref !== '' ? 'La référence « ' . h($ref) . ' » n\'est pas dans la gestion. ' . (parametre('demandes_site') === '1' ? 'Si elle vient d\'un email du site, l\'enregistrement a pu échouer : créez la commande à partir de l\'email.' : 'Les demandes du site ne s\'enregistrent pas encore toutes seules : créez la commande à partir de l\'email.') : 'Cette commande n\'existe pas.') . '</p>'
            . '<p>' . lien_bouton(u('commande-edition', $ref !== '' ? ['ref' => $ref] : []), 'Créer la commande', 'principal') . '</p>', 'commandes');
    }
    $id = (int)$k['id'];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        $a = texte('_action', 40);
        switch ($a) {
            case 'proposition':
                $prix = centimes(texte('prix_final', 12));
                $acompte = centimes(texte('acompte', 12));
                commande_statut($id, 'proposition', $prix !== null ? ['prix_final' => $prix] : []);
                if ($acompte) commande_paiement($id, 'acompte_demande', '', $acompte, (string)$k['facture']);
                flash('ok', 'Proposition notée. Quand le client accepte par écrit, confirmez la commande.');
                break;
            case 'confirmer':
                $d = date_valide(texte('retrait_date', 10));
                $h = preg_match('/^\d{2}:\d{2}$/', texte('retrait_heure', 5)) ? texte('retrait_heure', 5) : '';
                $prix = centimes(texte('prix_final', 12));
                commande_statut($id, 'confirmee', ['retrait_date' => $d, 'retrait_heure' => $h] + ($prix !== null ? ['prix_final' => $prix] : []));
                if (!empty($_POST['acompte_recu']) && in_array($k['paiement'], ['aucun', 'acompte_demande'], true)) commande_paiement($id, 'acompte_recu', choix('moyen', MOYENS, ''), (int)$k['acompte'] ?: null, (string)$k['facture']);
                flash('ok', 'Commande confirmée : envoyez la confirmation au client et ajoutez le retrait à votre agenda.');
                break;
            case 'remise':
                $prix = centimes(texte('prix_final', 12));
                commande_statut($id, 'remise', $prix !== null ? ['prix_final' => $prix] : []);
                $p = choix('paiement', PAIEMENTS, 'paye');
                commande_paiement($id, $p, choix('moyen', MOYENS, ''), null, une_ligne(texte('facture', 40)));
                flash('ok', 'Commande remise. Pensez à demander un avis Google si le client était content.');
                break;
            case 'statut':
                $s = choix('statut', STATUTS, '');
                if ($s !== '') commande_statut($id, $s, ['motif' => une_ligne(texte('motif', 300))]);
                flash('ok', 'Étape changée : ' . (STATUTS[$s] ?? '') . '.');
                break;
            case 'annuler':
                $qui = choix('qui', ['client' => 'Annulée par le client', 'madamecho' => 'Annulée par Madame Cho'], 'client');
                $motif = ($qui === 'client' ? 'par le client' : 'par Madame Cho') . (($m = une_ligne(texte('motif', 300))) !== '' ? ', ' . $m : '');
                commande_statut($id, 'annulee', ['motif' => $motif]);
                $sort = choix('acompte_sort', ['' => '', 'acquis' => 'acquis', 'rembourse' => 'remboursé'], '');
                if ($sort === 'rembourse') commande_paiement($id, 'rembourse', (string)$k['moyen'], null, (string)$k['facture']);
                elseif ($sort === 'acquis') commentaire('Acompte de ' . euros((int)$k['acompte']) . ' acquis (annulation moins de 24 h avant le retrait).', $id, (int)$k['client_id']);
                flash('ok', 'Commande annulée.');
                break;
            case 'paiement':
                commande_paiement($id, choix('paiement', PAIEMENTS, 'aucun'), choix('moyen', MOYENS, ''), centimes(texte('acompte', 12)) ?? 0, une_ligne(texte('facture', 40)));
                $pf = texte('prix_final', 12);
                if ($pf !== '') commande_modifier($id, ['prix_final' => centimes($pf)]);
                flash('ok', 'Paiement enregistré.');
                break;
            case 'probleme':
                commande_probleme($id, true, texte('note', 1000));
                flash('ok', 'Problème signalé : il reste visible partout jusqu\'à ce qu\'il soit résolu.');
                break;
            case 'resolu':
                commande_probleme($id, false, texte('note', 1000));
                flash('ok', 'Problème résolu.');
                break;
            case 'commentaire':
                commentaire(texte('texte', 2000), $id, (int)$k['client_id']);
                flash('ok', 'Commentaire ajouté.');
                break;
            case 'avis_demande':
                q("UPDATE clients SET avis_demande_le = ?, maj_le = ? WHERE id = ?", [aujourdhui(), maintenant(), $k['client_id']]);
                evenement('avis', 'Avis Google demandé', ['commande' => $id, 'client' => (int)$k['client_id']]);
                flash('ok', 'Demande d\'avis notée sur la fiche du client.');
                break;
            case 'avis_recu':
                q("UPDATE clients SET avis_recu_le = ?, maj_le = ? WHERE id = ?", [aujourdhui(), maintenant(), $k['client_id']]);
                evenement('avis', 'Avis Google reçu', ['commande' => $id, 'client' => (int)$k['client_id']]);
                flash('ok', 'Avis reçu noté.');
                break;
            case 'dupliquer':
                $n = commande_creer(['client_id' => (int)$k['client_id'], 'canal' => $k['canal'], 'type' => $k['type'], 'lignes' => $k['lignes_tab'],
                                     'perso' => $k['perso'], 'besoin' => $k['besoin'], 'retrait_moment' => $k['retrait_moment'], 'origine' => 'Copie de ' . $k['reference']]);
                flash('ok', 'Copie créée : choisissez la date de retrait.');
                rediriger('commande-edition', ['id' => $n]);
        }
        rediriger('commande', ['id' => $id]);
    }
    $k = commande($id);
    $client = client((int)$k['client_id']);
    $nom = commande_client_nom($k);
    $chips = chip_statut($k['statut'], false) . chip_paiement($k['paiement'], (int)$k['acompte']) . ($k['probleme'] ? drapeau() : '');
    $c = titre_page('<span translate="no">' . h($nom) . '</span>', h($k['reference']) . ' · ' . h(CANAUX[$k['canal']] ?? $k['canal']) . ' · ' . h(TYPES_COMMANDE[$k['type']] ?? '') . ' · reçue le ' . h(date_fr($k['recue_le'], 'heure')) . '<span class="chips">' . $chips . '</span>', '', u('commandes'));
    $c .= etape_suivante($k);
    // Client
    $tel = $k['client_tel'] !== '' ? '<a href="tel:' . h(preg_replace('/[^0-9+]/', '', $k['client_tel'])) . '">' . h($k['client_tel']) . '</a>' : '';
    $mail = $k['client_email'] !== '' ? '<a href="mailto:' . h($k['client_email']) . '">' . h($k['client_email']) . '</a>' : '';
    $ch = client_chiffres((int)$k['client_id']);
    $c .= '<div class="grille-2">';
    $c .= bloc('Client', definition(['Nom' => '<a href="' . h(u('client', ['id' => $k['client_id']])) . '" translate="no">' . h($nom) . '</a> <span class="petit">' . h($k['client_numero']) . '</span>',
                                     'Email' => $mail, 'Téléphone' => $tel, 'Ville' => $k['client_ville'] !== '' ? '<span translate="no">' . h($k['client_ville']) . '</span>' : '',
                                     'Historique' => h($ch['remises'] . ' commande(s) remise(s)' . ($ch['total'] ? ' · ' . euros($ch['total']) : '') . ($ch['derniere'] ? ' · dernière le ' . date_fr($ch['derniere']) : ''))])
                     . '<p>' . lien_bouton(u('client', ['id' => $k['client_id']]), 'Voir la fiche du client', 'discret') . '</p>');
    $close = !$k['retrait_date'] && in_array($k['statut'], ['remise', 'annulee', 'sans_suite'], true);
    $c .= bloc(($k['mode'] ?? 'retrait') === 'livraison' ? 'Livraison' : 'Retrait', $close ? definition(['Quand' => h(retrait_texte($k))]) : definition(['Quand' => h(retrait_texte($k)), 'Moment demandé' => h(MOMENTS[$k['retrait_moment']] ?? ''), 'Heure convenue' => h($k['retrait_heure'] !== '' ? str_replace(':', ' h ', $k['retrait_heure']) : 'à convenir'),
                                     'Où' => h(($k['mode'] ?? 'retrait') === 'livraison' ? 'Livraison' : 'Au 4 avenue du Château, 92190 Meudon')])
                      . agenda_boutons($k));
    $c .= '</div>';
    // Contenu
    $rows = ''; $V = variantes(); $carte = true;
    foreach ($k['lignes_tab'] as $l) {
        if (!isset($V[$l['v'] ?? ''])) $carte = false;
        $rows .= '<tr><td>' . (int)$l['q'] . ' ×</td><td><b translate="no">' . h($l['nom']) . '</b>' . (($l['libelle'] ?? '') !== '' ? '<br><span class="petit" translate="no">' . h($l['libelle']) . '</span>' : '')
               . (($l['plaque'] ?? '') !== '' ? '<br><span class="plaque">Plaque : « ' . h($l['plaque']) . ' »</span>' : '') . '</td><td class="nombre">' . h(euros((int)$l['prix'] * (int)$l['q'])) . '</td></tr>';
    }
    $contenu = $rows !== '' ? '<table class="tableau lignes-cmd"><tbody>' . $rows . '</tbody><tfoot><tr><td></td><td>' . ($carte ? 'Total indicatif (prix de la carte)' : 'Total des lignes') . '</td><td class="nombre">' . h(euros((int)$k['total_indicatif'])) . '</td></tr></tfoot></table>' : '';
    if ($k['perso'] !== '') $contenu .= '<div class="texte-client"><h3>Gâteau personnalisé</h3><p translate="no" lang="fr">' . nl2br(h($k['perso'])) . '</p></div>';
    if ($k['besoin'] !== '') $contenu .= '<div class="texte-client"><h3>Demande d\'un professionnel</h3><p translate="no" lang="fr">' . nl2br(h($k['besoin'])) . '</p></div>';
    if ($contenu === '') $contenu = '<p class="vide">Aucun gâteau noté.</p>';
    $c .= bloc('Contenu', $contenu . '<p>' . lien_bouton(u('commande-edition', ['id' => $id]), 'Modifier la commande', 'discret') . '</p>');
    // Montants et paiement
    $reste = montant_commande($k) - ($k['paiement'] === 'acompte_recu' ? (int)$k['acompte'] : 0);
    $c .= bloc('Montant et paiement', definition(['Prix final' => h($k['prix_final'] !== null ? euros((int)$k['prix_final']) . ' TTC' : 'pas encore fixé (indicatif : ' . euros((int)$k['total_indicatif']) . ')'),
                                     'Acompte' => h((int)$k['acompte'] ? euros((int)$k['acompte']) : ''), 'Paiement' => h(PAIEMENTS[$k['paiement']] ?? '') . ($k['moyen'] !== '' ? ' · ' . h(MOYENS[$k['moyen']] ?? '') : ''),
                                     'Reste à payer' => in_array($k['paiement'], ['paye', 'rembourse'], true) ? '' : h(euros($reste)),
                                     'Facture Qonto' => h($k['facture'])])
        . '<details class="replie"><summary>Modifier le paiement</summary>'
        . formulaire('paiement', '<div class="ligne-champs">' . liste_choix('paiement', 'Paiement', PAIEMENTS, $k['paiement']) . liste_choix('moyen', 'Moyen', MOYENS, $k['moyen']) . '</div>'
            . '<div class="ligne-champs">' . champ('prix_final', 'Prix final (€ TTC)', $k['prix_final'] !== null ? number_format((int)$k['prix_final'] / 100, 2, ',', '') : '', ['inputmode' => 'decimal'])
            . champ('acompte', 'Acompte (€)', (int)$k['acompte'] ? number_format((int)$k['acompte'] / 100, 2, ',', '') : '', ['inputmode' => 'decimal']) . '</div>'
            . champ('facture', 'N° de facture Qonto', $k['facture'], ['placeholder' => 'F-2026-013']) . bouton('Enregistrer', 'secondaire'))
        . '</details>');
    // Problème
    if ($k['probleme']) {
        $c .= bloc(icone('alerte') . ' Problème', '<p class="probleme-note"' . ($k['probleme_note'] !== '' ? ' translate="no" lang="fr"' : '') . '>' . nl2br(h($k['probleme_note'] !== '' ? $k['probleme_note'] : 'Problème signalé.')) . '</p>'
            . formulaire('resolu', zone('note', 'Comment il a été résolu', '', ['rows' => '2', 'maxlength' => '1000']) . bouton('Marquer résolu', 'secondaire')), 'alerte');
    }
    // Historique et commentaires
    $c .= bloc('Commentaires et historique', formulaire_commentaire() . fil_historique(historique($id, null)));
    // Autres actions
    $autres = '<div class="actions-colonne">'
        . (!$k['probleme'] ? '<details class="replie"><summary>' . icone('alerte') . 'Signaler un problème</summary>' . formulaire('probleme', zone('note', 'Le problème', '', ['rows' => '2', 'maxlength' => '1000', 'required' => true], 'Retard, réclamation, paiement… Une allergie ou un malaise d\'un client : prévenez aussi tout de suite Youngmi (R-39), sans noter de détail médical.') . bouton('Signaler', 'secondaire')) . '</details>' : '')
        . (in_array($k['statut'], STATUTS_EN_COURS, true) ? '<details class="replie"><summary>Annuler la commande</summary>' . formulaire('annuler',
              liste_choix('qui', 'Annulée par', ['client' => 'le client', 'madamecho' => 'Madame Cho']) . champ('motif', 'Motif (facultatif)', '', ['maxlength' => '300'])
            . ((int)$k['acompte'] && $k['paiement'] === 'acompte_recu' ? liste_choix('acompte_sort', 'L\'acompte', ['' => 'Je décide plus tard', 'acquis' => 'Reste acquis (moins de 24 h avant)', 'rembourse' => 'Est remboursé']) : '')
            . bouton('Annuler la commande', 'danger'), ['data-confirmer' => 'Annuler cette commande ?']) . '</details>' : '')
        . '<details class="replie"><summary>Changer l\'étape à la main</summary>' . formulaire('statut', liste_choix('statut', 'Étape', STATUTS, $k['statut']) . champ('motif', 'Précision (facultatif)', '', ['maxlength' => '300']) . bouton('Changer', 'secondaire')) . '</details>'
        . formulaire('dupliquer', bouton('Faire une copie (client régulier)', 'discret'))
        . '</div>';
    $c .= bloc('Autres actions', $autres);
    mise_en_page($k['reference'], $c, 'commandes', 'page-commande');
}

function agenda_boutons(array $k): string {
    if (!$k['retrait_date'] || in_array($k['statut'], ['annulee', 'sans_suite', 'remise'], true)) return '';
    $g = lien_agenda_google($k);
    return '<div class="actions">' . ($g ? lien_bouton($g, icone('agenda') . 'Ajouter à Google Agenda', 'discret', ['target' => '_blank', 'rel' => 'noopener']) : '')
         . lien_bouton(u('agenda', ['id' => $k['id']]), 'Fichier agenda (.ics)', 'discret') . '</div>';
}

/** Le grand cadre « prochaine étape » d'une commande, selon où elle en est. */
function etape_suivante(array $k): string {
    $id = (int)$k['id'];
    $email = (string)$k['client_email'];
    $prixDefaut = number_format(montant_commande($k) / 100, 2, ',', '');
    switch ($k['statut']) {
        case 'demande':
            $h = heures_depuis($k['recue_le']);
            $quand = $h >= GESTION_RETARD_HEURES ? '<span class="retard">Reçue il y a ' . $h . ' h : le site promet une réponse sous 24 h.</span>' : 'À répondre avant le ' . h(date_fr(date('Y-m-d H:i:s', strtotime($k['recue_le']) + 86400), 'heure')) . '.';
            $acompteDefaut = $k['perso'] !== '' ? number_format(round(montant_commande($k) * 0.3 / 100, 2), 2, ',', '') : '';
            $gmail = 'https://mail.google.com/mail/?authuser=contact@madamecho.com#search/' . rawurlencode($k['reference']);
            return '<section class="etape"><h2>Prochaine étape : envoyer une proposition</h2><p>' . $quand . '</p>'
                 . '<p class="aide">Vérifiez vos disponibilités, puis répondez au client par écrit : prix TTC total, jour et heure du retrait, acompte s\'il y a une personnalisation.</p>'
                 . bloc_email('email-prop', 'Préparer la proposition', email_proposition($k), $email)
                 . '<div class="actions">' . lien_bouton($gmail, icone('email') . 'Retrouver la demande dans Gmail', 'discret', ['target' => '_blank', 'rel' => 'noopener']) . '</div>'
                 . formulaire('proposition', '<div class="ligne-champs">' . champ('prix_final', 'Prix proposé (€ TTC)', $prixDefaut, ['inputmode' => 'decimal'])
                      . champ('acompte', 'Acompte demandé (€)', $acompteDefaut, ['inputmode' => 'decimal'], 'Vide s\'il n\'y en a pas.') . '</div>'
                      . bouton('J\'ai envoyé la proposition', 'principal'), ['class' => 'form-etape'])
                 . formulaire('statut', '<input type="hidden" name="statut" value="sans_suite">' . bouton('Classer sans suite', 'discret'), ['class' => 'form-secondaire'])
                 . '</section>';
        case 'proposition':
            $j = jours_depuis($k['dernier_echange']);
            return '<section class="etape"><h2>Le client a accepté ?</h2>'
                 . '<p>Proposition envoyée ' . h(date_fr($k['dernier_echange'], 'relative')) . ($j >= GESTION_SANS_SUITE_JOURS ? ' : <span class="dormant">sans nouvelles depuis ' . $j . ' jours, une relance ou « sans suite » ?</span>' : '.') . '</p>'
                 . formulaire('confirmer', '<div class="ligne-champs">' . champ('retrait_date', 'Jour du retrait', (string)$k['retrait_date'], ['type' => 'date', 'required' => true])
                      . champ('retrait_heure', 'Heure convenue', $k['retrait_heure'], ['type' => 'time', 'step' => '900']) . '</div>'
                      . champ('prix_final', 'Prix final (€ TTC)', $prixDefaut, ['inputmode' => 'decimal'])
                      . ((int)$k['acompte'] ? case_a_cocher('acompte_recu', 'Acompte de ' . h(euros((int)$k['acompte'])) . ' reçu', $k['paiement'] === 'acompte_recu') . liste_choix('moyen', 'Moyen de l\'acompte', MOYENS, $k['moyen']) : '')
                      . bouton('Confirmer la commande', 'principal'), ['class' => 'form-etape'])
                 . formulaire('statut', '<input type="hidden" name="statut" value="sans_suite">' . bouton('Classer sans suite', 'discret'), ['class' => 'form-secondaire'])
                 . '</section>';
        case 'confirmee':
            $passe = $k['retrait_date'] && $k['retrait_date'] < aujourdhui();
            return '<section class="etape' . ($passe ? ' etape-alerte' : '') . '"><h2>' . ($passe ? 'Retrait passé : la commande a été remise ?' : 'Retrait programmé : ' . h(retrait_texte($k))) . '</h2>'
                 . bloc_email('email-conf', 'Envoyer la confirmation au client', email_confirmation($k), $email)
                 . agenda_boutons($k)
                 . formulaire('remise', '<div class="ligne-champs">' . champ('prix_final', 'Prix final (€ TTC)', $prixDefaut, ['inputmode' => 'decimal'])
                      . liste_choix('moyen', 'Payé par', MOYENS, $k['moyen'] !== '' ? $k['moyen'] : 'carte') . '</div>'
                      . liste_choix('paiement', 'Paiement', ['paye' => 'Payé', 'aucun' => 'Pas encore payé'], 'paye')
                      . champ('facture', 'N° de facture Qonto (si facture)', $k['facture'], ['placeholder' => 'F-2026-013'])
                      . bouton('Commande remise au client', 'principal'), ['class' => 'form-etape'])
                 . '</section>';
        case 'remise':
            $c = client((int)$k['client_id']);
            $lien = (string)parametre('lien_avis', '');
            $avis = '';
            if ($c && $c['statut'] !== 'efface') {
                if ($c['avis_recu_le']) $avis = '<p>Avis Google reçu le ' . h(date_fr($c['avis_recu_le'], 'longue')) . '. Merci !</p>';
                elseif ($c['avis_demande_le']) $avis = '<p>Avis Google demandé le ' . h(date_fr($c['avis_demande_le'], 'longue')) . '.</p>' . formulaire('avis_recu', bouton(icone('avis') . 'L\'avis est arrivé', 'secondaire'));
                elseif ($lien === '') $avis = '<p class="aide">' . fr_ko('Pour demander un avis Google, ajoutez d\'abord le lien d\'avis de la fiche Google dans <a href="' . h(u('reglages')) . '#avis">Réglages</a>.',
                    '구글 리뷰를 부탁하려면 먼저 <a href="' . h(u('reglages')) . '#avis">설정</a>에 구글 프로필의 리뷰 링크를 넣어 주세요.') . '</p>';
                else $avis = '<p class="aide">Seulement à un client content, sans contrepartie et sans demander de note (règles de Google).</p>' . bloc_email('email-avis', 'Demander un avis Google', email_avis($k, $lien), $email)
                            . formulaire('avis_demande', bouton(icone('avis') . 'J\'ai demandé un avis', 'secondaire'));
            }
            return '<section class="etape etape-finie"><h2>Remise le ' . h(date_fr($k['remise_le'] ?? $k['maj_le'], 'longue')) . '</h2>' . $avis . '</section>';
        default:
            return '<section class="etape etape-finie"><h2>' . h(STATUTS[$k['statut']]) . ($k['statut'] === 'annulee' && $k['annulee_motif'] !== '' ? ' ' . h($k['annulee_motif']) : '') . '</h2>'
                 . formulaire('statut', '<input type="hidden" name="statut" value="demande">' . bouton('Rouvrir la demande', 'secondaire')) . '</section>';
    }
}

/* ---------- Nouvelle commande, modifier une commande ---------- */
function page_commande_edition(): void {
    $id = entier('id', 1);
    $k = $id ? commande($id) : null;
    if ($id && !$k) rediriger('commandes');
    $clientFixe = $k ? client((int)$k['client_id']) : (entier('client', 1) ? client(entier('client', 1)) : null);
    $erreurs = [];
    $V = variantes();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        // Le client
        $cid = $clientFixe ? (int)$clientFixe['id'] : 0;
        if (!$k) {
            $ref = une_ligne(texte('client_ref', 120));
            if (!$cid && preg_match('/CL-\d{4,}/', $ref, $m)) { $cid = (int)val("SELECT id FROM clients WHERE numero = ? AND statut <> 'efface'", [$m[0]]); if (!$cid) $erreurs[] = 'Le client ' . $m[0] . ' n\'existe pas.'; }
            if (!$cid && !$erreurs) {
                $nc = ['type' => choix('c_type', TYPES_CLIENT, 'particulier'), 'prenom' => une_ligne(texte('c_prenom', 60)), 'nom' => une_ligne(texte('c_nom', 80)),
                       'societe' => une_ligne(texte('c_societe', 100)), 'email' => mb_strtolower(une_ligne(texte('c_email', 120))), 'telephone' => une_ligne(texte('c_telephone', 30)),
                       'ville' => une_ligne(texte('c_ville', 60)), 'canal' => choix('canal', CANAUX, 'autre')];
                if ($nc['nom'] === '' && $nc['societe'] === '') $erreurs[] = 'Choisissez un client existant, ou indiquez au moins le nom du nouveau client.';
                if ($nc['email'] !== '' && !email_valide($nc['email'])) $erreurs[] = 'L\'email du client n\'est pas valide.';
                if ($nc['email'] !== '' && ($e = ligne("SELECT id, numero FROM clients WHERE email = ? AND statut <> 'efface'", [$nc['email']]))) $cid = (int)$e['id'];   // déjà connu : on le reprend
                elseif (!$erreurs) $nouveauClient = $nc;
            }
        }
        // Les gâteaux
        $lignes = [];
        foreach (is_array($_POST['lignes'] ?? null) ? $_POST['lignes'] : [] as $l) {
            if (!is_array($l)) continue;
            $v = is_string($l['v'] ?? null) ? $l['v'] : '';
            $q = is_string($l['q'] ?? null) && preg_match('/^\d{1,3}$/', $l['q']) ? (int)$l['q'] : 0;
            if ($v === '' || $q < 1) continue;
            if (!isset($V[$v])) {   // ligne reprise d'une ancienne commande (hors carte) : gardée telle quelle
                $ancien = null;
                foreach ($k['lignes_tab'] ?? [] as $x) if (($x['v'] ?? '') === $v || 'ancien:' . md5(json_encode($x)) === $v) $ancien = $x;
                if ($ancien) { $ancien['q'] = $q; $ancien['plaque'] = mb_substr(une_ligne((string)($l['plaque'] ?? '')), 0, 60); $lignes[] = $ancien; }
                continue;
            }
            $lignes[] = ['v' => $v, 'q' => $q, 'prix' => $V[$v]['prix'], 'plaque' => $V[$v]['plaque'] ? mb_substr(une_ligne(is_string($l['plaque'] ?? null) ? $l['plaque'] : ''), 0, 60) : ''];
        }
        $d = ['canal' => choix('canal', CANAUX, $k['canal'] ?? 'autre'), 'type' => choix('type', TYPES_COMMANDE, $k['type'] ?? 'carte'),
              'retrait_date' => date_valide(texte('retrait_date', 10)), 'retrait_moment' => choix('retrait_moment', ['' => ''] + MOMENTS, ''),
              'retrait_heure' => preg_match('/^\d{2}:\d{2}$/', texte('retrait_heure', 5)) ? texte('retrait_heure', 5) : '', 'mode' => choix('mode', MODES, 'retrait'),
              'perso' => texte('perso', 2000), 'besoin' => texte('besoin', 2000), 'lignes' => $lignes];
        if (!$lignes && $d['perso'] === '' && $d['besoin'] === '') $erreurs[] = 'Ajoutez au moins un gâteau, ou décrivez la demande.';
        if (!$erreurs) {
            if (!empty($nouveauClient)) $cid = client_creer($nouveauClient + ['premier_contact' => maintenant(), 'dernier_echange' => maintenant()]);
            $prix = centimes(texte('prix_final', 12));
            $acompte = centimes(texte('acompte', 12));
            if ($k) {
                commande_modifier($id, $d + ['prix_final' => $prix, 'acompte' => $acompte ?? 0]);
                flash('ok', 'Commande modifiée.');
            } else {
                $ref = strtoupper(une_ligne(texte('reference', 20)));
                $statut = choix('statut', STATUTS, 'demande');
                $id = commande_creer($d + ['client_id' => $cid, 'statut' => 'demande', 'origine' => 'Commande saisie à la main (' . (CANAUX[$d['canal']] ?? '') . ')']
                                     + (preg_match('/^MC-\d{6}-[0-9A-F]{4}$/', $ref) && !val("SELECT 1 FROM commandes WHERE reference = ?", [$ref]) ? ['reference' => $ref] : []));
                if ($prix !== null || $acompte) commande_modifier($id, ['prix_final' => $prix, 'acompte' => $acompte ?? 0]);
                if ($statut !== 'demande') commande_statut($id, $statut);
                if ($statut === 'remise') commande_paiement($id, choix('paiement', PAIEMENTS, 'paye'), choix('moyen', MOYENS, ''), null, '');
                $note = texte('note', 2000);
                if ($note !== '') commentaire($note, $id, $cid);
                flash('ok', 'Commande créée.');
            }
            rediriger('commande', ['id' => $id]);
        }
    }
    // Le formulaire
    $src = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : [];
    $val = fn(string $n, $def = '') => isset($src[$n]) && is_string($src[$n]) ? $src[$n] : $def;
    $titre = $k ? 'Modifier ' . $k['reference'] : 'Nouvelle commande';
    $c = titre_page(h($titre), $k ? '<span translate="no">' . h(commande_client_nom($k)) . '</span>' : 'Une demande reçue par Instagram, téléphone, email, sur un marché… ou une vente déjà faite', '', $k ? u('commande', ['id' => $k['id']]) : u('commandes'));
    if ($erreurs) $c .= '<div class="erreur" role="alert">' . implode('<br>', array_map(fn($e) => h($e), $erreurs)) . '</div>';
    $f = '';
    // Client
    if ($clientFixe) {
        $f .= bloc('Client', '<p><b translate="no">' . h(client_nom($clientFixe)) . '</b> <span class="petit">' . h($clientFixe['numero']) . '</span></p>');
    } else {
        $opts = '';
        foreach (clients_lister('', 'actifs') as $cl) $opts .= '<option value="' . h($cl['numero'] . ' · ' . client_nom($cl)) . '">';
        $f .= bloc('Client', champ('client_ref', 'Client déjà connu', $val('client_ref'), ['list' => 'liste-clients', 'placeholder' => 'Tapez un nom ou CL-…', 'autocomplete' => 'off']) . '<datalist id="liste-clients">' . $opts . '</datalist>'
            . '<details class="replie"' . ($val('c_nom') !== '' || $val('c_societe') !== '' ? ' open' : '') . '><summary>Ou un nouveau client</summary>'
            . liste_choix('c_type', 'Type', TYPES_CLIENT, $val('c_type', 'particulier'))
            . '<div class="ligne-champs">' . champ('c_prenom', 'Prénom', $val('c_prenom'), ['autocomplete' => 'off']) . champ('c_nom', 'Nom de famille', $val('c_nom'), ['autocomplete' => 'off']) . '</div>'
            . champ('c_societe', 'Entreprise ou restaurant', $val('c_societe'), ['autocomplete' => 'off'])
            . '<div class="ligne-champs">' . champ('c_email', 'Email', $val('c_email'), ['type' => 'email', 'autocomplete' => 'off']) . champ('c_telephone', 'Téléphone', $val('c_telephone'), ['type' => 'tel', 'autocomplete' => 'off']) . '</div>'
            . champ('c_ville', 'Ville', $val('c_ville'), ['autocomplete' => 'off']) . '</details>');
    }
    // Demande
    $f .= bloc('La demande', '<div class="ligne-champs">' . liste_choix('canal', 'Reçue par', CANAUX, $val('canal', $k['canal'] ?? (is_string($_GET['ref'] ?? null) ? 'site' : 'instagram')))
        . liste_choix('type', 'Type', TYPES_COMMANDE, $val('type', $k['type'] ?? 'carte')) . '</div>'
        . ($k ? '' : champ('reference', 'Référence MC-… (si elle existe déjà, sinon laissez vide)', $val('reference', is_string($_GET['ref'] ?? null) ? strtoupper($_GET['ref']) : ''), ['placeholder' => 'MC-261106-1A2B', 'pattern' => 'MC-[0-9]{6}-[0-9A-Fa-f]{4}'])));
    // Gâteaux
    $groupes = [];
    foreach ($V as $cle => $v) $groupes[$v['nom']][$cle] = $v['libelle'] . ' · ' . euros($v['prix']) . ($v['plaque'] ? '' : ' la pièce');
    $existantes = $k ? $k['lignes_tab'] : [];
    if ($src) $existantes = [];
    $rows = '';
    $i = 0;
    $ligne = function (int $i, string $v, string $q, string $plaque, array $extra = []) use ($groupes): string {
        $opts = ['' => '— Choisir un gâteau —'] + $extra + $groupes;
        return '<div class="ligne-gateau">' . liste_choix('lignes[' . $i . '][v]', 'Gâteau', $opts, $v, ['id' => 'l' . $i . 'v'])
             . champ('lignes[' . $i . '][q]', 'Quantité', $q, ['type' => 'number', 'min' => '1', 'max' => '999', 'inputmode' => 'numeric', 'id' => 'l' . $i . 'q'])
             . champ('lignes[' . $i . '][plaque]', 'Plaque en chocolat', $plaque, ['maxlength' => '60', 'id' => 'l' . $i . 'p', 'placeholder' => 'Prénom, message (gâteaux entiers)']) . '</div>';
    };
    foreach ($existantes as $l) {
        $v = (string)($l['v'] ?? '');
        $extra = [];
        if (!isset($V[$v])) { $v = 'ancien:' . md5(json_encode($l)); $extra = [$v => $l['nom'] . (($l['libelle'] ?? '') !== '' ? ', ' . $l['libelle'] : '') . ' (hors carte)']; }
        $rows .= $ligne($i++, $v, (string)$l['q'], (string)($l['plaque'] ?? ''), $extra);
    }
    if ($src && is_array($src['lignes'] ?? null)) foreach ($src['lignes'] as $l) if (is_array($l)) $rows .= $ligne($i++, (string)($l['v'] ?? ''), (string)($l['q'] ?? ''), (string)($l['plaque'] ?? ''));
    $vides = $i ? 0 : 1;
    for ($n = 0; $n < $vides; $n++) $rows .= $ligne($i++, '', '', '');
    $f .= bloc('Les gâteaux', '<div id="lignes" data-prochain="' . $i . '">' . $rows . '</div>'
        . '<template id="modele-ligne">' . $ligne(999, '', '', '') . '</template>'
        . '<button type="button" class="btn discret" data-ajouter-ligne="modele-ligne" data-cible="lignes">' . icone('ajouter') . 'Une ligne de plus</button>'
        . zone('perso', 'Gâteau personnalisé', $val('perso', $k['perso'] ?? ''), ['rows' => '3', 'maxlength' => '2000'], 'Nombre de personnes, saveurs, couleurs, décoration. Pas d\'information de santé : pour une allergie, seulement l\'ingrédient à éviter.')
        . zone('besoin', 'Demande d\'un professionnel', $val('besoin', $k['besoin'] ?? ''), ['rows' => '2', 'maxlength' => '2000']));
    // Retrait
    $f .= bloc('Le retrait', '<div class="ligne-champs">' . champ('retrait_date', 'Jour', $val('retrait_date', (string)($k['retrait_date'] ?? '')), ['type' => 'date'])
        . liste_choix('retrait_moment', 'Moment', ['' => '—'] + MOMENTS, $val('retrait_moment', $k['retrait_moment'] ?? '')) . '</div>'
        . '<div class="ligne-champs">' . champ('retrait_heure', 'Heure convenue', $val('retrait_heure', $k['retrait_heure'] ?? ''), ['type' => 'time', 'step' => '900'])
        . liste_choix('mode', 'Mode', MODES, $val('mode', $k['mode'] ?? 'retrait')) . '</div>');
    // Montants et étape
    $pf = $k && $k['prix_final'] !== null ? number_format((int)$k['prix_final'] / 100, 2, ',', '') : '';
    $ac = $k && (int)$k['acompte'] ? number_format((int)$k['acompte'] / 100, 2, ',', '') : '';
    $m = '<div class="ligne-champs">' . champ('prix_final', 'Prix final (€ TTC)', $val('prix_final', $pf), ['inputmode' => 'decimal'], 'Vide : le prix de la carte.') . champ('acompte', 'Acompte (€)', $val('acompte', $ac), ['inputmode' => 'decimal']) . '</div>';
    if (!$k) {
        $m .= liste_choix('statut', 'Où en est-elle ?', ['demande' => 'Demande reçue, à répondre', 'proposition' => 'Proposition déjà envoyée', 'confirmee' => 'Déjà confirmée', 'remise' => 'Déjà remise (vente faite)'], $val('statut', 'demande'))
            . '<div class="ligne-champs" data-si="f-statut=remise">' . liste_choix('paiement', 'Paiement', ['paye' => 'Payé', 'aucun' => 'Pas encore payé'], $val('paiement', 'paye')) . liste_choix('moyen', 'Payé par', MOYENS, $val('moyen', 'carte')) . '</div>'
            . zone('note', 'Commentaire (facultatif)', $val('note'), ['rows' => '2', 'maxlength' => '2000']);
    }
    $f .= bloc('Montant', $m);
    $f .= '<div class="barre-enregistrer">' . bouton($k ? 'Enregistrer les changements' : 'Créer la commande', 'principal large') . '</div>';
    $c .= formulaire('enregistrer', $f, ['class' => 'form-commande']);
    mise_en_page($titre, $c, 'commandes');
}

/* ---------- Fichier agenda (.ics) ---------- */
function page_agenda(): void {
    $k = commande_depuis_requete();
    if (!$k || !($c = creneau($k))) rediriger('commandes');
    $f = fn($s) => date('Ymd\THis', strtotime($s));
    $esc = fn($s) => str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], $s);
    $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Madame Cho//Gestion//FR\r\nCALSCALE:GREGORIAN\r\nBEGIN:VEVENT\r\n"
         . 'UID:' . strtolower($k['reference']) . '@madamecho.com' . "\r\n" . 'DTSTAMP:' . gmdate('Ymd\THis\Z') . "\r\n"
         . 'DTSTART;TZID=Europe/Paris:' . $f($c[0]) . "\r\n" . 'DTEND;TZID=Europe/Paris:' . $f($c[1]) . "\r\n"
         . 'SUMMARY:' . $esc(agenda_titre($k)) . "\r\n" . 'LOCATION:' . $esc('4 avenue du Château, 92190 Meudon') . "\r\n"
         . 'DESCRIPTION:' . $esc($k['reference'] . "\n" . lignes_email($k)) . "\r\n"
         . "BEGIN:VALARM\r\nTRIGGER:-P1D\r\nACTION:DISPLAY\r\nDESCRIPTION:" . $esc('Demain : ' . agenda_titre($k)) . "\r\nEND:VALARM\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="retrait-' . strtolower($k['reference']) . '.ics"');
    header('Cache-Control: no-store, private');
    echo $ics;
    exit;
}

/* ---------- Fiche de production ---------- */
function page_production(): void {
    $du = date_valide(texte('du', 10, $_GET)) ?? aujourdhui();
    $jours = choix('jours', ['1' => 'Ce jour', '2' => '2 jours', '7' => '7 jours'], '1');
    $au = date('Y-m-d', strtotime($du . ' +' . ((int)$jours - 1) . ' days'));
    $ks = lignes("SELECT k.*, c.nom AS client_nom, c.prenom AS client_prenom, c.societe AS client_societe, c.type AS client_type, c.statut AS client_statut, c.numero AS client_numero
                  FROM commandes k JOIN clients c ON c.id = k.client_id WHERE k.statut = 'confirmee' AND k.retrait_date BETWEEN ? AND ?
                  ORDER BY k.retrait_date, CASE WHEN k.retrait_heure <> '' THEN k.retrait_heure ELSE CASE k.retrait_moment WHEN 'matin' THEN '09' WHEN 'midi' THEN '12' WHEN 'apres-midi' THEN '14' ELSE '18' END END", [$du, $au]);
    $total = [];
    $parJour = [];
    foreach ($ks as $k) {
        $k['lignes_tab'] = json_decode($k['lignes'], true) ?: [];
        $parJour[$k['retrait_date']][] = $k;
        foreach ($k['lignes_tab'] as $l) {
            $cle = $l['nom'] . ' · ' . $l['libelle'];
            $total[$cle] = ($total[$cle] ?? 0) + (int)$l['q'];
        }
    }
    ksort($total);
    $c = titre_page('Fiche de production', 'Commandes confirmées, sans email ni téléphone : à imprimer pour l\'atelier',
        '<button type="button" class="btn discret" data-imprimer>' . icone('imprimer') . 'Imprimer</button>');
    $c .= '<form class="filtres" method="get" action=""><input type="hidden" name="p" value="production">'
        . champ('du', 'À partir du', $du, ['type' => 'date', 'data-auto' => true]) . liste_choix('jours', 'Sur', ['1' => 'Ce jour', '2' => '2 jours', '7' => '7 jours'], $jours, ['data-auto' => true])
        . '<noscript>' . bouton('Afficher', 'secondaire') . '</noscript></form>';
    $periode = $du === $au ? ucfirst(date_fr($du, 'jour')) : 'Du ' . date_fr($du, 'jour') . ' au ' . date_fr($au, 'jour');
    $c .= '<h2 class="imprime-titre">' . h($periode) . '</h2>';
    if (!$ks) { $c .= '<p class="vide">Aucune commande confirmée sur cette période.</p>'; mise_en_page('Production', $c, 'production', 'page-production'); }
    $t = '';
    foreach ($total as $l => $q) $t .= '<tr><td class="nombre">' . $q . '</td><td translate="no">' . h($l) . '</td></tr>';
    $c .= bloc('À préparer en tout', '<table class="tableau"><tbody>' . $t . '</tbody></table>');
    foreach ($parJour as $d => $liste) {
        $s = '';
        foreach ($liste as $k) {
            $lg = '';
            foreach ($k['lignes_tab'] as $l) $lg .= '<li><b>' . (int)$l['q'] . ' ×</b> <span translate="no">' . h($l['nom']) . (($l['libelle'] ?? '') !== '' ? ', ' . h($l['libelle']) : '') . '</span>' . (($l['plaque'] ?? '') !== '' ? ' · <span class="plaque">plaque « ' . h($l['plaque']) . ' »</span>' : '') . '</li>';
            if ($k['perso'] !== '') $lg .= '<li><b>Personnalisé :</b> <span translate="no" lang="fr">' . nl2br(h($k['perso'])) . '</span></li>';
            if ($k['besoin'] !== '') $lg .= '<li><b>Pro :</b> <span translate="no" lang="fr">' . nl2br(h($k['besoin'])) . '</span></li>';
            $reste = montant_commande($k) - ($k['paiement'] === 'acompte_recu' ? (int)$k['acompte'] : 0);
            $pay = $k['paiement'] === 'paye' ? 'payé' : 'à encaisser : ' . euros($reste);
            $quand = $k['retrait_heure'] !== '' ? str_replace(':', ' h ', $k['retrait_heure']) : (MOMENTS_COURTS[$k['retrait_moment']] ?? '');
            $s .= '<article class="fiche-prod"><header><span class="heure">' . h($quand) . '</span><b translate="no">' . h(commande_client_nom($k)) . '</b><span class="petit">' . h($k['reference']) . ' · ' . h($pay) . '</span></header><ul>' . $lg . '</ul>'
                . ($k['probleme'] ? '<p class="probleme-note">' . h(fr_ko('Problème : ', '문제: ')) . '<span translate="no" lang="fr">' . h($k['probleme_note']) . '</span></p>' : '') . '</article>';
        }
        $c .= bloc(h(ucfirst(date_fr($d, 'jour'))), $s, 'jour-prod');
    }
    mise_en_page('Production', $c, 'production', 'page-production');
}

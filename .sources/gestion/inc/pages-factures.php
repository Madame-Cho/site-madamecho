<?php
/*
 * Madame Cho : espace de gestion. Les écrans des factures des particuliers (spécification 003) : le cadre « Facture » de la commande
 * (aperçu, Émettre, PDF, remise au client, avoir), Plus › Factures (liste, phase de contrôle, vérification du journal, identité de
 * Madame Cho), le PDF, et la page publique du lien privé (madamecho.com/facture/…). Tout part au toucher d'une personne (R-01, FR-006).
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

require_once __DIR__ . '/factures.php';
require_once __DIR__ . '/qr.php';

const CORE_HANGUL = '/[\x{3131}-\x{318E}\x{AC00}-\x{D7A3}]/u';
const REMISE_NOTEES = ['sms' => 'lien envoyé par SMS.', 'whatsapp' => 'lien envoyé par WhatsApp.', 'qr' => 'QR code scanné par le client.', 'papier' => 'remise sur papier.', 'autre' => 'remise autrement.'];

/* ---------- Le mail de la facture (FR-007) ---------- */
function facture_accueil(array $k, array $f): string {
    $avoir = $f['genre'] === 'avoir';
    return salutation($k) . "\n\n" . ($avoir ? 'Voici l\'avoir ' . $f['numero'] . ' sur votre facture ' . ($f['d']['origine']['numero'] ?? '') . ', pour votre commande ' . $k['reference'] . '. Vous le trouverez en pièce jointe (PDF).'
                                             : 'Voici la facture de votre commande ' . $k['reference'] . '. Vous la trouverez en pièce jointe (PDF).');
}
/** Le mail : objet, texte, HTML (ou l'aperçu), dans le cadre des autres mails de Madame Cho, avec l'étiquette FACTURE ou AVOIR. */
function mail_facture(array $k, array $f, array $o = []): array {
    $apercu = !empty($o['apercu']);
    $S = mail_style($apercu); $p = mail_paragraphe($S);
    $avoir = $f['genre'] === 'avoir'; $d = $f['d'];
    $accueil = trim((string)($o['accueil'] ?? '')) !== '' ? (string)$o['accueil'] : facture_accueil($k, $f);
    $mot = trim((string)($o['mot'] ?? ''));
    $sujet = ($avoir ? 'Votre avoir ' : 'Votre facture ') . $f['numero'] . ' · Madame Cho';
    $F = MAIL_F; $TX = '#2B2623';
    $td = $S($F . 'font-size:14px;line-height:22px;color:' . $TX . ';padding:2px 0');
    $bord = $S($F . 'font-size:14px;color:' . $TX . ';border-top:1px solid #DDD3C6;padding:8px 0 0');
    $l = ''; $t = '';
    foreach ($d['lignes'] as $x) {
        $l .= '<tr><td' . $td . '>' . h($x['q'] . ' × ' . $x['designation']) . '</td><td align="right"' . $td . '>' . h(montant_facture($x['total_ttc'])) . '</td></tr>';
        $t .= $x['q'] . ' × ' . $x['designation'] . ' : ' . montant_facture($x['total_ttc']) . "\n";
    }
    $regle = $avoir ? '' : 'réglée le ' . date_facture($d['paiement']['le']) . ($d['paiement']['moyen'] !== '' ? ' par ' . $d['paiement']['moyen'] : '');
    $l .= '<tr><td' . $bord . '><b>Total TTC</b>' . ($regle !== '' ? ', ' . h($regle) : '') . '</td><td align="right"' . $bord . '><b>' . h(montant_facture((int)$d['totaux']['ttc'])) . '</b></td></tr>';
    $html = texte_mail_html($accueil, $p, 'margin-bottom:14px')
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"' . $S('background:#F3EDE4;border-radius:6px;margin-top:6px') . '><tr><td' . $S('padding:18px 20px') . '>'
        . '<p' . $S($F . 'font-size:17px;line-height:24px;color:#3B1D0E;font-weight:bold;margin:0') . '>' . h(($avoir ? 'Avoir n° ' : 'Facture n° ') . $f['numero']) . '</p>'
        . '<p' . $S($F . 'font-size:14px;line-height:21px;color:' . $TX . ';margin:2px 0 14px') . '>' . h('Commande ' . $d['commande']['reference'] . ' · ' . date_facture($f['emise_le'])) . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $l . '</table></td></tr></table>'
        . ($mot !== '' ? texte_mail_html($mot, $p, 'margin-top:20px') : '');
    $lien = facture_lien_actif($f) ? facture_lien($f) : '';
    if ($lien !== '') $html .= $p('Vous pouvez aussi la retrouver ici, pendant un an : <a href="' . h($lien) . '"' . $S('color:#6E2A10;font-weight:bold') . '>votre ' . ($avoir ? 'avoir' : 'facture') . ' en ligne</a>.', 'margin-top:20px');
    $html .= $p('Merci et à bientôt,<br>' . mail_signature_nom(), 'margin-top:22px');
    $texte = trim($accueil) . "\n\n" . ($avoir ? 'Avoir n° ' : 'Facture n° ') . $f['numero'] . "\n" . $t . 'Total TTC : ' . montant_facture((int)$d['totaux']['ttc']) . ($regle !== '' ? ', ' . $regle : '') . "\n"
           . ($mot !== '' ? "\n" . $mot . "\n" : '') . ($lien !== '' ? "\nEn ligne, pendant un an : " . $lien . "\n" : '') . "\nMerci et à bientôt,\n" . signature();
    return ['sujet' => $sujet, 'corps' => $texte, 'html' => mail_cadre($sujet, ($avoir ? 'Avoir' : 'Facture') . ' · ' . $f['numero'], $html, $S, $apercu), 'type' => 'facture'];
}
/** Envoie la facture (ou l'avoir) au client, PDF joint, au toucher de Youngmi (R-01) ; note la remise ou l'échec (FR-009). */
function envoyer_mail_facture(array $k, array $f, array $o = []): bool {
    $a = (string)$k['client_email'];
    if (!email_valide($a)) return false;
    $m = mail_facture($k, $f, $o);
    $verrou = 'envoi_facture_' . (int)$f['id'];
    q("DELETE FROM parametres WHERE cle LIKE 'envoi\\_%' ESCAPE '\\' AND CAST(valeur AS INTEGER) < ?", [time() - 120]);
    $st = db()->prepare("INSERT OR IGNORE INTO parametres (cle, valeur) VALUES (?, ?)");
    $st->execute([$verrou, (string)time()]);
    if ($st->rowCount() === 0) { $GLOBALS['mc_doublon'] = true; return true; }
    $pdf = (string)(facture((int)$f['id'], true)['pdf'] ?? '');
    $mid = envoyer_email_client($a, $m['sujet'], $m['corps'], $m['html'], mail_refs((int)$k['id']), [['nom' => $f['numero'] . '.pdf', 'type' => 'application/pdf', 'contenu' => $pdf]]);
    if ($mid === '') { q("DELETE FROM parametres WHERE cle = ?", [$verrou]); facture_remise_noter((int)$f['id'], 'email', $a, '', false); return false; }
    evenement('email_client', 'Email envoyé à ' . $a . ' (' . ($f['genre'] === 'avoir' ? 'avoir' : 'facture') . ') : « ' . $m['sujet'] . ' »', ['commande' => (int)$k['id'], 'client' => (int)$k['client_id']],
              'type:facture mid:' . $mid, $m['corps']);
    facture_remise_noter((int)$f['id'], 'email', $a, $mid);
    $now = maintenant();
    q("UPDATE commandes SET dernier_echange = ?, maj_le = ? WHERE id = ?", [$now, $now, (int)$k['id']]);
    client_echange((int)$k['client_id'], $now);
    return true;
}
/** Le texte court pour un SMS ou WhatsApp, avec le lien privé. */
function facture_sms(array $k, array $f): string {
    $avoir = $f['genre'] === 'avoir';
    return 'Bonjour, voici ' . ($avoir ? 'l\'avoir' : 'la facture') . ' de votre commande Madame Cho ' . $k['reference'] . ' : ' . facture_lien($f) . "\nMerci et à bientôt ! " . (trim(explode("\n", trim(signature()))[0] ?? '') ?: 'Youngmi');
}

/* ---------- Le cadre « Facture » de la commande ---------- */
function bloc_facture(array $k): string {
    $e = facture_etat($k);
    $id = (int)$k['id'];
    // Une facture seulement si le client la demande (décision d'Olivier du 09/11/2026) : sur le site, il coche « Je souhaite une facture » ;
    // par téléphone ou ailleurs, Youngmi le lui demande et le note ici, avec son adresse
    $sans = formulaire('facture_sans', '<div class="actions">' . bouton('Finalement, pas de facture', 'discret') . '</div>', ['class' => 'form-ligne']);
    switch ($e['etat']) {
        case 'pro': case 'aucune': return '';
        case 'non_demandee':
            return bloc('Facture', '<p class="aide">Le client n\'a pas demandé de facture. S\'il en veut une, notez-le avec son adresse postale : la facture se préparera quand la commande sera payée et remise.</p>'
                . formulaire('facture_demander', champ('adresse', 'Adresse postale du client', (string)($k['client_adresse'] ?? ''), ['maxlength' => '200', 'autocomplete' => 'off', 'lang' => 'fr'],
                                                     'Numéro, rue, code postal et ville. Vous pourrez aussi l\'écrire au moment d\'émettre.')
                                                . '<div class="actions">' . bouton(icone('valider') . 'Le client veut une facture', 'secondaire') . '</div>', ['class' => 'form-facture']), '', 'facture');
        case 'pas_payee':
            return bloc('Facture', '<p class="aide"><b>Facture demandée.</b> <span>La facture se prépare toute seule quand la commande est notée « Payé » et remise au client. Vous la relirez avant de l\'émettre.</span></p>' . $sans, '', 'facture');
        case 'pas_remise':
            return bloc('Facture', '<p class="aide"><b>Facture demandée.</b> <span>Payée : la facture sera prête dès que la commande sera notée « Remise » (elle porte la date de la vente).</span></p>' . $sans, '', 'facture');
        case 'a_emettre':
            $d = facture_donnees($k);
            $manque = $e['manque'] ? '<div class="erreur" role="alert"><b>Avant d\'émettre :</b><br>' . implode('<br>', array_map(fn($m) => h(FACTURE_MANQUES[$m]), $e['manque'])) . '</div>' : '';
            $qonto = $e['qonto'] !== '' ? '<p class="erreur"><span>Une facture Qonto est déjà notée sur cette commande :</span> <b translate="no">' . h($e['qonto']) . '</b>. <span>Si elle concerne cette vente, n\'émettez pas une deuxième facture.</span></p>' : '';
            // Prénom, nom et adresse : obligatoires (réponse de l'expert-comptable du 09/11/2026). Un nom écrit en coréen se met en lettres
            // latines (facture en français, PDF aux polices standard) ; un nom incomplet sur la fiche se complète ici
            $nomFiche = facture_nom_client($k);
            $nomLatin = Pdf::ecrivable($nomFiche);
            $form = ($nomLatin && facture_nom_complet($nomFiche) ? ''
                    : '<p class="erreur"><span>' . ($nomLatin ? 'La fiche n\'a pas le prénom et le nom du client : écrivez-les ici, ou complétez sa fiche.'
                                                             : 'Le nom de ce client est écrit en coréen. La facture est en français : écrivez-le en lettres latines (par exemple « Kim Minji »).') . '</span></p>'
                    . champ('nom_facture', 'Prénom et nom sur la facture, en lettres latines', '', ['maxlength' => '120', 'autocomplete' => 'off', 'lang' => 'fr', 'required' => true]))
                  . champ('adresse', 'Adresse postale du client', (string)($k['client_adresse'] ?? ''), ['maxlength' => '200', 'autocomplete' => 'off', 'lang' => 'fr', 'required' => true],
                          'Obligatoire sur la facture : numéro, rue, code postal et ville. Elle est gardée sur sa fiche pour ses prochaines factures.')
                  . ($e['qonto'] !== '' ? case_a_cocher('doublon_ok', '<span>Je confirme : la facture Qonto notée est une autre facture, ce n\'est pas un doublon</span> (<b translate="no">' . h($e['qonto']) . '</b>)') : '')
                  . '<input type="hidden" name="empreinte" value="' . h(facture_empreinte_apercu($d)) . '">'
                  . '<div class="actions">' . bouton(icone('valider') . 'Émettre la facture', 'principal', $e['manque'] ? ['disabled' => true] : []) . '</div>';
            // Après un avoir total : la facture annulée et son avoir restent visibles ici (audit n° 6, constat 11)
            $avant = '';
            if ($e['avoir']) {
                foreach (factures_de_commande($id) as $x) $avant .= '<li><span>' . ($x['genre'] === 'avoir' ? 'Avoir' : 'Facture') . '</span> <b translate="no">' . h($x['numero']) . '</b> · <span translate="no">'
                    . h(montant_facture((int)$x['total_ttc'])) . '</span> · <span>' . h(date_fr($x['emise_le'], 'courte')) . '</span> · <a href="' . h(u('facture-pdf', ['id' => (int)$x['id']])) . '" target="_blank" rel="noopener">PDF</a></li>';
                $avant = '<p class="aide">Déjà émis pour cette commande :</p><ul class="remises">' . $avant . '</ul>';
            }
            return bloc('Facture' . ($e['avoir'] ? ' (nouvelle, après l\'avoir)' : ' à émettre'),
                $avant . '<p class="aide">Relisez l\'aperçu. Une fois émise, la facture reçoit son numéro et ne se modifie plus : une erreur se corrige par un avoir.</p>'
                . '<p class="aide"><span>Un nom mal écrit ?</span> <span>Avant d\'émettre,</span> <a href="' . h(u('client-edition', ['id' => (int)$k['client_id']])) . '">corrigez la fiche du client</a>.</p>'
                . $manque . $qonto . facture_apercu_html($d)
                . formulaire('facture_emettre', $form, ['class' => 'form-facture', 'data-confirmer' => 'Émettre la facture ? Elle recevra son numéro et ne pourra plus être modifiée.'])
                . ($e['avoir'] ? '' : $sans), 'a-faire', 'facture');
    }
    // Émise
    $f = $e['facture'];
    $c = '';
    if ($e['a_corriger']) {
        $maintenant = in_array($k['statut'], ['annulee', 'sans_suite'], true) || $k['paiement'] === 'rembourse' ? 0 : montant_commande($k);
        $c .= '<div class="erreur" role="alert">' . ($maintenant === 0 ? '<p>La commande est annulée ou remboursée : faites un avoir qui annule toute la facture (ci-dessous).</p>'
            : '<p>La facture et la commande n\'ont plus le même montant.</p><p><span>Facture, avoirs déduits</span> : <b translate="no">' . h(montant_facture($e['reste'])) . '</b> · <span>Commande</span> : <b translate="no">' . h(montant_facture($maintenant)) . '</b></p>'
              . '<p>Si la commande est juste, faites un avoir puis une nouvelle facture. Si c\'est la facture qui est juste (geste commercial, avoir déjà fait), alignez la commande :</p>'
              . formulaire('facture_aligner', '<input type="hidden" name="facture" value="' . (int)$f['id'] . '">' . bouton('<span>Mettre le prix de la commande à</span> <b translate="no">' . h(montant_facture($e['reste'])) . '</b>', 'secondaire'),
                  ['data-confirmer' => 'Changer le prix final de la commande ?'])) . '</div>';
    }
    $c .= bloc_document($k, $f);
    foreach (facture_avoirs((int)$f['id']) as $av) { $av['d'] = json_decode($av['donnees'], true) ?: []; $c .= '<div class="doc-avoir">' . bloc_document($k, $av) . '</div>'; }
    // Avoir (FR-005)
    if ($e['reste'] > 0) $c .= '<details class="replie"><summary>' . icone('retour') . 'Corriger : faire un avoir</summary>'
        . '<p class="aide">L\'avoir annule tout ou partie de la facture, avec sa raison. Il a son propre numéro et se remet au client comme la facture. Après un avoir total, une nouvelle facture peut se faire.</p>'
        . formulaire('facture_avoir', '<input type="hidden" name="facture" value="' . (int)$f['id'] . '">'
            . liste_choix('mode', 'L\'avoir', ['total' => 'Annule toute la facture (' . montant_facture($e['reste']) . ')', 'partiel' => 'Corrige une partie (montant ci-dessous)'])
            . champ('montant', 'Montant de la correction, TTC (si partiel)', '', ['inputmode' => 'decimal', 'placeholder' => '5,00'])
            . (count($rt = array_filter(facture_reste_par_taux($f), fn($v) => $v > 0)) > 1
                ? liste_choix('taux', 'Taux de TVA de la correction (si partiel)', ['' => 'Choisir'] + array_combine(array_map('strval', array_keys($rt)), array_map(fn($t, $v) => taux_facture((int)$t) . ' · ' . montant_facture($v), array_keys($rt), $rt)), '')
                : '')
            . champ('motif', 'Raison (écrite sur l\'avoir)', '', ['maxlength' => '200', 'required' => true, 'lang' => 'fr', 'placeholder' => 'Par exemple : erreur de quantité, geste commercial'])
            . bouton('Émettre l\'avoir', 'danger'), ['data-confirmer' => 'Émettre l\'avoir ? Il recevra son numéro et ne pourra plus être modifié.']) . '</details>';
    return bloc('Facture ' . '<span translate="no">' . h($f['numero']) . '</span>', $c, $e['a_corriger'] ? 'alerte' : '', 'facture');
}
/** Un document émis (facture ou avoir) : ses informations, le PDF, la vérification, et les façons de le remettre au client. */
function bloc_document(array $k, array $f): string {
    $fid = (int)$f['id']; $avoir = $f['genre'] === 'avoir';
    $remises = facture_remises($fid);
    $r = '';
    foreach ($remises as $x) $r .= '<li>' . ($x['ok'] ? '' : '<span class="retard">Échec</span> · ') . '<span>' . h(REMISE_CANAUX[$x['canal']]) . '</span>' . ($x['detail'] !== '' ? ' <span translate="no">(' . h($x['detail']) . ')</span>' : '')
        . ' · <span>' . h(date_fr($x['le'], 'heure')) . '</span> · <span translate="no">' . h($x['par']) . '</span>'
        . ($x['ok'] && $x['canal'] === 'email' ? ' · <span class="petit">envoi accepté par le serveur, sans preuve de lecture</span>' : '') . '</li>';
    $remis = (bool)facture_remise_derniere($fid);
    $pdf = u('facture-pdf', ['id' => $fid]);
    $c = definition([($avoir ? 'Avoir' : 'Facture') => '<b translate="no">' . h($f['numero']) . '</b>' . ($avoir ? ' · <span translate="no" lang="fr">' . h($f['motif']) . '</span>' : ''),
                     'Émis' . ($avoir ? '' : 'e') . ' le' => '<span>' . h(date_fr($f['emise_le'], 'heure')) . '</span> · <span translate="no">' . h($f['emise_par']) . '</span>',
                     'Total TTC' => h(montant_facture((int)$f['total_ttc'])),
                     'Remise au client' => $r !== '' ? '<ul class="remises">' . $r . '</ul>' : '<span class="retard">Pas encore remis' . ($avoir ? '' : 'e') . '</span>',
                     'Vérification' => $f['verifiee_le'] ? '<span>Vérifié' . ($avoir ? '' : 'e') . '</span> · <span>' . h(date_fr($f['verifiee_le'], 'heure')) . '</span> · <span translate="no">' . h($f['verifiee_par']) . '</span>' : ''])
       . '<div class="actions">' . lien_bouton($pdf, icone('livre') . 'Voir le PDF', 'secondaire', ['target' => '_blank', 'rel' => 'noopener'])
       . lien_bouton(u('facture-pdf', ['id' => $fid, 'telecharger' => 1]), icone('imprimer') . 'Télécharger pour imprimer', 'discret')
       . (!$f['verifiee_le'] ? formulaire('facture_verifiee', '<input type="hidden" name="facture" value="' . $fid . '">' . bouton(icone('valider') . 'J\'ai vérifié : ' . ($avoir ? 'l\'avoir est juste' : 'la facture est juste'), 'discret'), ['class' => 'form-ligne']) : '')
       . '</div>';
    if (facture_attend_verification($f))   // audit n° 6, constat 5 : pendant la phase de contrôle, rien ne se remet avant « J'ai vérifié »
        return $c . '<p class="aide">Phase de contrôle : ouvrez le PDF et vérifiez nom, gâteaux, prix, TVA et date. Touchez « J\'ai vérifié » s\'il est juste ; sinon, faites un avoir. Les façons de le remettre au client apparaissent ensuite.</p>';
    if (!$f['verifiee_le']) $c .= '<p class="aide">Ouvrez le PDF et vérifiez nom, gâteaux, prix, TVA et date avant de le remettre.</p>';
    // Remettre au client (FR-007 à FR-009)
    $email = (string)$k['client_email'];
    $s = '';
    if (email_valide($email)) {
        $m = mail_facture($k, $f, ['apercu' => true]);
        $ap = 'apercu-facture-' . $fid;
        $s .= '<details class="email-pret" id="mail-facture-' . $fid . '"' . (!$remis && !$avoir ? ' open' : '') . '><summary>' . icone('email') . 'Par mail, PDF joint' . ($remis ? '' : '') . '</summary>'
            . formulaire('facture_envoyer', '<input type="hidden" name="facture" value="' . $fid . '">'
                . zone('accueil', 'Phrase d\'accueil', facture_accueil($k, $f), ['rows' => '4', 'maxlength' => '1500', 'data-apercu' => $ap, 'lang' => 'fr', 'data-aide-saisie' => '1', 'id' => 'f-accueil-facture-' . $fid])
                . zone('mot', 'Petit mot (facultatif)', '', ['rows' => '2', 'maxlength' => '1000', 'data-apercu' => $ap, 'lang' => 'fr', 'data-aide-saisie' => '1', 'id' => 'f-mot-facture-' . $fid, 'placeholder' => 'Par exemple : merci encore pour votre commande !'])
                . '<p class="etiq-apercu">Aperçu du mail (le PDF est joint)</p>'
                . '<div class="cadre-apercu" id="' . $ap . '" data-source="' . h(u('facture-apercu', ['id' => $fid])) . '">' . $m['html'] . '</div>'
                . '<div class="actions">' . bouton(icone('email') . 'Envoyer à <span translate="no">' . h($email) . '</span>', 'principal') . '</div>',
                ['class' => 'form-mail', 'data-confirmer' => 'Envoyer ' . ($avoir ? 'l\'avoir' : 'la facture') . ' à ' . $email . ' maintenant ?']) . '</details>';
    }
    if (facture_lien_actif($f)) {
        $lien = facture_lien($f);
        $s .= '<details class="email-pret" id="lien-facture-' . $fid . '"' . (!$remis && !email_valide($email) ? ' open' : '') . '><summary>' . icone('copier') . 'Par SMS ou WhatsApp (lien privé)</summary>'
            . '<p class="aide">Copiez le texte, collez-le dans un SMS ou WhatsApp au client, puis notez-le. Le lien ne montre que ' . ($avoir ? 'cet avoir' : 'cette facture') . ', pendant un an.</p>'
            . '<pre class="corps" id="sms-facture-' . $fid . '" translate="no" lang="fr">' . h(facture_sms($k, $f)) . '</pre>'
            . '<div class="actions"><button type="button" class="btn" data-copier="sms-facture-' . $fid . '">' . icone('copier') . 'Copier le texte</button></div>'
            . formulaire('facture_remise', '<input type="hidden" name="facture" value="' . $fid . '">' . liste_choix('canal', 'Envoyé par', ['sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'autre' => 'Autrement'], 'sms')
                . bouton('J\'ai envoyé le lien', 'secondaire'), ['class' => 'form-etape']) . '</details>'
            . '<details class="email-pret" id="qr-facture-' . $fid . '"><summary>' . icone('jour') . 'QR code à montrer au client</summary>'
            . '<p class="aide">Le client scanne l\'écran avec son téléphone et ouvre ' . ($avoir ? 'l\'avoir' : 'la facture') . '.</p><div class="qr-cadre">' . qr_svg($lien, 260, 'QR code ' . $f['numero']) . '</div>'
            . formulaire('facture_remise', '<input type="hidden" name="facture" value="' . $fid . '"><input type="hidden" name="canal" value="qr">' . bouton('Le client a scanné le QR code', 'secondaire'), ['class' => 'form-etape']) . '</details>';
    }
    $s .= '<details class="email-pret" id="papier-facture-' . $fid . '"><summary>' . icone('imprimer') . 'Sur papier</summary>'
        . '<p class="aide">Téléchargez le PDF, imprimez-le et donnez-le au client, puis notez-le.</p>'
        . '<div class="actions">' . lien_bouton(u('facture-pdf', ['id' => $fid, 'telecharger' => 1]), icone('imprimer') . 'Télécharger le PDF', 'discret') . '</div>'
        . formulaire('facture_remise', '<input type="hidden" name="facture" value="' . $fid . '"><input type="hidden" name="canal" value="papier">' . bouton('Remis' . ($avoir ? '' : 'e') . ' sur papier', 'secondaire'), ['class' => 'form-etape']) . '</details>';
    // Le lien privé : le couper (lien transféré par erreur) ou en refaire un
    $s .= '<p class="aide petit">' . (facture_lien_actif($f)
        ? '<span>Lien privé actif jusqu\'au</span> <span>' . h(date_fr(date('Y-m-d', strtotime($f['emise_le'] . ' +' . FACTURE_LIEN_MOIS . ' months')), 'longue')) . '</span>. '
          . formulaire('facture_lien_off', '<input type="hidden" name="facture" value="' . $fid . '">' . bouton('Désactiver le lien', 'discret'), ['class' => 'form-ligne', 'data-confirmer' => 'Désactiver ce lien ? Le client ne pourra plus l\'ouvrir.'])
        : '<span>Lien privé désactivé.</span> ' . formulaire('facture_lien_on', '<input type="hidden" name="facture" value="' . $fid . '">' . bouton('Faire un nouveau lien', 'discret'), ['class' => 'form-ligne'])) . '</p>';
    return $c . '<h3 class="sous-titre">' . ($remis ? 'Remettre à nouveau' : 'Remettre au client') . '</h3>' . $s;
}

/** Les actions du cadre « Facture », appelées depuis la page de la commande. */
function facture_post(array $k, string $a): void {
    $id = (int)$k['id'];
    foreach (['adresse', 'motif', 'nom_facture'] as $champ) if (preg_match(CORE_HANGUL, (string)($_POST[$champ] ?? ''))) { flash('erreur', 'La facture est toujours en français : écrivez le nom, l\'adresse et la raison en lettres latines.'); return; }
    if ($a === 'facture_demander' || $a === 'facture_sans') {
        $err = facture_demande_noter($id, $a === 'facture_demander', texte('adresse', 200));
        flash($err !== '' ? 'erreur' : 'ok', $err !== '' ? $err : ($a === 'facture_demander' ? 'C\'est noté : le client veut une facture.' : 'C\'est noté : pas de facture pour cette commande.'));
        return;
    }
    if ($a === 'facture_emettre') {
        $r = facture_emettre($id, ['adresse' => une_ligne(texte('adresse', 200)), 'nom' => une_ligne(texte('nom_facture', 120)), 'doublon_ok' => !empty($_POST['doublon_ok']),
                                   'empreinte' => (string)preg_replace('/[^0-9a-f]/', '', texte('empreinte', 64))]);
        flash(isset($r['erreur']) ? 'erreur' : 'ok', $r['erreur'] ?? ('Facture ' . $r['numero'] . ' émise. Vérifiez le PDF, puis remettez-la au client.'));
        return;
    }
    $f = facture(entier('facture', 1));
    if (!$f || (int)$f['commande_id'] !== $id) { flash('erreur', 'Facture introuvable.'); return; }
    if (in_array($a, ['facture_envoyer', 'facture_remise'], true) && facture_attend_verification($f)) { flash('erreur', 'Phase de contrôle : vérifiez d\'abord le PDF et touchez « J\'ai vérifié », puis remettez-le au client.'); return; }
    switch ($a) {
        case 'facture_envoyer':
            $ok = envoyer_mail_facture($k, $f, ['accueil' => texte('accueil', 1500), 'mot' => texte('mot', 1000)]);
            if ($ok) flash_envoi(($f['genre'] === 'avoir' ? 'Avoir envoyé' : 'Facture envoyée') . ' au client, avec le PDF.');
            else flash('erreur', 'Le mail n\'est pas parti. Réessayez, ou remettez ' . ($f['genre'] === 'avoir' ? 'l\'avoir' : 'la facture') . ' par le lien ou sur papier.');
            break;
        case 'facture_remise':
            $canal = choix('canal', ['sms' => 1, 'whatsapp' => 1, 'qr' => 1, 'papier' => 1, 'autre' => 1], '');
            if ($canal === '') { flash('erreur', 'Choisissez comment vous l\'avez remise.'); break; }
            facture_remise_noter((int)$f['id'], $canal);
            flash('ok', 'C\'est noté : ' . REMISE_NOTEES[$canal]);
            break;
        case 'facture_verifiee':
            // « J'ai vérifié » vaut après avoir ouvert le PDF, pas sur un simple toucher (audit n° 6, constat 5)
            if (empty($_SESSION['pdf_vus'][(int)$f['id']])) { flash('erreur', 'Ouvrez d\'abord le PDF (« Voir le PDF ») et relisez-le, puis touchez « J\'ai vérifié ».'); break; }
            q("UPDATE factures SET verifiee_le = ?, verifiee_par = ? WHERE id = ? AND verifiee_le IS NULL", [maintenant(), auteur(), (int)$f['id']]);
            evenement('facture', ($f['genre'] === 'avoir' ? 'Avoir ' : 'Facture ') . $f['numero'] . ' vérifié' . ($f['genre'] === 'avoir' ? '' : 'e') . ' (phase de contrôle)', ['commande' => $id, 'client' => (int)$k['client_id']]);
            flash('ok', 'Merci : c\'est noté comme vérifié.');
            break;
        case 'facture_lien_off':
            q("UPDATE factures SET jeton = '' WHERE id = ?", [(int)$f['id']]);
            evenement('facture', 'Lien privé de ' . $f['numero'] . ' désactivé', ['commande' => $id, 'client' => (int)$k['client_id']]);
            flash('ok', 'Lien désactivé : il ne s\'ouvre plus.');
            break;
        case 'facture_lien_on':
            if (strtotime($f['emise_le']) < strtotime('-' . FACTURE_LIEN_MOIS . ' months')) { flash('erreur', 'Plus d\'un an : donnez plutôt le PDF au client.'); break; }
            q("UPDATE factures SET jeton = ? WHERE id = ?", [bin2hex(random_bytes(12)), (int)$f['id']]);
            evenement('facture', 'Nouveau lien privé pour ' . $f['numero'] . ' (l\'ancien ne s\'ouvre plus)', ['commande' => $id, 'client' => (int)$k['client_id']]);
            flash('ok', 'Nouveau lien prêt. L\'ancien ne s\'ouvre plus.');
            break;
        case 'facture_aligner':   // après un avoir partiel (geste commercial) : le prix de la commande suit la facture
            $reste = facture_reste($f);
            if ($f['genre'] !== 'facture' || $reste <= 0) { flash('erreur', 'Rien à aligner.'); break; }
            commande_modifier($id, ['prix_final' => $reste]);
            flash('ok', 'Prix de la commande mis au montant de la facture.');
            break;
        case 'facture_avoir':
            $partiel = choix('mode', ['total' => 1, 'partiel' => 1], 'total') === 'partiel';
            $montant = $partiel ? centimes(texte('montant', 12)) : null;
            if ($partiel && ($montant === null || $montant <= 0)) { flash('erreur', 'Écrivez le montant de la correction (par exemple 5,00).'); break; }
            $taux = $partiel && ($t = texte('taux', 4)) !== '' && ctype_digit($t) ? (int)$t : null;
            $r = avoir_emettre((int)$f['id'], texte('motif', 200), $montant, $taux);
            flash(isset($r['erreur']) ? 'erreur' : 'ok', $r['erreur'] ?? ('Avoir ' . $r['numero'] . ' émis. Remettez-le au client comme la facture.'));
            break;
    }
}

/* ---------- Le PDF (connecté) et l'aperçu du mail ---------- */
function envoyer_pdf(array $f, bool $telecharger): never {
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($telecharger ? 'attachment' : 'inline') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $f['numero']) . '.pdf"');
    header('Content-Length: ' . strlen((string)$f['pdf']));
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Content-Type-Options: nosniff');
    echo $f['pdf'];
    exit;
}
function page_facture_pdf(): void {
    $f = facture(entier('id', 1), true);
    if (!$f) { http_response_code(404); mise_en_page('Introuvable', titre_page('Facture introuvable', '', '', u('factures')), 'plus'); }
    $_SESSION['pdf_vus'][(int)$f['id']] = 1;
    envoyer_pdf($f, !empty($_GET['telecharger']));
}
function page_facture_apercu(): void {
    $f = facture(entier('id', 1));
    $k = $f && $f['commande_id'] ? commande((int)$f['commande_id']) : null;
    if (!$k || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(404); exit; }
    csrf_verifier();
    entetes_securite();
    echo mail_facture($k, $f, ['accueil' => texte('accueil', 1500), 'mot' => texte('mot', 1000), 'apercu' => true])['html'];
    exit;
}

/* ---------- La page du lien privé (publique, FR-008) ---------- */
function page_facture_client(): void {
    $c = is_string($_GET['c'] ?? null) ? $_GET['c'] : '';
    $f = preg_match('/^(\d{1,9})-([0-9a-f]{24})$/', $c, $m) ? facture((int)$m[1], !empty($_GET['pdf'])) : null;
    entetes_securite();
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: private, no-store');
    $ok = $f && facture_lien_actif($f) && hash_equals((string)$f['jeton'], $m[2]);
    $tete = '<!doctype html><html lang="fr" translate="no"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
          . '<meta name="robots" content="noindex, nofollow"><title>' . ($ok ? h(($f['genre'] === 'avoir' ? 'Avoir ' : 'Facture ') . $f['numero']) . ' · ' : '') . 'Madame Cho</title>'
          . '<link rel="stylesheet" href="' . h(fichier_versionne('gestion.css')) . '"></head><body class="deconnecte"><main class="page etroite facture-publique">';
    if (!$ok) {
        http_response_code(404);
        echo $tete . '<h1>Ce lien n\'est plus disponible</h1><p>Il a pu être désactivé ou dater de plus d\'un an. Pour recevoir votre facture : 06 61 31 17 79 (appel, SMS ou WhatsApp) ou contact@madamecho.com.</p>'
           . '<p><a href="https://madamecho.com/">madamecho.com</a></p></main></body></html>';
        exit;
    }
    if (!empty($_GET['pdf'])) envoyer_pdf($f, false);
    $d = json_decode($f['donnees'], true) ?: [];
    $avoir = $f['genre'] === 'avoir';
    echo $tete . '<p class="marque">Madame Cho</p><h1>' . ($avoir ? 'Avoir ' : 'Facture ') . h($f['numero']) . '</h1>'
       . '<p>' . h(($d['client']['nom'] ?? '') . ' · commande ' . ($d['commande']['reference'] ?? '') . ' · ' . date_facture($f['emise_le'])) . '</p>'
       . '<p class="total-public">' . h(montant_facture((int)$f['total_ttc'])) . ' TTC</p>'
       . '<p><a class="btn principal" href="' . h(facture_lien($f)) . '/pdf">Ouvrir le PDF</a></p>'
       . '<p class="aide">Pour l\'enregistrer, ouvrez le PDF puis « Partager » ou « Télécharger ». Ce lien est personnel : il s\'ouvre pendant un an.</p>'
       . '<p class="aide">Une question ? 06 61 31 17 79 · contact@madamecho.com · <a href="https://madamecho.com/">madamecho.com</a></p></main></body></html>';
    exit;
}

/* ---------- Plus › Factures (FR-011) ---------- */
function page_factures(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verifier();
        if (texte('_action', 40) === 'vendeur' && est_admin()) {
            $v = [];
            foreach (VENDEUR_CHAMPS as $c => $l) $v[$c] = une_ligne(texte('v_' . $c, 200));
            $v['rcs'] = (string)preg_replace('/^R\.?\s?C\.?\s?S\.?\s+/iu', '', $v['rcs']);   // « RCS Nanterre » tapé en entier : la facture écrit déjà « RCS »
            $manque = array_filter(VENDEUR_OBLIGATOIRES, fn($c) => $v[$c] === '');
            if ($manque) flash('erreur', 'À remplir : ' . implode(', ', array_map(fn($c) => mb_strtolower(VENDEUR_CHAMPS[$c]), $manque)) . '.');
            elseif (preg_grep(CORE_HANGUL, $v)) flash('erreur', 'Les mentions de la facture sont en français.');
            elseif (array_filter($v, fn($x) => !Pdf::ecrivable($x))) flash('erreur', 'Une mention contient des caractères que la facture ne sait pas écrire (émoji…).');
            elseif (!siren_valide($v['siren'])) flash('erreur', 'Le SIREN doit avoir 9 chiffres et être valide : recopiez-le depuis le Kbis ou l\'avis de situation INSEE.');
            elseif (!tva_fr_valide($v['tva'], $v['siren'])) flash('erreur', 'Le numéro de TVA ne correspond pas au SIREN : il s\'écrit FR, deux chiffres de clé, puis le SIREN.');
            else {
                parametre_ecrire('vendeur', json_encode($v, JSON_UNESCAPED_UNICODE));
                if (!facture_prefixes_figes()) {
                    foreach (['facture_prefixe' => 'P-', 'avoir_prefixe' => 'AV-'] as $cle => $defaut) {
                        $p = strtoupper(texte($cle, 12));
                        if ($p !== '' && preg_match('/^[A-Z]{1,6}-?$/', $p)) parametre_ecrire($cle, $p);
                    }
                }
                evenement('reglage', 'Identité de Madame Cho sur les factures enregistrée');
                flash('ok', 'Enregistré : les prochaines factures porteront ces mentions.');
            }
        }
        rediriger('factures');
    }
    $mois = is_string($_GET['mois'] ?? null) && preg_match('/^\d{4}-\d{2}$/', $_GET['mois']) ? $_GET['mois'] : '';
    $tous = lignes("SELECT " . FACTURE_COLONNES . " FROM factures " . ($mois !== '' ? "WHERE substr(emise_le, 1, 7) = ? " : '') . "ORDER BY id DESC LIMIT 300", $mois !== '' ? [$mois] : []);
    $moisListe = array_column(lignes("SELECT DISTINCT substr(emise_le, 1, 7) AS m FROM factures ORDER BY m DESC"), 'm');
    $verif = factures_verifier();
    $verifiees = (int)val("SELECT COUNT(*) FROM factures WHERE genre = 'facture' AND verifiee_le IS NOT NULL");
    $c = titre_page(icone('paiement') . 'Factures', 'Factures et avoirs des particuliers. Les restaurants restent facturés dans Qonto.', '', u('plus'));
    // La date de bascule (audit n° 6, constat 13) : avant elle, les factures des particuliers sont dans Qonto
    $depuis = (string)parametre('factures_depuis', '');
    $bascule = $depuis !== '' ? '<p class="aide"><span>La gestion fait les factures des commandes remises depuis le</span> <span>' . h(date_fr(substr($depuis, 0, 10), 'longue')) . '</span>. <span>Celles d\'avant sont dans Qonto.</span></p>' : '';
    $c .= bloc('Phase de contrôle', '<p>' . h($verifiees . ' facture(s) vérifiée(s) sur ' . FACTURE_CONTROLE_MIN . ' prévues.') . '</p>'
        . '<div class="jauge-controle"><span style="width:' . min(100, intdiv(100 * $verifiees, FACTURE_CONTROLE_MIN)) . '%"></span></div>'
        . '<p class="aide">Pendant cette phase, rien ne part sans votre toucher : vous ouvrez chaque PDF, vous vérifiez, puis vous l\'envoyez. Ensuite, avec Olivier, on décidera s\'il faut aller plus vite.</p>' . $bascule);
    $c .= bloc('Journal des factures', $verif['problemes']
        ? '<div class="erreur" role="alert"><b>Attention : le journal ne correspond plus.</b><br>' . implode('<br>', array_map('MadameCho\Gestion\h', $verif['problemes'])) . '<br>Prévenez Olivier, ne touchez à rien.</div>'
        : '<p>' . icone('valider') . ' ' . '<span>' . h($verif['n'] ? 'Aucune modification détectée : ' . $verif['n'] . ' document(s) contrôlé(s) (PDF, données, chaîne et numéros sans trou).' : 'Pas encore de facture.') . '</span>' . '</p>'
          . ($verif['n'] ? '<p class="aide">Ce contrôle repère une facture modifiée ou retirée dans la gestion. Ce n\'est pas une certification fiscale.</p>' : ''),
        $verif['problemes'] ? 'alerte' : '');
    $opts = ['' => 'Tous les mois'];
    foreach ($moisListe as $m) $opts[$m] = ucfirst(mois_texte($m));
    $filtre = '<form class="filtres" method="get" action=""><input type="hidden" name="p" value="factures">' . liste_choix('mois', 'Mois', $opts, $mois, ['onchange' => 'this.form.submit()']) . '<noscript>' . bouton('Voir', 'discret') . '</noscript></form>';
    $l = ''; $total = 0;
    foreach ($tous as $f) {
        $d = json_decode($f['donnees'], true) ?: [];
        $avoir = $f['genre'] === 'avoir';
        $rem = facture_remise_derniere((int)$f['id']);
        $etat = $rem ? '<span class="chip s-remise">' . h(REMISE_CANAUX[$rem['canal']]) . '</span>' : '<span class="chip s-demande">Pas encore remis' . ($avoir ? '' : 'e') . '</span>';
        if (!$avoir && facture_reste($f) <= 0) $etat .= ' <span class="chip s-annulee">Annulée par avoir</span>';
        if ($f['verifiee_le']) $etat .= ' <span class="chip">Vérifiée</span>';
        $total += (int)$f['total_ttc'];
        $l .= '<a class="carte-cmd" href="' . h($f['commande_id'] ? u('commande', ['id' => $f['commande_id']]) . '#facture' : u('facture-pdf', ['id' => $f['id']])) . '">'
            . '<span class="l1"><b translate="no">' . h($f['numero']) . '</b>' . ($avoir ? ' <span class="chip">Avoir</span>' : '') . '</span>'
            . '<span class="l2"><span translate="no">' . h($d['client']['nom'] ?? '') . '</span> · <span translate="no">' . h(montant_facture((int)$f['total_ttc'])) . '</span> · <span>' . h(date_fr($f['emise_le'], 'courte')) . '</span></span>'
            . '<span class="l3">' . $etat . '</span><span class="l4" translate="no">' . h($d['commande']['reference'] ?? '') . '</span></a>';
    }
    $c .= bloc('Factures et avoirs', $filtre . ($l !== '' ? '<p class="aide"><span>' . h(count($tous) . ' document(s)') . '</span> · <span translate="no">' . h(montant_facture($total)) . '</span> <span>TTC, avoirs déduits</span></p><div class="liste">' . $l . '</div>' : '<p class="vide">Aucune facture' . ($mois !== '' ? ' ce mois-ci' : ' pour l\'instant') . '.</p>'));
    // Pour l'expert-comptable (réponse du 09/11/2026, question 4) : tout reste dans la base, et se télécharge en tableur quand il le faut
    // (choix d'Olivier) : les factures et avoirs du mois, et les espèces encaissées (le livre des recettes), factures demandées ou non
    $t = '';
    for ($i = 0; $i < 3; $i++) {
        $m = date('Y-m', strtotime(date('Y-m-01') . ' -' . $i . ' month'));
        $t .= '<li><b>' . h(ucfirst(mois_texte($m))) . '</b> : ' . '<a href="' . h(u('factures-export', ['mois' => $m])) . '">Factures et avoirs</a> · '
            . '<a href="' . h(u('factures-export', ['mois' => $m, 'jeu' => 'especes'])) . '">Espèces encaissées</a></li>';
    }
    $c .= bloc('Tableurs pour l\'expert-comptable', '<p class="aide">Chaque tableur s\'ouvre dans Excel. Les espèces encaissées forment le livre des recettes : toutes les commandes réglées en espèces, avec ou sans facture.</p><ul class="remises">' . $t . '</ul>');
    // L'identité de Madame Cho (FR-002) : saisie une fois, vérifiée par Olivier ou Youngmi
    $v = vendeur();
    $vals = $v ?? VENDEUR_SUGGESTION;
    $f = '';
    foreach (VENDEUR_CHAMPS as $cle => $lib) $f .= champ('v_' . $cle, $lib . (in_array($cle, VENDEUR_OBLIGATOIRES, true) ? '' : ' (facultatif)'), $vals[$cle] ?? '', ['maxlength' => '200', 'lang' => 'fr', 'translate' => 'no']);
    $fige = facture_prefixes_figes();
    $f .= '<div class="ligne-champs">' . champ('facture_prefixe', 'Début des numéros de facture', facture_prefixe('facture'), ['maxlength' => '7', 'disabled' => $fige])
        . champ('avoir_prefixe', 'Début des numéros d\'avoir', facture_prefixe('avoir'), ['maxlength' => '7', 'disabled' => $fige]) . '</div>'
        . '<p class="aide"><span>Exemple</span> : <b translate="no">' . h(sprintf('%s%d-%04d', facture_prefixe('facture'), (int)date('Y'), 1)) . '</b>. ' . ($fige ? 'Des factures de cette année portent déjà ce début : il ne change plus avant le 1er janvier.' : 'Une série à part pour les particuliers, validée par l\'expert-comptable : les numéros se suivent dans l\'ordre des dates.') . '</p>';
    $c .= est_admin() ? '<details class="replie bloc" id="identite"' . ($v ? '' : ' open') . '><summary>' . icone('reglages') . 'Identité de Madame Cho sur les factures' . ($v ? '' : ' <span class="chip s-demande">à enregistrer</span>') . '</summary>'
        . '<p class="aide">' . ($v ? 'Ces mentions figurent sur chaque facture.' : 'Repris des mentions légales du site : vérifiez, puis enregistrez. Aucune facture ne peut s\'émettre avant.') . '</p>'
        . formulaire('vendeur', $f . bouton('Enregistrer', 'principal')) . '</details>' : '';
    mise_en_page('Factures', $c, 'plus');
}

/**
 * Le tableur d'un mois pour l'expert-comptable : chaque facture et chaque avoir, les montants par taux de TVA, la date et le moyen du
 * règlement (les espèces s'y voient : réponse de l'expert-comptable du 09/11/2026, question 4). Lu dans les données figées.
 */
const FACTURES_EXPORT_ENTETES = ['Numéro', 'Document', 'Émis le', 'Date de la vente', 'Client', 'Adresse du client', 'HT 5,5 %', 'TVA 5,5 %', 'HT 20 %', 'TVA 20 %',
                                 'HT autres taux', 'TVA autres taux', 'Total HT', 'Total TVA', 'Total TTC', 'Réglé le', 'Moyen', 'Acompte', 'Acompte versé le', 'Moyen de l\'acompte',
                                 'Facture d\'origine (avoir)', 'Raison (avoir)', 'Commande'];
/** Un texte saisi (nom, adresse, raison) ne doit jamais devenir une formule dans Excel : « = », « + », « - », « @ » en tête sont désamorcés. */
function tableur_texte(string $s): string {
    return preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;
}
function factures_export_lignes(string $mois): array {
    $n = fn(int $c): string => number_format($c / 100, 2, ',', '');
    $out = [];
    foreach (lignes("SELECT " . FACTURE_COLONNES . " FROM factures WHERE substr(emise_le, 1, 7) = ? ORDER BY id", [$mois]) as $f) {
        $d = json_decode((string)$f['donnees'], true) ?: [];
        $t = [55 => [0, 0], 200 => [0, 0], 0 => [0, 0]];
        foreach ($d['par_taux'] ?? [] as $x) { $k = isset($t[(int)$x['taux']]) && (int)$x['taux'] ? (int)$x['taux'] : 0; $t[$k][0] += (int)$x['ht']; $t[$k][1] += (int)$x['tva']; }
        $avoir = $f['genre'] === 'avoir';
        $pa = $avoir ? [] : ($d['paiement'] ?? []);
        $out[] = [$f['numero'], $avoir ? 'Avoir' : 'Facture', substr((string)$f['emise_le'], 0, 10), (string)($d['date_vente'] ?? ''), tableur_texte((string)($d['client']['nom'] ?? '')),
                  tableur_texte((string)($d['client']['adresse'] ?? '')), $n($t[55][0]), $n($t[55][1]), $n($t[200][0]), $n($t[200][1]), $n($t[0][0]), $n($t[0][1]),
                  $n((int)$f['total_ht']), $n((int)$f['total_tva']), $n((int)$f['total_ttc']), (string)($pa['le'] ?? ''), (string)($pa['moyen'] ?? ''),
                  (int)($pa['acompte'] ?? 0) ? $n((int)$pa['acompte']) : '', (string)($pa['acompte_le'] ?? ''), (string)($pa['acompte_moyen'] ?? ''),
                  (string)($d['origine']['numero'] ?? ''), tableur_texte((string)($d['motif'] ?? '')), (string)($d['commande']['reference'] ?? '')];
    }
    return $out;
}
/**
 * Le livre des recettes en espèces d'un mois (réponse de l'expert-comptable du 09/11/2026) : chaque somme reçue en espèces, particuliers et
 * restaurants, avec ou sans facture, à la date où elle a été notée. Un acompte et le solde d'une même commande font deux lignes, chacun avec
 * son moyen ; une commande annulée dont l'acompte est gardé y reste. Lu dans la base, jamais tenu à part (choix d'Olivier du 09/11/2026).
 */
const ESPECES_EXPORT_ENTETES = ['Encaissé le', 'Commande', 'Client', 'Particulier ou professionnel', 'Encaissé TTC', 'Nature', 'Facture', 'Retrait prévu le'];
function especes_export_lignes(string $mois): array {
    $out = [];
    foreach (lignes("SELECT id FROM commandes WHERE paiement IN ('paye', 'acompte_recu') AND (moyen = 'especes' OR acompte > 0) ORDER BY id") as $x) {
        $k = commande((int)$x['id']);
        if (!$k) continue;
        $le = substr((string)($k['paiement_le'] ?: ($k['remise_le'] ?: $k['maj_le'])), 0, 10);
        $total = montant_commande($k);
        $acompte = (int)$k['acompte'];
        $especes = $k['moyen'] === 'especes';
        $sommes = [];
        if ($k['paiement'] === 'acompte_recu') {
            if ($especes) $sommes[] = [$le, 'Acompte', $acompte > 0 ? $acompte : $total];
        } else {
            [$leA, $moyenA] = $acompte > 0 ? facture_acompte_info((int)$k['id']) : ['', ''];
            if ($leA === '') { if ($especes) $sommes[] = [$le, 'Paiement', $total]; }   // payé en une fois (ou acompte jamais noté à part)
            else {
                if ($moyenA === MOYENS_FACTURE['especes']) $sommes[] = [$leA, 'Acompte', $acompte];
                if ($especes) $sommes[] = [$le, 'Solde', $total - $acompte];
            }
        }
        $f = facture_en_vigueur((int)$k['id']);
        foreach ($sommes as [$quand, $nature, $montant]) if ($montant > 0 && substr($quand, 0, 7) === $mois)
            $out[] = [$quand, $k['reference'], tableur_texte(commande_client_nom($k)), commande_est_pro($k) ? 'Professionnel' : 'Particulier',
                      number_format($montant / 100, 2, ',', ''), $nature, $f ? (string)$f['numero'] : tableur_texte((string)$k['facture']), (string)$k['retrait_date']];
    }
    usort($out, fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
    return $out;
}
function page_factures_export(): void {
    $mois = is_string($_GET['mois'] ?? null) && preg_match('/^\d{4}-\d{2}$/', $_GET['mois']) ? $_GET['mois'] : '';
    if ($mois === '') rediriger('factures');
    $especes = ($_GET['jeu'] ?? '') === 'especes';
    evenement('export', ($especes ? 'Tableur des espèces de ' : 'Tableur des factures de ') . mois_texte($mois) . ' téléchargé');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="madamecho-' . ($especes ? 'especes-' : 'factures-') . $mois . '.csv"');
    header('Cache-Control: no-store, private');
    echo $especes ? csv(especes_export_lignes($mois), ESPECES_EXPORT_ENTETES) : csv(factures_export_lignes($mois), FACTURES_EXPORT_ENTETES);
    exit;
}

/* ---------- Accueil (FR-010) ---------- */
function bloc_factures_accueil(): string {
    $a = factures_alertes();
    $l = '';
    foreach ($a['echecs'] as $f) $l .= ligne_alerte_facture($f, 'Mail non parti : renvoyer ou remettre autrement', 'retard');
    foreach ($a['a_corriger'] as $x) $l .= '<a class="carte-cmd" href="' . h(u('commande', ['id' => $x['commande']['id']]) . '#facture') . '"><span class="l1"><b translate="no">' . h(commande_client_nom($x['commande'])) . '</b><span class="retard">Facture à corriger (avoir)</span></span><span class="l4" translate="no">' . h($x['facture']['numero'] . ' · ' . $x['commande']['reference']) . '</span></a>';
    foreach ($a['a_emettre'] as $k) $l .= '<a class="carte-cmd" href="' . h(u('commande', ['id' => $k['id']]) . '#facture') . '"><span class="l1"><b translate="no">' . h(commande_client_nom($k)) . '</b><span class="dormant">Facture à émettre</span></span><span class="l2"><span>Remise le</span> <span>' . h(date_fr($k['remise_le'], 'courte')) . '</span> · <span translate="no">' . h(montant_facture(montant_commande($k))) . '</span></span><span class="l4" translate="no">' . h($k['reference']) . '</span></a>';
    foreach ($a['pas_remises'] as $f) $l .= ligne_alerte_facture($f, 'Facture émise, pas encore remise au client', 'dormant');
    if ($l === '') return '';
    return bloc(icone('paiement') . ' Factures à faire', '<p class="aide">Touchez une ligne : la facture est prête à relire, puis à remettre au client. Rien ne part tout seul.</p><div class="liste">' . $l . '</div>', '', 'factures');
}
function ligne_alerte_facture(array $f, string $texte, string $classe): string {
    $d = json_decode($f['donnees'], true) ?: [];
    return '<a class="carte-cmd" href="' . h(u('commande', ['id' => $f['commande_id']]) . '#facture') . '"><span class="l1"><b translate="no">' . h($d['client']['nom'] ?? '') . '</b><span class="' . $classe . '">' . h($texte) . '</span></span>'
         . '<span class="l4" translate="no">' . h($f['numero'] . ' · ' . ($d['commande']['reference'] ?? '')) . '</span></a>';
}

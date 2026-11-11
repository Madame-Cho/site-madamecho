<?php
/*
 * Madame Cho : accusé de réception au client (demandes d'Olivier du 09/11 et du 11/11/2026).
 * Dès qu'une commande arrive, par le formulaire du site ou saisie par Youngmi dans la gestion après un appel, un mail court dit au client
 * que Madame Cho l'a bien reçue, qu'elle la traite dans les plus brefs délais et revient vers lui. Il part TOUT SEUL, sans toucher :
 * exception à R-01 voulue par Olivier (« mail vraiment automatique, même si ça va contre nos règles », 11/11/2026). Texte fixe, sans prix
 * ni décision, écrit à l'avance et jamais par une IA ; depuis contact@, signé DKIM par le site (sélecteur « site », P-07).
 * Chargé par le formulaire du site (inc/enregistrer.php) et par la gestion (index.php).
 */
declare(strict_types=1);
namespace MadameCho\Gestion;

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/modele.php';
require_once __DIR__ . '/vues.php';    // mail_commande(), au même dessin que les mails de la gestion
require_once __DIR__ . '/email.php';

/**
 * $d : ref, prenom (ou nom complet dans « nom »), email ; $commande : son numéro interne (0 si la demande du site n'est pas enregistrée) ;
 * $statut : celui de la commande ; $limite : une fois par adresse et par 24 heures (formulaire du site, ouvert à tous ; pas pour la gestion).
 * Garde-fous contre l'envoi à l'adresse de quelqu'un d'autre : rien de ce que le visiteur a écrit sauf un prénom fait de lettres, la limite
 * de 24 heures (empreinte HMAC de l'adresse, effacée après 24 heures) et celle de 5 demandes par heure du formulaire.
 * Arrêtable dans Paramètres › Demandes du site. Pas de copie à contact@, qui a déjà la commande. Rangé dans les Échanges de la commande.
 * Renvoie 'envoye', 'arrete', 'deja' (même adresse il y a moins de 24 heures) ou 'echec'.
 */
function accuser_reception(array $d, int $commande = 0, string $statut = 'demande', bool $limite = true): string {
    if (!gestion_base_existante() || parametre('accuse_reception', '1') !== '1') return 'arrete';
    $email = mb_strtolower(trim((string)($d['email'] ?? '')));
    $ref = (string)($d['ref'] ?? '');
    if (!email_valide($email) || !preg_match('/^MC-\d{6}-[0-9A-F]{4}$/', $ref)) return 'echec';
    $cle = '';
    if ($limite) {   // la ligne est prise avant l'envoi : deux demandes à la fois n'en envoient qu'un
        q("DELETE FROM parametres WHERE cle LIKE 'accuse:%' AND CAST(valeur AS INTEGER) < ?", [time() - 86400]);
        $cle = 'accuse:' . substr(hash_hmac('sha256', $email, sel()), 0, 32);
        $st = db()->prepare("INSERT OR IGNORE INTO parametres (cle, valeur) VALUES (?, ?)");
        $st->execute([$cle, (string)time()]);
        if ($st->rowCount() === 0) return 'deja';
    }
    // Le prénom, seulement s'il est fait de lettres (pas de lien ni de chiffres glissés dans « Bonjour … ») ; sinon « Bonjour, »
    [$prenom] = isset($d['prenom']) ? [(string)$d['prenom']] : prenom_nom((string)($d['nom'] ?? ''));
    $prenom = trim($prenom);
    if (!preg_match("/^\\p{L}[\\p{L}\\p{M}'’ -]{0,39}$/u", $prenom)) $prenom = '';
    $k = ['reference' => $ref, 'statut' => $statut, 'client_prenom' => $prenom, 'client_nom' => '', 'client_type' => 'particulier', 'client_contact' => ''];
    $m = mail_commande($k, 'recu');
    $mid = envoyer_email_client($email, $m['sujet'], $m['corps'], $m['html'], [], false);
    if ($mid === '') { if ($cle !== '') q("DELETE FROM parametres WHERE cle = ?", [$cle]); return 'echec'; }
    // Le Message-ID est gardé : la proposition et la confirmation suivront dans la même conversation chez le client.
    // Ni « dernier échange » ni réponse : la demande reste « à répondre » pour Youngmi.
    if ($commande > 0 && ($cid = val("SELECT client_id FROM commandes WHERE id = ?", [$commande]))) {
        evenement('email_client', 'Accusé de réception envoyé automatiquement à ' . $email . ' : « ' . $m['sujet'] . ' »', ['commande' => $commande, 'client' => (int)$cid],
                  'type:recu mid:' . $mid, $m['corps'], 'site');
    }
    return 'envoye';
}

/** Une commande saisie dans la gestion (après un appel, un message…) : l'accusé part si le client a un email et si la commande attend
 *  encore quelque chose de Madame Cho (pas une vente déjà faite, ni une livraison de la gamme pro). Renvoie comme accuser_reception(), ou 'sans'. */
function accuser_reception_commande(int $id): string {
    $k = commande($id);
    if (!$k || !in_array($k['statut'], ['demande', 'proposition', 'confirmee'], true) || ($k['type'] === 'pro' && $k['mode'] === 'livraison')) return 'sans';
    if (!email_valide((string)$k['client_email'])) return 'sans';
    $prenom = ($k['client_type'] === 'professionnel' && trim((string)$k['client_contact']) !== '') ? (string)$k['client_contact'] : (string)$k['client_prenom'];
    return accuser_reception(['ref' => $k['reference'], 'prenom' => $prenom, 'email' => $k['client_email']], $id, (string)$k['statut'], false);
}

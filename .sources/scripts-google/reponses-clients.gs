/**
 * Madame Cho : les réponses des clients dans l'espace de gestion (demande d'Olivier du 07/11/2026).
 *
 * À installer UNE fois, connecté avec contact@madamecho.com (et seulement ce compte), sur script.google.com :
 * nouveau projet « Réponses des clients », ce fichier dans Code.gs, le fichier appsscript.json à côté (il limite le script
 * à la LECTURE de la boîte : il ne peut ni envoyer, ni supprimer, ni modifier un mail), le service avancé « Gmail API »,
 * puis la propriété du script CLE (la clé créée dans la gestion, Réglages › Réponses des clients), puis lancer « installer ».
 * Guide pas à pas en français et en coréen : Drive, 15/04.
 *
 * Ce qu'il fait, chaque minute : il cherche les mails des deux derniers jours dont l'objet porte une référence de commande (MC-…),
 * et envoie à la gestion ceux qu'il n'a pas encore envoyés : expéditeur, date, objet, texte, noms des pièces jointes (pas leur contenu).
 * La gestion les range dans la commande. Aucune clé ni donnée de client n'est écrite dans ce fichier : le dépôt du site est public.
 */
var ADRESSE = 'https://madamecho.com/gestion/index.php?p=reponses';
var RECHERCHE = 'subject:MC newer_than:2d -in:spam -in:trash';
var REFERENCE = /\bMC-\d{6}-[0-9A-F]{4}\b/;   // seuls les mails dont l'objet porte une référence de commande partent
var MAX_PAR_PASSAGE = 30;
var BATTEMENT_MS = 60 * 60 * 1000;   // même sans mail, un signe de vie chaque heure (la gestion alerte si rien depuis 24 h)

function installer() {
  ScriptApp.getProjectTriggers().forEach(function (t) { ScriptApp.deleteTrigger(t); });
  ScriptApp.newTrigger('transmettre').timeBased().everyMinutes(1).create();
  transmettre();
}

function transmettre() {
  var props = PropertiesService.getScriptProperties();
  var cle = props.getProperty('CLE');
  if (!cle) throw new Error('Propriété CLE manquante (Paramètres du projet › Propriétés du script)');
  var deja = JSON.parse(props.getProperty('DEJA') || '[]');
  var vus = {};
  deja.forEach(function (id) { vus[id] = true; });
  var liste = Gmail.Users.Messages.list('me', { q: RECHERCHE, maxResults: 100 });
  var nouveaux = (liste.messages || []).filter(function (m) { return !vus[m.id]; }).slice(0, MAX_PAR_PASSAGE);
  var dernier = Number(props.getProperty('DERNIER') || 0);
  if (!nouveaux.length && Date.now() - dernier < BATTEMENT_MS) return;
  var mails = [], ecartes = [];
  nouveaux.forEach(function (m) { var x = lire(m.id); if (REFERENCE.test(x.objet)) mails.push(x); else ecartes.push(m.id); });
  if (!mails.length && Date.now() - dernier < BATTEMENT_MS) { props.setProperty('DEJA', JSON.stringify(deja.concat(ecartes).slice(-500))); return; }
  var r = UrlFetchApp.fetch(ADRESSE, { method: 'post', payload: { cle: cle, mails: JSON.stringify(mails) }, muteHttpExceptions: true, followRedirects: false });
  if (r.getResponseCode() !== 200) throw new Error('La gestion a répondu ' + r.getResponseCode());
  var faits = JSON.parse(r.getContentText()).faits || [];
  props.setProperty('DEJA', JSON.stringify(deja.concat(ecartes, faits).slice(-500)));
  props.setProperty('DERNIER', String(Date.now()));
}

function lire(id) {
  var m = Gmail.Users.Messages.get('me', id, { format: 'full' });
  var entetes = {};
  (m.payload.headers || []).forEach(function (h) { entetes[h.name.toLowerCase()] = h.value; });
  var texte = '', html = '', pj = [];
  (function parcourir(p) {
    if (p.filename) { pj.push(p.filename); return; }
    if (p.mimeType === 'text/plain' && p.body && p.body.data && !texte) texte = decoder(p.body.data);
    else if (p.mimeType === 'text/html' && p.body && p.body.data && !html) html = decoder(p.body.data);
    (p.parts || []).forEach(parcourir);
  })(m.payload);
  if (!texte && html) texte = html.replace(/<br\s*\/?>/gi, '\n').replace(/<\/(p|div|tr|li)>/gi, '\n').replace(/<[^>]+>/g, '')
    .replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#39;/g, "'");
  return {
    id: m.id, de: entetes['from'] || '', a: entetes['to'] || '', objet: entetes['subject'] || '', date: entetes['date'] || '',
    message_id: entetes['message-id'] || '', references: entetes['references'] || '', texte: texte.slice(0, 50000), pj: pj
  };
}

function decoder(d) {
  while (d.length % 4) d += '=';
  return Utilities.newBlob(Utilities.base64DecodeWebSafe(d)).getDataAsString('UTF-8');
}

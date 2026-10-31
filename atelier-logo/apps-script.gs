// Collecte des votes du Tinder des logos Madame Cho.
// À coller dans Extensions > Apps Script de la feuille « Tinder des logos », puis Déployer > Nouveau déploiement
// > Application web, exécuter en tant que : moi, accès : tout le monde. Copier l'URL dans VOTES_URL de index.html.
const SHEET = "Votes";
function doPost(e) {
  const lock = LockService.getScriptLock(); lock.waitLock(10000);
  try {
    const data = JSON.parse(e.postData.contents);
    const sh = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(SHEET);
    const now = new Date();
    const rows = (data.rows || []).map(r => [now, r.prenom, r.id, r.vote, r.connue, r.secondes, r.position, r.session, r.image, r.t]);
    if (rows.length) sh.getRange(sh.getLastRow() + 1, 1, rows.length, rows[0].length).setValues(rows);
    return ContentService.createTextOutput(JSON.stringify({ok: true, n: rows.length})).setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ok: false, error: String(err)})).setMimeType(ContentService.MimeType.JSON);
  } finally { lock.releaseLock(); }
}
function doGet() { return ContentService.createTextOutput("Atelier logo : collecte active."); }

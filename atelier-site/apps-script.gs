// Atelier du site : reçoit les réponses des pages de choix de Youngmi et les écrit dans la feuille privée
// « Réponses du site » (Drive 91/03), onglet « Réponses ». Voir GUIDE.md, section 1.
// Une ligne par gâteau, par photo, ou « fin » ; la dernière ligne par Prénom et Id fait foi.
const SHEET = "Réponses";
const HEAD = ["Horodatage", "Prénom", "Id", "Réponse", "Session", "Page", "Horodatage navigateur"];

function doPost(e) {
  const lock = LockService.getScriptLock();
  lock.waitLock(10000);
  try {
    const data = JSON.parse(e.postData.contents);
    const ss = SpreadsheetApp.getActiveSpreadsheet();
    const sh = ss.getSheetByName(SHEET) || ss.insertSheet(SHEET);
    if (sh.getLastRow() === 0) sh.appendRow(HEAD);
    const now = new Date();
    const rows = (data.rows || []).map(r => [now, r.prenom, r.id, r.vote, r.session, r.image, r.t]);
    if (rows.length) sh.getRange(sh.getLastRow() + 1, 1, rows.length, HEAD.length).setValues(rows);
    return ContentService.createTextOutput(JSON.stringify({ok: true, n: rows.length})).setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ok: false, error: String(err)})).setMimeType(ContentService.MimeType.JSON);
  } finally {
    lock.releaseLock();
  }
}

function doGet() {
  return ContentService.createTextOutput("Atelier du site : collecte active.");
}

/* Mesure d'audience de Madame Cho : Matomo installé chez OVH, réglé pour l'exemption de consentement de la CNIL.
   - aucun cookie (disableCookies), aucune donnée croisée, adresse IP tronquée côté serveur (réglage de Matomo) ;
   - le visiteur peut refuser la mesure sur la page Confidentialité (choix gardé dans son navigateur) ;
   - « Do Not Track » et « Global Privacy Control » du navigateur sont respectés.
   L'adresse de Matomo et le numéro du site sont lus sur la balise du script (data-matomo, data-site), posés par gen.py. */
(function () {
  var balise = document.currentScript;
  var url = balise && balise.getAttribute("data-matomo");
  var site = balise && balise.getAttribute("data-site");
  var CLE = "mc-mesure-refus";

  function refuse() {
    try { return localStorage.getItem(CLE) === "1"; } catch (e) { return false; }
  }

  // Interrupteur de la page Confidentialité
  function brancherChoix() {
    var c = document.getElementById("mesure-choix");
    var etat = document.getElementById("mesure-etat");
    if (!c) return;
    function maj() {
      var r = refuse();
      c.checked = !r;
      if (etat) etat.textContent = r ? "Vos visites ne sont pas mesurées." : "Vos visites sont comptées, de façon anonyme.";
    }
    c.addEventListener("change", function () {
      try { if (c.checked) localStorage.removeItem(CLE); else localStorage.setItem(CLE, "1"); } catch (e) {}
      maj();
    });
    maj();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", brancherChoix);
  else brancherChoix();

  var dnt = navigator.doNotTrack === "1" || window.doNotTrack === "1" || navigator.globalPrivacyControl === true;
  if (!url || !site || refuse() || dnt) return;

  var _paq = window._paq = window._paq || [];
  _paq.push(["setTrackerUrl", url + "matomo.php"]);
  _paq.push(["setSiteId", site]);
  _paq.push(["disableCookies"]);
  _paq.push(["setDoNotTrack", true]);
  _paq.push(["trackPageView"]);
  _paq.push(["enableLinkTracking"]);
  var s = document.createElement("script");
  s.async = true;
  s.src = url + "matomo.js";
  document.head.appendChild(s);
})();

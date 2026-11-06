/* Madame Cho : espace de gestion. Petits gestes (copier un texte, confirmer, imprimer, ajouter une ligne).
   Tout marche aussi sans ce fichier, sauf copier et imprimer. */
(function () {
  "use strict";
  var d = document;

  // Copier le texte d'un email préparé
  d.addEventListener("click", function (e) {
    var b = e.target.closest("[data-copier]");
    if (!b) return;
    var el = d.getElementById(b.getAttribute("data-copier"));
    if (!el) return;
    var texte = el.innerText || el.textContent || "";
    var fini = function (ok) {
      var avant = b.innerHTML;
      b.textContent = ok ? "Copié" : "Sélectionnez le texte et copiez-le";
      setTimeout(function () { b.innerHTML = avant; }, 1800);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texte).then(function () { fini(true); }, function () { fini(false); });
    } else {
      var r = d.createRange(); r.selectNodeContents(el);
      var s = window.getSelection(); s.removeAllRanges(); s.addRange(r);
      try { fini(d.execCommand("copy")); } catch (x) { fini(false); }
    }
  });

  // Imprimer (fiche de production)
  d.addEventListener("click", function (e) {
    if (e.target.closest("[data-imprimer]")) window.print();
  });

  // Confirmer avant une action qui compte ; un seul envoi par formulaire
  d.addEventListener("submit", function (e) {
    var f = e.target;
    var q = f.getAttribute("data-confirmer");
    if (q && !window.confirm(q)) { e.preventDefault(); return; }
    var b = f.querySelector("button[type=submit]");
    if (b && f.method.toLowerCase() === "post") setTimeout(function () { b.disabled = true; }, 0);
  });

  // Filtres qui s'appliquent dès qu'on change une valeur
  d.addEventListener("change", function (e) {
    var el = e.target;
    if (el.hasAttribute("data-auto") && el.form) el.form.submit();
    // « Nouvel ingrédient… » dans une recette
    var id = el.getAttribute && el.getAttribute("data-nouveau");
    if (id) {
      var bloc = d.getElementById(id);
      if (!bloc) return;
      var nouveau = el.value === "nouveau";
      bloc.hidden = !nouveau;
      var nom = bloc.querySelector("input");
      if (nom) { nom.required = nouveau; if (nouveau) nom.focus(); }
    }
  });

  // Champs montrés seulement pour un choix : data-si="id-du-choix=valeur"
  function conditions() {
    d.querySelectorAll("[data-si]").forEach(function (el) {
      var r = el.getAttribute("data-si").split("="), c = d.getElementById(r[0]);
      if (c) el.hidden = c.value !== r[1];
    });
  }
  d.addEventListener("change", conditions);
  conditions();

  // Une ligne de gâteau de plus
  d.addEventListener("click", function (e) {
    var b = e.target.closest("[data-ajouter-ligne]");
    if (!b) return;
    var modele = d.getElementById(b.getAttribute("data-ajouter-ligne"));
    var cible = d.getElementById(b.getAttribute("data-cible"));
    if (!modele || !cible) return;
    var n = parseInt(cible.getAttribute("data-prochain"), 10) || 0;
    var html = modele.innerHTML.replace(/999/g, String(n));
    cible.insertAdjacentHTML("beforeend", html);
    cible.setAttribute("data-prochain", String(n + 1));
    var s = cible.lastElementChild && cible.lastElementChild.querySelector("select");
    if (s) s.focus();
  });
})();

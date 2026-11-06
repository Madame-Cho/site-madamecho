/* Madame Cho : espace de gestion. Petits gestes (copier un texte, confirmer, imprimer, ajouter une ligne).
   Tout marche aussi sans ce fichier, sauf copier et imprimer. */
(function () {
  "use strict";
  var d = document;
  // L'écran en coréen (page traduite par la gestion) : les quelques mots écrits ici suivent
  var ko = (d.documentElement.getAttribute("lang") || "").indexOf("ko") === 0;

  // Copier le texte d'un email préparé
  d.addEventListener("click", function (e) {
    var b = e.target.closest("[data-copier]");
    if (!b) return;
    var el = d.getElementById(b.getAttribute("data-copier"));
    if (!el) return;
    var texte = el.innerText || el.textContent || "";
    var fini = function (ok) {
      var avant = b.innerHTML;
      b.textContent = ok ? (ko ? "복사했어요" : "Copié") : (ko ? "텍스트를 선택해서 복사하세요" : "Sélectionnez le texte et copiez-le");
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

  // « Afficher le mot de passe » : une case sous les champs de mot de passe de chaque formulaire,
  // pour relire ce qu'on a tapé en cas de doute (demande d'Olivier, 06/11/2026)
  d.querySelectorAll("form").forEach(function (f) {
    var champs = Array.prototype.slice.call(f.querySelectorAll("input[type=password]"));
    if (!champs.length) return;
    champs.forEach(function (x) {
      // En clair, le téléphone ne doit ni corriger ni mettre de majuscule
      x.setAttribute("autocapitalize", "off"); x.setAttribute("autocorrect", "off"); x.spellcheck = false;
    });
    var dernier = champs[champs.length - 1];
    var apres = dernier.closest(".ligne-champs") || dernier.closest(".champ") || dernier;
    var l = d.createElement("label"), c = d.createElement("input"), t = d.createElement("span");
    l.className = "case voir-mdp";
    c.type = "checkbox";
    t.textContent = ko ? "비밀번호 보기" : (champs.length > 1 ? "Afficher les mots de passe" : "Afficher le mot de passe");
    l.appendChild(c); l.appendChild(t);
    apres.insertAdjacentElement("afterend", l);
    c.addEventListener("change", function () {
      champs.forEach(function (x) { x.type = c.checked ? "text" : "password"; });
    });
    // À l'envoi, les champs redeviennent des mots de passe : le navigateur peut proposer de l'enregistrer
    f.addEventListener("submit", function () {
      champs.forEach(function (x) { x.type = "password"; });
    });
  });
})();

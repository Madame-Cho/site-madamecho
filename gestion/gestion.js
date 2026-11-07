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

  // Menu du compte (barre du haut) : il se referme quand on touche ailleurs ou qu'on appuie sur Échap
  d.addEventListener("click", function (e) {
    d.querySelectorAll("details.menu-compte[open]").forEach(function (m) { if (!m.contains(e.target)) m.removeAttribute("open"); });
  });
  d.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    d.querySelectorAll("details.menu-compte[open]").forEach(function (m) { m.removeAttribute("open"); var s = m.querySelector("summary"); if (s) s.focus(); });
  });

  // Imprimer (fiche de production)
  d.addEventListener("click", function (e) {
    if (e.target.closest("[data-imprimer]")) window.print();
  });

  // Confirmer avant une action qui compte ; un seul envoi par formulaire
  d.addEventListener("submit", function (e) {
    var f = e.target;
    if (f.id === "composer" || f.classList.contains("form-suggestion")) return;   // l'assistant a sa propre façon d'envoyer (plus bas)
    var q = f.getAttribute("data-confirmer");
    if (q && !window.confirm(q)) { e.preventDefault(); return; }
    var b = f.querySelector("button[type=submit]");
    if (b && f.method.toLowerCase() === "post") setTimeout(function () {
      b.disabled = true;
      // « L'assistant réfléchit… » : le bouton le dit pendant l'attente
      var attente = b.getAttribute("data-attente");
      if (attente) b.textContent = attente;
    }, 0);
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

  // Assistant, comme ChatGPT et Claude (demande d'Olivier, 07/11/2026) : la question s'affiche tout de suite, l'IA « réfléchit »
  // (étoile qui respire, mot qui scintille), puis la réponse prend sa place sans recharger la page. Entrée envoie sur ordinateur,
  // Maj + Entrée va à la ligne ; sur téléphone, Entrée va à la ligne et la flèche envoie. Sans fetch, le formulaire part comme avant.
  var occupe = false;
  var tactile = !!(window.matchMedia && window.matchMedia("(pointer: coarse)").matches);
  var calme = !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);
  function composer() { return d.getElementById("composer"); }
  function ajuster(t) { t.style.height = "auto"; t.style.height = Math.min(t.scrollHeight + 2, 240) + "px"; }
  function etatEnvoi() {
    var f = composer(); if (!f) return;
    var t = f.querySelector("textarea"), b = f.querySelector(".composer-envoyer");
    if (t && b) b.disabled = occupe || t.value.trim() === "";
  }
  function preparer() {
    var f = composer(); if (!f) return;
    ajuster(f.querySelector("textarea"));
    etatEnvoi();
  }
  function enBas(doux) { window.scrollTo({ top: d.documentElement.scrollHeight, behavior: doux && !calme ? "smooth" : "auto" }); }
  function envoyer(texte) {
    var f = composer();
    texte = (texte || "").trim();
    if (!f || occupe || texte === "") return;
    var t = f.querySelector("textarea");
    if (!window.fetch || !window.FormData || !window.DOMParser) { t.value = texte; HTMLFormElement.prototype.submit.call(f); return; }
    var donnees = new FormData(f);
    donnees.set("message", texte);
    var accueil = d.querySelector(".accueil-ia");
    if (accueil) accueil.parentNode.removeChild(accueil);
    var chat = d.getElementById("chat");
    if (!chat) { chat = d.createElement("div"); chat.id = "chat"; chat.className = "chat"; f.parentNode.insertBefore(chat, d.getElementById("bas") || f); }
    var avant = chat.querySelectorAll(".msg").length;
    var moi = d.createElement("div"), bulle = d.createElement("div");
    moi.className = "msg msg-moi nouveau"; bulle.className = "bulle-moi"; bulle.setAttribute("translate", "no"); bulle.textContent = texte;
    moi.appendChild(bulle); chat.appendChild(moi);
    var modele = d.getElementById("modele-attente");
    var attente = modele && modele.content ? modele.content.firstElementChild.cloneNode(true) : null;
    if (attente) { attente.classList.add("nouveau"); chat.appendChild(attente); }
    var vieux = d.querySelector(".page-assistant main > .flash");
    if (vieux) vieux.parentNode.removeChild(vieux);
    occupe = true;
    f.classList.add("occupe");
    t.value = ""; ajuster(t); etatEnvoi();
    enBas(true);
    var lent = setTimeout(function () {
      var r = attente && attente.querySelector(".reflechit");
      if (r && r.getAttribute("data-encore")) r.textContent = r.getAttribute("data-encore");
    }, 15000);
    fetch(f.getAttribute("action") || location.href, { method: "POST", body: donnees, credentials: "same-origin" })
      .then(function (r) { return r.text().then(function (h) { return { url: r.url, html: h }; }); })
      .then(function (x) {
        clearTimeout(lent);
        var doc = new DOMParser().parseFromString(x.html, "text/html");
        var neuf = doc.getElementById("contenu");
        if (!neuf || !doc.body.classList.contains("page-assistant")) { location.href = x.url; return; }   // session finie : on suit
        occupe = false;
        d.getElementById("contenu").innerHTML = neuf.innerHTML;
        var tous = d.querySelectorAll("#chat .msg");
        for (var i = avant; i < tous.length; i++) if (!tous[i].classList.contains("msg-moi")) tous[i].classList.add("nouveau");
        preparer();
        // La dernière question en haut de l'écran, la réponse dessous : on lit la réponse depuis son début
        var questions = d.querySelectorAll("#chat .msg-moi"), q = questions[questions.length - 1];
        if (q && !d.querySelector(".page-assistant main > .flash")) q.scrollIntoView({ block: "start", behavior: calme ? "auto" : "smooth" }); else enBas(true);
        var t2 = composer() && composer().querySelector("textarea");
        if (t2 && !tactile) t2.focus({ preventScroll: true });
      })
      .catch(function () {
        clearTimeout(lent);
        occupe = false;
        f.classList.remove("occupe");
        if (attente && attente.parentNode) attente.parentNode.removeChild(attente);
        var p = d.createElement("p");
        p.className = "msg-echec"; p.setAttribute("role", "alert");
        p.textContent = ko ? "보내지 못했어요. 연결을 확인하고 다시 보내 주세요." : "Pas envoyé : vérifiez la connexion, puis renvoyez.";
        chat.appendChild(p);
        if (moi.parentNode) moi.parentNode.removeChild(moi);
        t.value = texte; ajuster(t); etatEnvoi();
      });
  }
  d.addEventListener("submit", function (e) {
    var f = e.target;
    if (f.id === "composer") { e.preventDefault(); envoyer(f.querySelector("textarea").value); }
    else if (f.classList.contains("form-suggestion")) { e.preventDefault(); envoyer(f.querySelector("[name=message]").value); }
  });
  d.addEventListener("input", function (e) {
    if (e.target.tagName === "TEXTAREA" && e.target.closest("#composer")) { ajuster(e.target); etatEnvoi(); }
  });
  d.addEventListener("keydown", function (e) {
    var t = e.target;
    if (t.tagName !== "TEXTAREA" || !t.closest("#composer")) return;
    if (e.key !== "Enter" || e.shiftKey || e.altKey || e.ctrlKey || e.metaKey || e.isComposing || e.keyCode === 229 || tactile) return;
    e.preventDefault();   // pendant la saisie en coréen (isComposing), Entrée termine la syllabe : on n'envoie pas
    envoyer(t.value);
  });
  if (composer()) {
    preparer();
    if (d.getElementById("chat") && !location.hash) enBas(false);   // comme une messagerie : on arrive sur les derniers messages
  }

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

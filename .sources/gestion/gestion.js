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

  // Aperçu du mail au client, mis à jour pendant la saisie (phrase d'accueil, petit mot) : rien n'est enregistré
  var minuteur = null;
  d.addEventListener("input", function (e) {
    var z = e.target.closest("[data-apercu]");
    if (!z) return;
    var cadre = d.getElementById(z.getAttribute("data-apercu"));
    if (!cadre || !z.form) return;
    // Audit n° 4, A05 : « Envoyer » attend que l'aperçu soit à jour ; une réponse plus ancienne que la dernière saisie est ignorée
    var envoi = z.form.querySelector("button[type=submit]");
    if (envoi) envoi.disabled = true;
    clearTimeout(minuteur);
    var numero = (cadre._numero || 0) + 1;
    cadre._numero = numero;
    minuteur = setTimeout(function () {
      var donnees = new FormData(z.form);
      fetch(cadre.getAttribute("data-source"), { method: "POST", body: donnees, credentials: "same-origin" })
        .then(function (r) { return r.ok ? r.text() : null; })
        .then(function (h) { if (numero !== cadre._numero) return; if (h) cadre.innerHTML = h; if (envoi) envoi.disabled = false; })
        .catch(function () { if (numero === cadre._numero && envoi) envoi.disabled = false; });
    }, 400);
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
    interfaceVocale();
    flecheBas();
  }
  // La flèche « retour en bas » (demande d'Olivier, 09/11/2026, comme dans Claude) : elle apparaît dès qu'on a remonté la conversation
  // d'un peu plus d'un écran à moitié, et ramène d'un toucher aux derniers messages et à la zone de saisie (téléphone, tablette, ordinateur)
  function flecheBas() {
    var b = d.getElementById("vers-le-bas"); if (!b) return;
    var reste = d.documentElement.scrollHeight - (window.scrollY || window.pageYOffset || 0) - window.innerHeight;
    var montrer = !!d.getElementById("chat") && reste > Math.max(160, window.innerHeight * 0.4);
    if (montrer) { b.hidden = false; b.classList.add("visible"); }
    else { b.classList.remove("visible"); }
  }
  var flecheEnAttente = false;
  function flecheBasBientot() {
    if (flecheEnAttente) return; flecheEnAttente = true;
    (window.requestAnimationFrame || setTimeout)(function () { flecheEnAttente = false; flecheBas(); });
  }
  window.addEventListener("scroll", flecheBasBientot, { passive: true });
  window.addEventListener("resize", flecheBasBientot);
  d.addEventListener("click", function (e) {
    var b = e.target.closest && e.target.closest("#vers-le-bas"); if (!b) return;
    e.preventDefault();
    enBas(true);
    b.classList.remove("visible");
    // Sur ordinateur, le curseur se met dans la zone de saisie ; sur téléphone et tablette, non : le clavier cacherait la conversation
    var t = composer() && composer().querySelector("textarea");
    if (t && !tactile) t.focus({ preventScroll: true });
  });
  function enBas(doux) { window.scrollTo({ top: d.documentElement.scrollHeight, behavior: doux && !calme ? "smooth" : "auto" }); }
  // La page de l'assistant renvoyée par le serveur remplace la conversation affichée ; null si ce n'est pas elle (session finie, erreur)
  function remplacer(html) {
    var doc = new DOMParser().parseFromString(html, "text/html");
    var neuf = doc.getElementById("contenu");
    if (!neuf || !doc.body.classList.contains("page-assistant")) return null;
    d.getElementById("contenu").innerHTML = neuf.innerHTML;
    return doc;
  }
  function texteFlash() { var f = d.querySelector(".page-assistant main > .flash"); return f ? (f.innerText || f.textContent || "").trim() : ""; }
  // opts.vocal : la question a été dite (le serveur répond court) ; opts.fin(texte) reçoit ce qu'il faut lire à voix haute
  function envoyer(texte, opts) {
    opts = opts || {};
    var f = composer();
    texte = (texte || "").trim();
    if (!f || occupe || texte === "") { if (opts.fin) opts.fin(""); return; }
    var t = f.querySelector("textarea");
    if (!window.fetch || !window.FormData || !window.DOMParser) { t.value = texte; HTMLFormElement.prototype.submit.call(f); return; }
    var donnees = new FormData(f);
    donnees.set("message", texte);
    if (opts.vocal) donnees.set("vocal", "1");
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
        occupe = false;
        if (!remplacer(x.html)) { arreterVocal(""); location.href = x.url; return; }   // session finie : on suit
        var tous = d.querySelectorAll("#chat .msg"), dire = [];
        for (var i = avant; i < tous.length; i++) {
          if (tous[i].classList.contains("msg-moi")) continue;
          tous[i].classList.add("nouveau");
          var ia = tous[i].querySelector(".texte-ia");
          if (ia) dire.push(ia.innerText || ia.textContent || "");
        }
        preparer();
        // La dernière question en haut de l'écran, la réponse dessous : on lit la réponse depuis son début
        var questions = d.querySelectorAll("#chat .msg-moi"), q = questions[questions.length - 1];
        var flash = texteFlash();
        // En mode vocal, la réponse est lue : on va en bas, pour que les boutons d'une modification préparée soient à portée de doigt
        if (q && !flash && !opts.vocal) q.scrollIntoView({ block: "start", behavior: calme ? "auto" : "smooth" }); else enBas(true);
        var t2 = composer() && composer().querySelector("textarea");
        if (t2 && !tactile && !opts.vocal) t2.focus({ preventScroll: true });
        if (opts.fin) opts.fin(flash || dire.join(" "));
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
        if (opts.fin) opts.fin(p.textContent, true);
      });
  }
  d.addEventListener("submit", function (e) {
    var f = e.target;
    if (f.id === "composer") { e.preventDefault(); envoyer(f.querySelector("textarea").value); }
    else if (f.classList.contains("form-suggestion")) { e.preventDefault(); envoyer(f.querySelector("[name=message]").value); }
    // « Confirmer » ou « Ne pas faire » sous une modification préparée, en mode vocal : sans recharger la page (la voix continue)
    else if (vocal.actif && (f.classList.contains("form-proposition") || f.classList.contains("form-proposition-non"))) { e.preventDefault(); repondreProposition(f); }
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

  // Le mode vocal (demande d'Olivier, 07/11/2026 : Youngmi les mains prises). Un toucher sur le micro ; elle parle ; la question part
  // comme un message écrit ; la réponse est lue à voix haute ; puis l'assistant réécoute. « Stop » (ou « 그만 ») ou le bouton Arrêter
  // le termine. La reconnaissance et la voix sont celles du navigateur (Chrome : Google ; Safari : Apple), dans la langue de l'écran.
  // Une modification préparée se confirme toujours d'un toucher sur « Confirmer ».
  var Reco = window.SpeechRecognition || window.webkitSpeechRecognition;
  var synth = window.speechSynthesis;
  var vocalPossible = !!(Reco && synth && window.SpeechSynthesisUtterance && window.fetch && window.FormData && window.DOMParser);
  var vocal = { actif: false, reco: null, verrou: null, vides: 0, etat: "", texte: "", parole: 0 };
  var MOTS_STOP = ["stop", "arrete", "arreter", "arrete toi", "c est tout", "merci c est tout", "termine", "fin", "au revoir", "그만", "그만해", "그만해요", "끝", "끝내", "멈춰", "종료", "됐어", "됐어요"];
  function barre() { return d.getElementById("vocal"); }
  function libelle(k) { var b = barre(); return b ? (b.getAttribute("data-" + k) || "") : ""; }
  // Après chaque réponse, la conversation est remplacée : la barre et le micro sont remis dans l'état du moment
  function interfaceVocale() {
    var m = d.getElementById("micro"), b = barre();
    if (m) {
      m.hidden = !vocalPossible;
      m.classList.toggle("actif", vocal.actif);
      m.setAttribute("aria-pressed", vocal.actif ? "true" : "false");
    }
    if (!b) return;
    b.hidden = !vocal.actif;
    b.setAttribute("data-etat", vocal.etat);
    var e = d.getElementById("vocal-etat"), t = d.getElementById("vocal-texte");
    if (e) e.textContent = vocal.etat ? libelle(vocal.etat) : "";
    if (t) t.textContent = vocal.texte;
  }
  function etatVocal(e, texte) { vocal.etat = e; if (texte !== undefined) vocal.texte = texte; interfaceVocale(); }
  function normaliser(s) {
    s = (s || "").toLowerCase();
    if (s.normalize) s = s.normalize("NFD").replace(/[\u0300-\u036f]/g, "").normalize("NFC");   // sans accents ; le hangeul est recomposé
    return s.replace(/[.,!?;:'’"«»…\-]+/g, " ").replace(/\s+/g, " ").trim();
  }
  function estStop(s) { return MOTS_STOP.indexOf(normaliser(s)) !== -1; }
  function message(texte) {
    if (!texte) return;
    var chat = d.getElementById("chat") || composer();
    if (!chat) return;
    var p = d.createElement("p");
    p.className = "msg-echec"; p.setAttribute("role", "alert"); p.textContent = texte;
    if (chat.id === "chat") chat.appendChild(p); else chat.parentNode.insertBefore(p, chat);
  }
  // La voix : une voix du système dans la langue du texte (coréen s'il contient du hangeul), de préférence sur l'appareil
  function voixPour(lang) {
    var vs = synth.getVoices ? synth.getVoices() : [], p = lang.slice(0, 2), choix = null;
    for (var i = 0; i < vs.length; i++) {
      if ((vs[i].lang || "").replace("_", "-").toLowerCase().indexOf(p) !== 0) continue;
      if (!choix || (vs[i].localService && !choix.localService)) choix = vs[i];
    }
    return choix;
  }
  function pourLaVoix(t) {
    return (t || "").replace(/\s*·\s*/g, ", ").replace(/https?:\/\/\S+/g, "").replace(/[*#•]/g, "").replace(/\s*\n+\s*/g, ". ").replace(/\s+/g, " ").trim();
  }
  function morceaux(t) {
    var phrases = pourLaVoix(t).match(/[^.!?。？！]+[.!?。？！]*/g) || [], out = [], cur = "";
    phrases.forEach(function (p) { p = p.trim(); if (!p) return; if ((cur + " " + p).length > 200 && cur) { out.push(cur); cur = p; } else cur = (cur ? cur + " " : "") + p; });
    if (cur) out.push(cur);
    return out;
  }
  function dire(texte, ensuite) {
    var parts = morceaux(texte), i = 0, numero = ++vocal.parole;
    try { synth.cancel(); } catch (x) {}
    if (!parts.length) { if (ensuite) ensuite(); return; }
    etatVocal("parle");
    (function suivant() {
      if (numero !== vocal.parole) return;     // une autre lecture a pris la place, ou le mode vocal est arrêté
      if (!vocal.actif && ensuite) return;     // arrêté pendant la lecture
      if (i >= parts.length) { if (ensuite) ensuite(); return; }
      var u = new SpeechSynthesisUtterance(parts[i++]);
      u.lang = /[\uac00-\ud7af]/.test(u.text) ? "ko-KR" : "fr-FR";
      var v = voixPour(u.lang);
      if (v) u.voice = v;
      var fini = false;
      // Certains navigateurs n'annoncent jamais la fin d'une phrase : on n'attend pas plus que sa durée probable
      var garde = setTimeout(function () { if (!fini) { fini = true; try { synth.cancel(); } catch (x) {} suivant(); } }, 4000 + u.text.length * 110);
      u.onend = u.onerror = function () { if (fini) return; fini = true; clearTimeout(garde); setTimeout(suivant, 60); };
      synth.speak(u);
    })();
  }
  function ecouter() {
    if (!vocal.actif) return;
    var b = barre(), r;
    try { r = new Reco(); } catch (x) { arreterVocal(libelle("reseau")); return; }
    r.lang = (b && b.getAttribute("data-langue")) || (ko ? "ko-KR" : "fr-FR");
    r.interimResults = true; r.continuous = false; r.maxAlternatives = 1;
    var final = "", erreur = "";
    r.onresult = function (e) {
      var f = "", provisoire = "";
      for (var i = 0; i < e.results.length; i++) { if (e.results[i].isFinal) f += e.results[i][0].transcript; else provisoire += e.results[i][0].transcript; }
      if (f) final = f;
      etatVocal("ecoute", (f + " " + provisoire).trim());
    };
    r.onerror = function (e) { erreur = e.error || ""; };
    r.onend = function () {
      vocal.reco = null;
      if (!vocal.actif || erreur === "aborted") return;   // arrêté exprès (bouton, « Confirmer ») : on ne réécoute pas tout seul
      if (erreur === "not-allowed" || erreur === "service-not-allowed" || erreur === "audio-capture") { arreterVocal(libelle("refus")); return; }
      if (erreur === "network") { arreterVocal(libelle("reseau")); return; }
      var dit = final.trim();
      if (!dit) {
        // Rien entendu : on réécoute, puis on s'arrête après trois silences (pas de micro ouvert sans fin)
        if (++vocal.vides >= 3) { arreterVocal(libelle("pause")); return; }
        setTimeout(ecouter, 250);
        return;
      }
      vocal.vides = 0;
      if (estStop(dit)) { var a = libelle("aurevoir"); arreterVocal(""); dire(a, null); return; }
      etatVocal("reflechit", dit);
      envoyer(dit, { vocal: true, fin: function (reponse) {
        if (!vocal.actif) return;
        vocal.texte = "";
        if (reponse) dire(reponse, ecouter); else ecouter();
      } });
    };
    vocal.reco = r;
    etatVocal("ecoute", "");
    try { r.start(); } catch (x) { setTimeout(ecouter, 600); }
  }
  function demarrerVocal() {
    if (!vocalPossible || vocal.actif) return;
    vocal.actif = true; vocal.vides = 0; vocal.texte = "";
    // L'écran reste allumé pendant qu'on parle (si le navigateur le permet)
    if (navigator.wakeLock && navigator.wakeLock.request) navigator.wakeLock.request("screen").then(function (l) { vocal.verrou = l; }, function () {});
    etatVocal("parle");
    // Une première phrase dite pendant le toucher : le navigateur autorise ensuite la voix sans nouveau toucher
    dire(libelle("bonjour"), ecouter);
  }
  function arreterVocal(texte) {
    var etait = vocal.actif;
    vocal.actif = false; vocal.etat = ""; vocal.texte = ""; vocal.parole++;
    if (vocal.reco) { try { vocal.reco.abort(); } catch (x) {} vocal.reco = null; }
    try { synth && synth.cancel(); } catch (x) {}
    if (vocal.verrou) { try { vocal.verrou.release(); } catch (x) {} vocal.verrou = null; }
    interfaceVocale();
    if (etait) message(texte);
  }
  // En mode vocal, « Confirmer » et « Ne pas faire » partent sans recharger la page ; la gestion dit ce qu'elle a fait
  function repondreProposition(f) {
    vocal.parole++;   // la lecture en cours s'arrête, sans réécouter derrière
    try { synth.cancel(); } catch (x) {}
    if (vocal.reco) { try { vocal.reco.abort(); } catch (x) {} }
    var non = f.classList.contains("form-proposition-non"), b = f.querySelector("button[type=submit]");
    if (b) b.disabled = true;
    etatVocal("reflechit", "");
    fetch(f.getAttribute("action") || location.href, { method: "POST", body: new FormData(f), credentials: "same-origin" })
      .then(function (r) { return r.text().then(function (h) { return { url: r.url, html: h }; }); })
      .then(function (x) {
        // Pas la page de l'assistant : la session est finie (on va à la connexion), ou la gestion a ouvert un formulaire à corriger
        // (la carte l'explique : on recharge la conversation). Le formulaire n'est jamais renvoyé une deuxième fois.
        if (!remplacer(x.html)) { arreterVocal(""); if (/[?&]p=connexion/.test(x.url)) location.href = x.url; else location.reload(); return; }
        preparer();
        enBas(true);
        dire(non ? libelle("rien") : (texteFlash() || libelle("fait")), ecouter);
      })
      .catch(function () { if (b) b.disabled = false; dire(libelle("echec"), ecouter); });
  }
  d.addEventListener("click", function (e) {
    if (e.target.closest("#micro")) { e.preventDefault(); if (vocal.actif) arreterVocal(""); else demarrerVocal(); }
    else if (e.target.closest("#vocal-stop")) { e.preventDefault(); arreterVocal(""); }
  });
  // On quitte l'écran (autre application, écran éteint) : le micro se ferme
  d.addEventListener("visibilitychange", function () { if (d.hidden && vocal.actif) arreterVocal(""); });
  if (vocalPossible && synth.getVoices) { synth.getVoices(); if ("onvoiceschanged" in synth) synth.onvoiceschanged = function () { synth.getVoices(); }; }

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

  // Bandeaux de message (vert ou rouge) : une croix pour les fermer une fois lus (demande d'Olivier du 07/11/2026).
  d.querySelectorAll(".flash").forEach(function (b) {
    var x = d.createElement("button"); x.type = "button"; x.className = "flash-fermer";
    x.setAttribute("aria-label", ko ? "닫기" : "Fermer"); x.title = ko ? "닫기" : "Fermer"; x.textContent = "×";
    x.addEventListener("click", function () { b.remove(); });
    b.appendChild(x);
  });

  // Sous chaque zone de saisie marquée (note d'échange, phrase d'accueil, petit mot, message au client) : « Dicter » (la voix du
  // navigateur, dans la langue de l'écran) et « Corriger mon français » (l'assistant propose un texte corrigé, avec la traduction
  // coréenne ; Youngmi touche « Utiliser » ou garde le sien). Rien n'est inscrit ni envoyé sans son toucher sur « Ajouter » ou « Envoyer ».
  // Demande d'Olivier du 07/11/2026.
  var T = ko ? { dicter: "프랑스어로 말하기", dicterKo: "한국어로 말하기", hangul: "한국어가 들어 있어요. 고객과 기록은 프랑스어로 써요. ‘프랑스어 고치기’를 눌러 프랑스어로 바꿔 주세요.", ecoute: "듣는 중… (다시 누르면 멈춤)", corriger: "프랑스어 고치기", attente: "고치는 중…", utiliser: "이 문장 쓰기", garder: "내 문장 그대로", sens: "뜻 (한국어):", erreur: "고치지 못했어요: ", vide: "먼저 문장을 쓰거나 말해 주세요." }
             : { dicter: "Dicter en français", dicterKo: "Parler en coréen", hangul: "Le texte contient du coréen. Les mails et les notes sont en français : touchez « Corriger mon français » pour le traduire.", ecoute: "J'écoute… (touchez pour arrêter)", corriger: "Corriger mon français", attente: "Correction…", utiliser: "Utiliser ce texte", garder: "Garder le mien", sens: "Le sens, en coréen :", erreur: "La correction n'a pas marché : ", vide: "Écrivez ou dictez d'abord votre texte." };
  var ZONES = { texte: "note", accueil: "accueil", mot: "mot", texte_mail: "message" };
  var urlCorriger = d.body.getAttribute("data-corriger"), jeton = d.body.getAttribute("data-jeton");
  function changer(z, v) { z.value = v; z.dispatchEvent(new Event("input", { bubbles: true })); }
  d.querySelectorAll("textarea[data-aide-saisie]").forEach(function (z) {
    if (!Reco && !urlCorriger) return;
    var barreA = d.createElement("div"); barreA.className = "aide-saisie";
    var boite = d.createElement("div"); boite.className = "correction"; boite.hidden = true;
    var lab = z.closest(".champ") || z;
    lab.insertAdjacentElement("afterend", barreA);
    barreA.insertAdjacentElement("afterend", boite);
    // Deux façons de dicter (demande d'Olivier, 07/11/2026) : en français, ou en coréen, et l'assistant propose aussitôt la phrase en français
    var bc = null;
    function drapeau(l) { var t = d.getElementById("drapeau-" + l); return t ? t.innerHTML : ""; }
    function bouton_dicter(langue, libelle, apres) {
      var b = d.createElement("button"); b.type = "button"; b.className = "btn secondaire petit-btn";
      // 🎤 + drapeau de la langue parlée (+ → drapeau français quand l'assistant traduit)
      var dessin = "🎤 " + drapeau(langue === "ko-KR" ? "ko" : "fr") + (langue === "ko-KR" ? " → " + drapeau("fr") : "") + " ";
      function etiquette() { b.innerHTML = dessin; b.appendChild(d.createTextNode(libelle)); }
      etiquette();
      var reco = null;
      b.addEventListener("click", function () {
        if (reco) { reco.stop(); return; }
        try { reco = new Reco(); } catch (x) { return; }
        reco.lang = langue; reco.interimResults = true; reco.continuous = true;
        var avant = z.value.replace(/\s+$/, ""), sep = avant ? (/\n$/.test(z.value) ? "" : " ") : "", dit = false;
        reco.onresult = function (e) {
          var t = "";
          for (var i = 0; i < e.results.length; i++) t += e.results[i][0].transcript;
          if (t.trim()) dit = true;
          changer(z, avant + sep + t.trim());
        };
        reco.onend = function () { reco = null; etiquette(); b.classList.remove("actif"); if (dit && apres) apres(); };
        reco.onerror = function () {};
        b.textContent = "■ " + T.ecoute; b.classList.add("actif");
        reco.start();
      });
      barreA.appendChild(b);
    }
    if (Reco) {
      bouton_dicter("fr-FR", T.dicter, null);
      if (urlCorriger) bouton_dicter("ko-KR", T.dicterKo, function () { if (bc) bc.click(); });
    }
    if (urlCorriger) {
      bc = d.createElement("button"); bc.type = "button"; bc.className = "btn secondaire petit-btn"; bc.textContent = "✎ " + T.corriger;
      bc.addEventListener("click", function () {
        var texte = z.value.trim();
        boite.hidden = false; boite.textContent = "";
        if (!texte) { boite.textContent = T.vide; return; }
        boite.textContent = T.attente; bc.disabled = true;
        var f = new FormData(); f.append("_jeton", jeton); f.append("texte", texte); f.append("zone", ZONES[z.name] || "message");
        fetch(urlCorriger, { method: "POST", body: f, credentials: "same-origin" })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            boite.textContent = "";
            if (!j || j.erreur || !j.fr) { boite.textContent = T.erreur + ((j && j.erreur) || ""); return; }
            var p = d.createElement("p"); p.className = "corrige"; p.setAttribute("translate", "no"); p.lang = "fr"; p.textContent = j.fr; boite.appendChild(p);
            if (j.ko) { var s = d.createElement("p"); s.className = "aide"; s.setAttribute("translate", "no"); s.lang = "ko"; s.textContent = T.sens + " " + j.ko; boite.appendChild(s); }
            if (j.remarque) { var rq = d.createElement("p"); rq.className = "aide"; rq.lang = "ko"; rq.textContent = j.remarque; boite.appendChild(rq); }
            var act = d.createElement("div"); act.className = "actions";
            var u = d.createElement("button"); u.type = "button"; u.className = "btn principal"; u.textContent = T.utiliser;
            u.addEventListener("click", function () { changer(z, j.fr); boite.hidden = true; z.focus(); });
            var g = d.createElement("button"); g.type = "button"; g.className = "btn discret"; g.textContent = T.garder;
            g.addEventListener("click", function () { boite.hidden = true; });
            act.appendChild(u); act.appendChild(g); boite.appendChild(act);
          })
          .catch(function () { boite.textContent = T.erreur; })
          .then(function () { bc.disabled = false; });
      });
      barreA.appendChild(bc);
    }
  });

  // Rien en coréen ne part au client ni dans les notes : on arrête l'envoi et on propose « Corriger mon français »
  d.addEventListener("submit", function (e) {
    var zs = e.target.querySelectorAll ? e.target.querySelectorAll("textarea[data-aide-saisie]") : [];
    for (var i = 0; i < zs.length; i++) {
      if (/[\u3131-\u318E\uAC00-\uD7A3]/.test(zs[i].value)) {
        e.preventDefault(); e.stopImmediatePropagation();
        alert(T.hangul); zs[i].focus(); return;
      }
    }
  }, true);

  // « Répondre » sous une réponse de client, ou un lien vers #mail-avis : ouvrir le bloc replié et y aller
  function ouvrir(id) {
    var el = id && d.getElementById(id);
    if (!el) return false;
    for (var p = el; p; p = p.parentElement) if (p.tagName === "DETAILS") p.open = true;
    el.scrollIntoView({ block: "start" });
    var t = el.querySelector("textarea, input:not([type=hidden])");
    if (t) setTimeout(function () { t.focus(); }, 200);
    return true;
  }
  d.addEventListener("click", function (e) {
    var a = e.target.closest("[data-ouvrir]");
    if (a && ouvrir(a.getAttribute("data-ouvrir"))) e.preventDefault();
  });
  if (location.hash.length > 1 && /^#mail-|^#ecrire$/.test(location.hash)) ouvrir(location.hash.slice(1));
})();

/* Hygiène (spécification 004, lot 2) : la case « qu'avez-vous fait ? » quand la température sort de la plage,
   la date limite d'une fournée reprise de la durée fixée, la photo d'étiquette réduite avant l'envoi. */
(function () {
  var d = document;
  var lire = function (v) { v = (v || "").replace(",", ".").replace("−", "-").replace(/\s|°|C/g, ""); return /^-?\d{1,2}(\.\d)?$/.test(v) ? parseFloat(v) : null; };
  var verifier = function (i) {
    var bloc = d.getElementById(i.getAttribute("data-plage-action") + "-bloc"), z = d.getElementById(i.getAttribute("data-plage-action"));
    if (!bloc) return;
    var t = lire(i.value), mi = i.getAttribute("data-plage-min"), ma = i.getAttribute("data-plage-max");
    var hors = t !== null && ((mi !== null && t < parseFloat(mi)) || (ma !== null && t > parseFloat(ma)));
    bloc.hidden = !hors && !(z && z.value);
    if (z) z.required = hors;
  };
  d.addEventListener("input", function (e) { if (e.target.matches && e.target.matches("[data-plage-action]")) verifier(e.target); });
  Array.prototype.forEach.call(d.querySelectorAll("[data-plage-action]"), verifier);
  var dlc = function () {
    var s = d.getElementById("f-produit-fournee"), c = d.getElementById("f-dlc-fournee");
    if (!s || !c) return;
    var j; try { j = JSON.parse(s.getAttribute("data-durees") || "{}")[s.value]; } catch (x) { j = undefined; }
    if (c.getAttribute("data-tape") === "1") return;
    // Gâteau sans durée fixée : la date proposée pour le gâteau d'avant s'efface (jamais devinée)
    if (j === undefined) { if (c.getAttribute("data-auto") === "1") { c.value = ""; c.removeAttribute("data-auto"); } return; }
    var t = new Date((s.getAttribute("data-jour") || "") + "T12:00:00"); t.setDate(t.getDate() + j);
    c.value = t.getFullYear() + "-" + ("0" + (t.getMonth() + 1)).slice(-2) + "-" + ("0" + t.getDate()).slice(-2);
    c.setAttribute("data-auto", "1");
  };
  d.addEventListener("change", function (e) {
    if (e.target.id === "f-produit-fournee") dlc();
    if (e.target.id === "f-dlc-fournee") { e.target.setAttribute("data-tape", "1"); e.target.removeAttribute("data-auto"); }
  });
  // Une date déjà là (correction, formulaire refusé) n'est jamais remplacée à l'ouverture
  var c0 = d.getElementById("f-dlc-fournee");
  if (c0 && c0.value) c0.setAttribute("data-tape", "1"); else dlc();
  // Une photo de téléphone (3 à 6 Mo) devient un JPEG d'environ 1600 px avant l'envoi ; sans ce geste, l'original part tel quel
  d.addEventListener("change", function (e) {
    var i = e.target;
    if (!i.matches || !i.matches("input[type=file][data-reduire]") || !i.files || !i.files[0] || !window.DataTransfer || !window.createImageBitmap) return;
    var max = parseInt(i.getAttribute("data-reduire"), 10) || 1600, f = i.files[0];
    if (f.size < 900000 || !/^image\//.test(f.type)) return;
    createImageBitmap(f).then(function (b) {
      var r = Math.min(1, max / Math.max(b.width, b.height)), c = d.createElement("canvas");
      c.width = Math.round(b.width * r); c.height = Math.round(b.height * r);
      c.getContext("2d").drawImage(b, 0, 0, c.width, c.height);
      c.toBlob(function (blob) {
        if (!blob) return;
        var t = new DataTransfer(); t.items.add(new File([blob], "etiquette.jpg", { type: "image/jpeg" })); i.files = t.files;
      }, "image/jpeg", 0.82);
    }).catch(function () {});
  });
})();

/* Étiquettes (lot 3) : si le texte déborde d'une seule étiquette, l'impression est bloquée (rien n'est coupé ; audit n° 8, constat 14) */
(function () {
  var l = document.querySelectorAll(".planche .etiquette"), e = null;
  for (var i = 0; i < l.length; i++) if (l[i].scrollHeight > l[i].clientHeight + 1 || l[i].scrollWidth > l[i].clientWidth + 1) { e = l[i]; break; }
  if (!e) return;
  document.body.classList.add("etiquettes-bloquees");
  document.querySelectorAll("[data-imprimer]").forEach(function (b) { b.disabled = true; });
  var p = document.createElement("p");
  p.className = "erreur etiquettes-trop ecran-seul";
  p.textContent = document.documentElement.getAttribute("lang") === "ko" ? "이 라벨에는 글이 너무 길어요. 재료 목록을 줄이거나 A4 시트를 고르세요." : "Texte trop long pour cette étiquette : raccourcissez la liste d'ingrédients ou choisissez la planche A4.";
  e.parentNode.parentNode.insertBefore(p, e.parentNode);
})();

/* Pastilles (FR-041) : le nombre rouge sur l'icône de l'application, quand le téléphone ou l'ordinateur le permet */
(function () {
  var n = parseInt(document.body.getAttribute("data-pastilles") || "", 10);
  if (isNaN(n) || !("setAppBadge" in navigator)) return;
  try { (n > 0 ? navigator.setAppBadge(n) : navigator.clearAppBadge()).catch(function () {}); } catch (e) {}
})();

// Sous-onglets plus larges que l'écran : l'onglet ouvert reste visible (Obligations, lot 4)
(function () {
  document.querySelectorAll(".sous-onglets").forEach(function (n) {
    var a = n.querySelector("a[aria-current]");
    if (a && n.scrollWidth > n.clientWidth) n.scrollLeft += a.getBoundingClientRect().left - n.getBoundingClientRect().left - (n.clientWidth - a.offsetWidth) / 2;
  });
})();

/* Son des nouvelles commandes (demande d'Olivier du 12/11/2026 : « chaque commande est une bonne nouvelle pour Youngmi »).
 * Toutes les 30 s, la page demande la dernière commande du site (?p=nouvelles). Une nouvelle : un petit carillon joyeux et un bandeau.
 * Les navigateurs ne jouent un son qu'après un premier toucher sur la page : sans lui, le bandeau s'affiche tout de suite et le son
 * attend le toucher suivant. Plusieurs onglets ouverts : un seul joue le son. Le réglage est gardé sur l'appareil (Paramètres). */
(function () {
  var d = document, b = d.body, url = b.getAttribute("data-son-url");
  var ko = d.documentElement.getAttribute("lang") === "ko";
  var CLE = "mc-son", CLE_DERNIER = "mc-son-dernier";
  function lire(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function ecrire(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function actif() { return lire(CLE) !== "0"; }

  // Le carillon : quatre notes montantes (do, mi, sol, do), douces comme une clochette, sans fichier à charger
  var ctx = null, enAttente = false;
  function contexte() {
    var A = window.AudioContext || window.webkitAudioContext;
    if (!A) return null;
    if (!ctx) { try { ctx = new A(); } catch (e) { return null; } }
    return ctx;
  }
  function carillon() {
    var c = contexte();
    if (!c || c.state !== "running") return false;
    var t0 = c.currentTime + 0.03, sortie = c.createGain();
    sortie.gain.value = 0.9; sortie.connect(c.destination);
    [1046.5, 1318.5, 1568, 2093].forEach(function (f, i) {
      var t = t0 + i * 0.09, duree = i === 3 ? 1.1 : 0.55;
      [[1, 0.32, "sine"], [2.01, 0.06, "sine"], [3, 0.03, "triangle"]].forEach(function (h) {   // la note et deux harmoniques légères
        var o = c.createOscillator(), g = c.createGain();
        o.type = h[2]; o.frequency.value = f * h[0];
        g.gain.setValueAtTime(0.0001, t);
        g.gain.exponentialRampToValueAtTime(h[1], t + 0.012);
        g.gain.exponentialRampToValueAtTime(0.0001, t + duree);
        o.connect(g); g.connect(sortie); o.start(t); o.stop(t + duree + 0.05);
      });
    });
    return true;
  }
  // Le premier toucher (ou une touche) débloque le son ; un son en attente se joue alors
  function debloquer() {
    var c = contexte();
    if (!c) return;
    var suite = function () { if (enAttente && c.state === "running") { enAttente = false; carillon(); } };
    if (c.state !== "running" && c.resume) c.resume().then(suite).catch(function () {}); else suite();
  }
  ["pointerdown", "keydown", "touchend"].forEach(function (e) { d.addEventListener(e, debloquer, { capture: true, passive: true }); });
  function sonner() {
    if (!actif()) return;
    if (!carillon()) { enAttente = true; var c = contexte(); if (c && c.resume) c.resume().then(function () { if (enAttente && carillon()) enAttente = false; }).catch(function () {}); }
  }

  // Paramètres › Son des nouvelles commandes
  var caseSon = d.querySelector("[data-son-reglage]");
  if (caseSon) {
    caseSon.checked = actif();
    caseSon.addEventListener("change", function () { ecrire(CLE, caseSon.checked ? "1" : "0"); if (caseSon.checked) { debloquer(); carillon(); } });
  }
  var tester = d.querySelector("[data-son-tester]");
  if (tester) tester.addEventListener("click", function () {
    var c = contexte();
    if (!c) return;
    if (c.state === "running") carillon(); else c.resume().then(carillon).catch(function () {});
  });

  if (!url) return;   // pas connecté
  // Le bandeau, en bas de l'écran, au-dessus des onglets
  var bandeau = null, minuterie = 0, titre = d.title;
  function montrer(r) {
    if (!bandeau) {
      bandeau = d.createElement("div");
      bandeau.className = "son-bandeau"; bandeau.setAttribute("role", "status");
      d.body.appendChild(bandeau);
    }
    bandeau.textContent = "";
    var a = d.createElement("a"); a.href = r.url || "?p=commandes";
    var fete = d.createElement("span"); fete.className = "son-fete"; fete.setAttribute("aria-hidden", "true"); fete.textContent = "🎉";
    var txt = d.createElement("span"); txt.className = "son-texte";
    var l1 = d.createElement("b");
    l1.textContent = r.n > 1 ? (ko ? "새 주문 " + r.n + "건!" : r.n + " nouvelles commandes !") : (ko ? "새 주문이 들어왔어요!" : "Nouvelle commande !");
    txt.appendChild(l1);
    if (r.n === 1 && r.ref) {
      var l2 = d.createElement("span"); l2.setAttribute("translate", "no");
      l2.textContent = r.ref + (r.qui ? " · " + r.qui : "");
      txt.appendChild(l2);
    }
    var voir = d.createElement("span"); voir.className = "son-voir"; voir.textContent = ko ? "보기" : "Voir";
    a.appendChild(fete); a.appendChild(txt); a.appendChild(voir);
    var x = d.createElement("button"); x.type = "button"; x.className = "son-fermer"; x.setAttribute("aria-label", ko ? "닫기" : "Fermer"); x.textContent = "×";
    x.addEventListener("click", cacher);
    bandeau.appendChild(a); bandeau.appendChild(x);
    bandeau.classList.remove("son-sortie"); void bandeau.offsetWidth; bandeau.classList.add("son-entree");
    d.title = (ko ? "🎉 새 주문 · " : "🎉 Nouvelle commande · ") + titre;
    clearTimeout(minuterie); minuterie = setTimeout(cacher, 120000);   // deux minutes, puis il s'efface
  }
  function cacher() { if (bandeau) { bandeau.classList.remove("son-entree"); bandeau.classList.add("son-sortie"); } d.title = titre; }

  // La dernière commande connue : celle de la page affichée, ou celle déjà fêtée sur cet appareil si elle est plus ancienne
  // (une commande arrivée pendant que la gestion était fermée est fêtée à l'ouverture suivante)
  var page = parseInt(b.getAttribute("data-son-dernier") || "0", 10) || 0;
  var memo = parseInt(lire(CLE_DERNIER) || "", 10);
  var connu = isNaN(memo) ? page : Math.min(memo, page);
  if (isNaN(memo)) ecrire(CLE_DERNIER, String(page));
  var pas = (parseInt(b.getAttribute("data-son-intervalle") || "30", 10) || 30) * 1000, arret = false, enCours = false;

  function demander() {
    if (arret || enCours) return;
    enCours = true;
    fetch(url + "&depuis=" + connu, { credentials: "same-origin", cache: "no-store", headers: { "Accept": "application/json" } })
      .then(function (r) { if (r.status === 401) { arret = true; return null; } return r.ok ? r.json() : null; })
      .then(function (r) {
        enCours = false;
        if (!r || typeof r.dernier !== "number") return;
        if (r.n > 0 && r.dernier > connu) {
          var deja = parseInt(lire(CLE_DERNIER) || "0", 10) || 0;
          ecrire(CLE_DERNIER, String(r.dernier));
          montrer(r);
          if (r.dernier > deja) sonner();   // un autre onglet l'a déjà fait sonner : le bandeau seulement
        } else if (r.dernier > parseInt(lire(CLE_DERNIER) || "0", 10)) ecrire(CLE_DERNIER, String(r.dernier));
        connu = Math.max(connu, r.dernier);
      })
      .catch(function () { enCours = false; });
  }
  if (connu < page) demander();
  setInterval(function () { if (!d.hidden || !("ontouchstart" in window)) demander(); }, pas);
  d.addEventListener("visibilitychange", function () { if (!d.hidden) demander(); });
})();

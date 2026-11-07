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
    interfaceVocale();
  }
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
})();

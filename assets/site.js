/* Madame Cho, site v2 : « Ma demande » gardée dans le navigateur (gâteaux, tailles, quantités ; ni nom ni message),
   pages des gâteaux, formulaire de demande sans engagement. Le site marche aussi sans ce script (formulaire simple). */
(function () {
  'use strict';
  var CAT = window.MC_CATALOGUE || { produits: {} };
  var APERCU = !!window.MC_APERCU;
  var CLE = 'madamecho-demande-v1';
  var DUREE = 7 * 24 * 3600 * 1000;

  function euros(c) {
    var e = Math.floor(c / 100), ct = c % 100;
    return ct ? e + ',' + (ct < 10 ? '0' : '') + ct + ' €' : e + ' €';
  }
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }

  /* ---------- Provenance (QR code des emballages, 07/11/2026) ----------
     madamecho.com/qr ouvre la carte avec les paramètres de campagne de Matomo (mtm_campaign, mtm_kwd). Ils suivent la visite
     de lien en lien, dans l'adresse seulement (rien n'est stocké), et « Ma demande » les envoie avec la demande : la gestion
     sait alors qu'elle vient d'un scan. Matomo retire lui-même ces paramètres des adresses de pages qu'il compte. */
  var PROVENANCE = '';
  (function () {
    var camp = /[?&]mtm_campaign=([\w.-]{1,40})(?:&|$)/.exec(location.search), mot = /[?&]mtm_kwd=([\w.-]{1,40})(?:&|$)/.exec(location.search);
    if (!camp) return;
    PROVENANCE = '?mtm_campaign=' + camp[1] + (mot ? '&mtm_kwd=' + mot[1] : '');
    $$('a[href]').forEach(function (a) {
      var h = a.getAttribute('href');
      if (!h || /^(#|\/\/|[a-z][a-z0-9+.-]*:)/i.test(h) || /[?&]mtm_campaign=/.test(h)) return;
      var m = /^([^#]*)(#.*)?$/.exec(h);
      a.setAttribute('href', m[1] + (m[1].indexOf('?') >= 0 ? '&' + PROVENANCE.slice(1) : PROVENANCE) + (m[2] || ''));
    });
    var via = $('input[name="via"]');
    if (via) via.value = camp[1] + (mot ? ' ' + mot[1] : '');
  })();

  /* ---------- Clics comptés (14/11/2026) ----------
     Un lien avec data-evenement="Catégorie|Action|Nom" envoie un événement à Matomo s'il mesure la visite (sinon rien). */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('[data-evenement]');
    if (!a || !window._paq) return;
    var t = a.getAttribute('data-evenement').split('|');
    window._paq.push(['trackEvent', t[0], t[1] || '', t[2] || '']);
  });

  /* ---------- Panier ----------
     Une seule source : le stockage du navigateur quand il marche ; sinon, la mémoire de la page, et la demande passe
     dans l'adresse vers « Ma demande » (#d=…). Mêmes plafonds que le serveur : 10 par taille, 200 par parfum (audit C05). */
  function own(o, k) { return !!o && typeof k === 'string' && Object.prototype.hasOwnProperty.call(o, k); }
  function produit(l) { return l && own(CAT.produits, l.p) ? CAT.produits[l.p] : null; }
  function maxQ(p) { var m = p.mode === 'taille' ? 10 : 200, pas = p.mode === 'taille' ? 1 : p.pas; return m - (m % pas); }
  function valide(l) {
    var p = produit(l);
    if (!p || typeof l.q !== 'number' || l.q % 1 || l.q < 1 || l.q > maxQ(p)) return false;
    return p.mode === 'taille' ? own(p.tailles, l.t) : own(p.parfums, l.v);
  }
  var STOCKAGE = (function () {
    try { localStorage.setItem(CLE + '-essai', '1'); localStorage.removeItem(CLE + '-essai'); return true; } catch (e) { return false; }
  })();
  /* Renvoie la demande gardée, [] si elle est vide ou expirée (elle est alors effacée, audit C07), null sans stockage. */
  function lire() {
    if (!STOCKAGE) return null;
    try {
      var d = JSON.parse(localStorage.getItem(CLE) || 'null');
      if (!d) return [];
      if (!d.maj || Date.now() - d.maj > DUREE || !Array.isArray(d.lignes)) { localStorage.removeItem(CLE); return []; }
      return d.lignes.filter(valide);
    } catch (e) { return []; }
  }
  function depuisAdresse() {
    var m = /(?:^#|&)d=([^&]*)/.exec(location.hash);
    if (!m) return [];
    try { var l = JSON.parse(decodeURIComponent(m[1])); return Array.isArray(l) ? l.filter(valide) : []; } catch (e) { return []; }
  }
  var memoire = STOCKAGE ? [] : depuisAdresse();
  function ecrire(ls) {
    memoire = ls;
    if (STOCKAGE) {
      try {
        if (ls.length) localStorage.setItem(CLE, JSON.stringify({ lignes: ls, maj: Date.now() }));
        else localStorage.removeItem(CLE);
      } catch (e) { STOCKAGE = false; }   // stockage plein ou refusé : la demande vit le temps de la page
    }
    compteur();
  }
  function lignes() { var l = lire(); return l === null ? memoire.slice() : l; }
  function cleLigne(l) { return l.p + '|' + (l.t || l.v); }
  /* Ajoute des lignes ; renvoie les messages des quantités plafonnées, jamais de ligne perdue en silence. */
  function ajouter(nouv) {
    var ls = lignes(), msgs = [], ajoute = false;
    nouv.forEach(function (n) {
      var p = produit(n), max = maxQ(p);
      var trouve = ls.filter(function (l) { return cleLigne(l) === cleLigne(n); })[0];
      var avant = trouve ? trouve.q : 0, q = Math.min(avant + n.q, max);
      if (avant + n.q > max) msgs.push(p.nom + ', ' + libelle(n) + '\u00a0: ' + max + ' au plus dans une demande. Pour davantage, décrivez-le dans «\u00a0Votre envie\u00a0».');
      if (q <= avant) return;
      ajoute = true;
      if (trouve) trouve.q = q;
      else { var o = { p: n.p, q: q }; if (n.t) o.t = n.t; if (n.v) o.v = n.v; ls.push(o); }
    });
    ecrire(ls);
    msgs.rien = !ajoute;
    return msgs;
  }
  function compteur() {
    var vus = {}; lignes().forEach(function (l) { vus[l.p] = 1; });
    var n = Object.keys(vus).length;
    $$('[data-compteur]').forEach(function (c) {
      c.hidden = !n; c.textContent = n;
      c.setAttribute('aria-label', n + (n > 1 ? ' gâteaux' : ' gâteau'));
    });
    barreDemande(n);
  }
  /* Barre « Ma demande » en bas de l'écran, sur téléphone (14/11/2026) : dès qu'un gâteau est dans la demande, partout sauf sur la page
     « Ma demande ». Elle reprend le lien du menu (provenance comprise) ; sans stockage, la demande passe dans l'adresse, comme ailleurs. */
  var barre = null;
  function barreDemande(n) {
    var menu = $('.nav-demande');
    if (!menu || $('#formulaire') || (!barre && !n)) return;
    if (!barre) {
      barre = document.createElement('a'); barre.className = 'barre-demande';
      barre.innerHTML = '<span><b>Ma demande</b> <span data-bd></span></span><span class="bd-voir">Voir</span>';
      document.body.appendChild(barre);
    }
    barre.hidden = !n;
    document.body.classList.toggle('avec-barre', !!n);
    if (!n) return;
    var combien = n + (n > 1 ? ' gâteaux' : ' gâteau');
    $('[data-bd]', barre).textContent = '· ' + combien;
    barre.setAttribute('aria-label', 'Voir ma demande : ' + combien);
    barre.href = menu.getAttribute('href') + (STOCKAGE ? '' : '#d=' + encodeURIComponent(JSON.stringify(lignes())));
  }
  function prixLigne(l) {
    var p = CAT.produits[l.p];
    return p.mode === 'taille' ? p.tailles[l.t].prix * l.q : p.unite * l.q;
  }
  function libelle(l) {
    var p = CAT.produits[l.p];
    return p.mode === 'taille' ? p.tailles[l.t].libelle : p.parfums[l.v].libelle;
  }
  /* Totaux par produit à quantité minimale (choux, cookies, petits gâteaux) */
  function manques(ls) {
    var tot = {}, out = [];
    ls.forEach(function (l) { tot[l.p] = (tot[l.p] || 0) + l.q; });
    Object.keys(tot).forEach(function (id) {
      var p = CAT.produits[id];
      if (p.mode === 'parfums' && tot[id] < p.minimum) out.push(p.nom + '\u00a0: ' + p.minimum + ' au minimum (' + tot[id] + ' pour l’instant).');
      if (p.mode === 'parfums' && p.pas > 1) ls.forEach(function (l) {
        if (l.p === id && l.q % p.pas) out.push(p.nom + ' ' + p.parfums[l.v].libelle + '\u00a0: par ' + p.pas + ' du même parfum.');
      });
    });
    return out;
  }

  /* ---------- Page d'un gâteau ---------- */
  function pageGateau() {
    var art = $('[data-produit]');
    if (!art) return;
    var id = art.getAttribute('data-produit'), p = CAT.produits[id], form = $('[data-ajout]', art);
    var conf = $('[data-confirmation]', art), bouton = $('[data-ajouter]', art);
    var lienDemande = (APERCU ? '../../ma-demande/index.html' : '../../ma-demande/') + PROVENANCE;
    function confirmer(texte, msgs) {
      conf.innerHTML = '';
      conf.appendChild(document.createTextNode(texte + ' ' + (msgs.length ? msgs.join(' ') + ' ' : '')));
      var a = document.createElement('a'); a.textContent = 'Voir ma demande';
      a.href = lienDemande + (STOCKAGE ? '' : '#d=' + encodeURIComponent(JSON.stringify(lignes())));
      conf.appendChild(a);
    }
    if (p.mode === 'taille') {
      var q = 1, out = $('[data-q]', form);
      var moins = $('[data-moins]', form), plus = $('[data-plus]', form);
      function maj() { out.textContent = q; moins.disabled = q <= 1; plus.disabled = q >= 5; }
      moins.addEventListener('click', function () { if (q > 1) q--; maj(); });
      plus.addEventListener('click', function () { if (q < 5) q++; maj(); });
      maj();
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var t = ($('input[name=taille]:checked', form) || {}).value;
        if (!t) return;
        var msgs = ajouter([{ p: id, t: t, q: q }]);
        confirmer(msgs.rien ? 'Rien n’a été ajouté.' : msgs.length ? 'Demande mise à jour.' : 'Ajouté à votre demande\u00a0: ' + q + ' × ' + p.nom + ', ' + p.tailles[t].libelle + '.', msgs);
        q = 1; maj();
      });
    } else {
      var qs = {}, totalEl = $('[data-total]', form);
      function majP() {
        var tot = 0;
        $$('.parfum', form).forEach(function (r) {
          var v = r.getAttribute('data-parfum'); tot += qs[v] || 0;
          $('[data-q]', r).textContent = qs[v] || 0;
          $('[data-moins]', r).disabled = !(qs[v] > 0);
        });
        bouton.disabled = tot < p.minimum;
        totalEl.textContent = tot ? tot + ' pièce' + (tot > 1 ? 's' : '') + ' · ' + euros(tot * p.unite) +
          (tot < p.minimum ? ' · encore ' + (p.minimum - tot) + ' pour atteindre le minimum' : '') : '';
      }
      $$('.parfum', form).forEach(function (r) {
        var v = r.getAttribute('data-parfum');
        $('[data-plus]', r).addEventListener('click', function () { qs[v] = Math.min((qs[v] || 0) + p.pas, 200); majP(); });
        $('[data-moins]', r).addEventListener('click', function () { qs[v] = Math.max((qs[v] || 0) - p.pas, 0); majP(); });
      });
      majP();
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var nouv = [], tot = 0;
        Object.keys(qs).forEach(function (v) { if (qs[v] > 0) { nouv.push({ p: id, v: v, q: qs[v] }); tot += qs[v]; } });
        if (tot < p.minimum) return;
        var msgs = ajouter(nouv);
        confirmer(msgs.rien ? 'Rien n’a été ajouté.' : msgs.length ? 'Demande mise à jour.' : 'Ajouté à votre demande\u00a0: ' + tot + ' ' + p.nom.toLowerCase() + '.', msgs);
        qs = {}; majP();
      });
    }
  }

  /* « Partager ce gâteau » : sur la page d'un gâteau, avec ou sans ajout à la demande (fiche du Number cake) */
  function boutonPartager() {
    var part = $('[data-partager]');
    if (part) part.addEventListener('click', function () {
      // sans la provenance de la visite (QR code, lien d'Instagram) : la personne qui reçoit le lien n'est pas venue par là
      var url = location.href.replace(/[?#].*$/, '').replace(/index\.html$/, '');
      if (navigator.share) { navigator.share({ title: document.title, url: url }).catch(function () {}); return; }
      copier(url, function (ok) { part.textContent = ok ? 'Lien copié' : url; });
    });
  }

  function copier(texte, fin) {
    function secours() {   // navigateurs sans presse-papiers moderne : zone de texte temporaire
      var t = document.createElement('textarea'), ok = false;
      t.value = texte; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
      document.body.appendChild(t); t.select();
      try { ok = document.execCommand('copy'); } catch (e) {}
      document.body.removeChild(t); fin(ok);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texte).then(function () { fin(true); }, secours);
    } else secours();
  }

  /* Bouton « copier » à côté de l'adresse email (toutes les pages) */
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-copier-email]');
    if (!b) return;
    var msg = b.querySelector('.copie-ok');
    copier(b.getAttribute('data-copier-email'), function (ok) {
      b.classList.add(ok ? 'copie' : 'copie-ko');
      if (msg) msg.textContent = ok ? 'Adresse copiée' : 'Copie impossible';
      clearTimeout(b._t);
      b._t = setTimeout(function () { b.classList.remove('copie', 'copie-ko'); if (msg) msg.textContent = ''; }, 2000);
    });
  });

  /* ---------- Ma demande ---------- */
  function iso(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
  function dateFr(s) {
    var p = s.split('-'), d = new Date(+p[0], +p[1] - 1, +p[2]);
    try { return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); } catch (e) { return s; }
  }
  function pageDemande() {
    var form = $('#formulaire');
    if (!form) return;
    var panier = $('[data-panier]'), dateIn = $('[data-date]'), dateAide = $('[data-date-aide]');
    var erreurs = $('[data-erreurs]'), envoyer = $('[data-envoyer]'), perso = $('[data-perso]');
    // « Commander et payer » (spécification 006, temps 2) : proposé seulement si le site le permet pour le jour choisi (réponse de
    // « verifier ») et si le panier s'y prête ; le serveur revérifie tout à l'envoi
    var payerBloc = $('[data-payer]'), payerNon = $('[data-payer-non]'), payerBouton = $('[data-commander-payer]'), payerJour = null;
    var TEXTE_ENVOYER = 'Envoyer ma demande, sans engagement', TEXTE_SANS_PAYER = 'Envoyer une demande sans payer', texteEnvoyer = TEXTE_ENVOYER;
    var envoiEnCours = false;
    var plaques = {};
    var racine = '../';
    var lienCarte = ($('.vide a', panier) || { getAttribute: function () { return '../#carte'; } }).getAttribute('href');

    if (/[?&]pro=1/.test(location.search)) { var c = $('[data-pro]'); if (c) c.checked = true; }
    /* Demande déjà commencée (14/11/2026) : un bouton de l'accueil ouvre cette page avec ?demande=<clé> (anniversaire, Number cake,
       événement…). « Votre envie » commence par quelques mots que le client complète, l'aide dit quoi préciser, et la demande dit à
       Madame Cho par quel bouton elle a commencé (champ caché « depuis »). Le client peut tout effacer ; des mots laissés tels quels
       ne suffisent pas (voir verifier). */
    var cleEnvie = (/[?&]demande=([\w-]{1,30})(?:&|$)/.exec(location.search) || [])[1];
    var ENVIE = cleEnvie && own(CAT.envies, cleEnvie) ? CAT.envies[cleEnvie] : null;
    function typo(t) { return t.replace(/ ([:;?!»])/g, ' $1').replace(/« /g, '« '); }
    if (ENVIE) {
      if (!perso.value.trim()) perso.value = ENVIE.envie;
      var aideP = $('[data-perso-aide]'); if (aideP && ENVIE.aide) aideP.textContent = typo(ENVIE.aide);
      if (form.elements.depuis) form.elements.depuis.value = cleEnvie;
    }
    /* « Comment avez-vous connu Madame Cho ? » : une réponse à toucher, que l'on retire en la touchant de nouveau (facultatif) */
    var choisi = null;
    $$('input[name="connu"]', form).forEach(function (r) {
      r.addEventListener('click', function () {
        if (choisi === r) { r.checked = false; choisi = null; } else choisi = r;
      });
    });
    function majPro() {
      var on = $('[data-pro]') && $('[data-pro]').checked;
      $('[data-societe]').hidden = !on; $('[data-message-pro]').hidden = !on;
    }
    if ($('[data-pro]')) $('[data-pro]').addEventListener('change', function () { majPro(); majDate(); majPayer(); });
    majPro();

    function delaiMin(ls) {
      var d = 0;
      ls.forEach(function (l) { d = Math.max(d, CAT.produits[l.p].delai); });
      if (perso.value.trim() || !ls.length) d = Math.max(d, CAT.delaiPerso);
      if ($('[data-pro]') && $('[data-pro]').checked) d = Math.max(d, CAT.delaiPro || 14);   // professionnels : deux semaines
      return d;
    }
    function majDate() {
      var d = new Date(); d.setHours(12, 0, 0, 0);
      var n = delaiMin(lignes()); d.setDate(d.getDate() + n);
      // le premier jour possible n'est jamais un jour fermé (dimanche, 24 décembre) : on passe au suivant
      while ((CAT.fermesSemaine || [0]).indexOf(d.getDay()) >= 0 || iso(d).slice(5) === '12-24') d.setDate(d.getDate() + 1);
      dateIn.min = iso(d);
      var max = new Date(); max.setMonth(max.getMonth() + 6); dateIn.max = iso(max);
      dateAide.textContent = 'Au plus tôt le ' + dateFr(dateIn.min) + (n === 1 ? ' (commande la veille).' : ' (commande ' + n + ' jours avant).') + ' Pas de retrait le dimanche ni le 24 décembre.';
    }

    // Les plages de retrait (spécification 007, 27/11/2026) : à chaque date choisie, la page demande au site l'état des six plages de ce
    // jour, « libre » ou « complet » (pleine, fermée ou indisponible : jamais un nombre ni un motif), et ne laisse choisir qu'une plage libre.
    // Pas de retrait le dimanche ni le 24 décembre. Sans réponse, rien n'est bloqué : le serveur vérifie de nouveau à l'envoi.
    var indispoP = $('[data-date-indispo]'), indispo = { cle: '', message: '' }, numIndispo = 0, plageSel = form.elements.plage, complets = {};
    var MSG_DIMANCHE = 'Pas de retrait le dimanche : choisissez un autre jour, ou appelez Madame Cho au 06 61 31 17 79 pour un cas particulier.';
    function estDimanche(d) { var x = new Date(d + 'T12:00:00'); return !isNaN(x) && (CAT.fermesSemaine || [0]).indexOf(x.getDay()) >= 0; }
    // Audit n° 10 (28/11/2026) : les plages ne se choisissent qu'une fois le jour connu (C10-12) ; une plage choisie qui n'est plus libre
    // est retirée avec une phrase, la demande est gardée. Rend true si la plage choisie vient d'être retirée.
    function majPlages(etats) {   // etats : { code: 'libre'|'complet' } ; null : toutes libres
      var sansDate = !form.elements.date.value, avant = plageSel.value;
      complets = {};
      $$('option', plageSel).forEach(function (o) {
        if (!o.value) { o.textContent = sansDate ? 'Choisissez une date d’abord' : 'Choisissez une plage'; return; }
        var complet = !!(etats && etats[o.value] === 'complet');
        if (complet) complets[o.value] = true;
        o.disabled = complet || sansDate;
        o.textContent = CAT.plages[o.value] + (complet ? ' (complet)' : '');
      });
      if (plageSel.value && (complets[plageSel.value] || sansDate)) plageSel.value = '';
      return !!(avant && !plageSel.value && !sansDate);
    }
    function montrerIndispo(cle, message) {
      var avant = indispo.message;
      indispo = { cle: cle, message: message };
      if (indispoP) indispoP.textContent = message;
      if (message) form.elements.date.setAttribute('aria-invalid', 'true');
      else if (avant) form.elements.date.removeAttribute('aria-invalid');
    }
    // Une phrase sous la date qui ne bloque pas l'envoi (vérification en cours, plage retirée, plages pas vérifiées)
    function noter(texte) { if (indispoP && !indispo.message) indispoP.textContent = texte; }
    var TOUT_COMPLET = { '0900': 'complet', '1030': 'complet', '1200': 'complet', '1400': 'complet', '1600': 'complet', '1800': 'complet' };
    function verifierAgenda() {
      var d = form.elements.date.value, n = ++numIndispo;
      montrerIndispo('', '');
      majPlages(null);
      payerJour = null; majPayer();
      if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) return;
      if (estDimanche(d) || d.slice(5) === '12-24') {   // jours fermés connus : aucune plage, comme le dimanche (C10-12)
        majPlages(TOUT_COMPLET);
        montrerIndispo(d, estDimanche(d) ? MSG_DIMANCHE : 'Pas de retrait le 24 décembre\u00a0: choisissez une autre date.');
        return;
      }
      if (APERCU || !window.fetch || !window.FormData || d < dateIn.min || d > dateIn.max) return;
      var fd = new FormData();
      fd.append('verifier', '1'); fd.append('date', d);
      noter('Vérification des plages libres…');
      // Sans réponse, la demande reste possible, mais la page ne dit pas que les plages sont libres (C10-05)
      var nonVerifie = function () { if (n === numIndispo && d === form.elements.date.value) noter('Les plages libres ne peuvent pas être vérifiées pour le moment. Vous pouvez envoyer votre demande : Madame Cho vous confirmera la plage.'); };
      fetch(form.getAttribute('action'), { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (n !== numIndispo || d !== form.elements.date.value) return;
          if (!j || !j.ok || !j.plages) { nonVerifie(); return; }
          var retiree = majPlages(j.plages);
          montrerIndispo(d, String(j.message || ''));
          payerJour = j.payer === true ? { date: d, minimum: Math.max(0, +j.minimum || 0) } : null; majPayer();
          noter(retiree ? 'La plage choisie n’est plus libre ce jour-là : choisissez-en une autre, votre demande est gardée.' : '');
        })
        .catch(nonVerifie);
    }
    dateIn.addEventListener('change', verifierAgenda);
    plageSel.addEventListener('change', function () { if (plageSel.value) plageSel.removeAttribute('aria-invalid'); majPayer(); });
    majPlages(null);

    var sansStockage = $('[data-sans-stockage]');
    if (sansStockage) sansStockage.hidden = STOCKAGE;
    panier.setAttribute('tabindex', '-1');
    function changer(k, f) {   // modifie la ligne de clé k dans la demande telle qu'elle est maintenant (autre onglet compris)
      var ls2 = lignes(), j = -1;
      ls2.forEach(function (l, x) { if (cleLigne(l) === k) j = x; });
      if (j >= 0) { f(ls2, j); ecrire(ls2); }
      rendre();
    }
    function rendre() {
      var ls = lignes();
      // Le bouton qui avait le focus le retrouve après la mise à jour (audit C08)
      var actif = document.activeElement, cleF = actif && panier.contains(actif) ? actif.getAttribute('data-f') : null;
      panier.innerHTML = '';
      if (!ls.length) {
        var v = document.createElement('p'); v.className = 'vide';
        v.textContent = 'Votre demande est vide pour l’instant. ';
        var va = document.createElement('a'); va.href = lienCarte; va.textContent = 'Voir la carte'; v.appendChild(va);
        panier.appendChild(v); majDate(); majPayer();
        if (cleF) panier.focus();
        return;
      }
      var total = 0;
      ls.forEach(function (l, i) {
        var p = CAT.produits[l.p], div = document.createElement('div'); div.className = 'ligne';
        var im = document.createElement('img'); im.src = racine + 'assets/photos/' + p.photo + '-600.webp'; im.alt = ''; im.width = 64; im.height = 64; im.loading = 'lazy';
        var nom = document.createElement('div'); nom.className = 'l-nom'; nom.textContent = p.nom;
        var det = document.createElement('div'); det.className = 'l-detail';
        det.textContent = libelle(l) + (p.mode === 'parfums' ? ' · ' + euros(p.unite) + ' la pièce' : '');
        var act = document.createElement('div'); act.className = 'l-actions';
        var pas = p.mode === 'parfums' ? p.pas : 1, maxQ = p.mode === 'taille' ? 10 : 200;
        var grp = document.createElement('div'); grp.className = 'pas'; grp.setAttribute('role', 'group'); grp.setAttribute('aria-label', 'Quantité\u00a0: ' + p.nom + ', ' + libelle(l));
        var m = document.createElement('button'); m.type = 'button'; m.textContent = '−'; m.setAttribute('aria-label', 'Moins');
        var o = document.createElement('output'); o.textContent = l.q;
        var pl = document.createElement('button'); pl.type = 'button'; pl.textContent = '+'; pl.setAttribute('aria-label', 'Plus');
        m.disabled = l.q <= pas; pl.disabled = l.q + pas > maxQ;
        var k = cleLigne(l);
        m.setAttribute('data-f', k + '|m'); pl.setAttribute('data-f', k + '|p');
        m.addEventListener('click', function () { changer(k, function (a, j) { a[j].q = Math.max(pas, a[j].q - pas); }); });
        pl.addEventListener('click', function () { changer(k, function (a, j) { a[j].q = Math.min(maxQ, a[j].q + pas); }); });
        grp.appendChild(m); grp.appendChild(o); grp.appendChild(pl);
        var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'lien-bouton'; rm.textContent = 'Retirer';
        rm.setAttribute('aria-label', 'Retirer ' + p.nom + ', ' + libelle(l));
        rm.setAttribute('data-f', 'retirer|' + i);
        rm.addEventListener('click', function () { changer(k, function (a, j) { a.splice(j, 1); }); });
        var px = document.createElement('span'); px.className = 'l-prix'; px.textContent = euros(prixLigne(l)); total += prixLigne(l);
        act.appendChild(grp); act.appendChild(rm); act.appendChild(px);
        div.appendChild(im); div.appendChild(nom); div.appendChild(det); div.appendChild(act);
        if (p.plaque) {
          var lab = document.createElement('label'); lab.className = 'champ plaque';
          var sp = document.createElement('span'); sp.innerHTML = 'Plaque en chocolat, comprise\u00a0: prénom et message <i>(facultatif)</i>';
          var inp = document.createElement('input'); inp.type = 'text'; inp.maxLength = 60; inp.value = plaques[k] || '';
          inp.addEventListener('input', function () { plaques[k] = inp.value; });
          lab.appendChild(sp); lab.appendChild(inp); div.appendChild(lab);
        }
        panier.appendChild(div);
      });
      var s = document.createElement('div'); s.className = 'somme';
      s.innerHTML = '<span>Total indicatif, TTC</span><span></span>'; s.lastChild.textContent = euros(total);
      panier.appendChild(s);
      var ms = manques(ls);
      if (ms.length) { var a = document.createElement('p'); a.className = 'avert'; a.textContent = ms.join(' '); panier.appendChild(a); }
      majDate(); majPayer();
      if (cleF) {
        var cible = $('[data-f="' + cleF.replace(/"/g, '') + '"]', panier);
        if (!cible && /^retirer\|/.test(cleF)) {   // ligne retirée : le bouton « Retirer » de la ligne qui prend sa place, ou de la dernière
          var rs = $$('[data-f^="retirer|"]', panier); cible = rs[Math.min(+cleF.split('|')[1], rs.length - 1)];
        }
        if (cible && cible.disabled) cible = $('button:not([disabled])', cible.parentNode);
        (cible || panier).focus();
      }
    }
    rendreDemande = rendre;
    perso.addEventListener('input', function () { majDate(); majPayer(); });
    form.addEventListener('input', function (e) { if (e.target.removeAttribute) e.target.removeAttribute('aria-invalid'); });
    rendre();
    if (form.elements.date.value) verifierAgenda();   // date remise par le navigateur (retour arrière)

    function recap(ref) {
      var f = form.elements, ls = lignes(), t = [], total = 0;
      if (ref) t.push('Demande ' + ref);
      ls.forEach(function (l) {
        var p = CAT.produits[l.p], k = cleLigne(l);
        t.push('- ' + l.q + ' × ' + p.nom + ', ' + libelle(l) + '\u00a0: ' + euros(prixLigne(l)) + (plaques[k] ? ' (plaque\u00a0: «\u00a0' + plaques[k].trim() + '\u00a0»)' : ''));
        total += prixLigne(l);
      });
      if (ls.length) t.push('Total indicatif\u00a0: ' + euros(total) + ' TTC');
      if (f.perso.value.trim()) t.push('Personnalisation\u00a0: ' + f.perso.value.trim());
      if (f.date.value) t.push('Retrait souhaité\u00a0: ' + dateFr(f.date.value) + ', ' + (CAT.plages[f.plage.value] || '').toLowerCase());
      t.push('Nom\u00a0: ' + (f.prenom ? f.prenom.value.trim() + ' ' : '') + f.nom.value.trim());
      t.push('Email\u00a0: ' + f.email.value.trim() + (f.telephone.value.trim() ? ' · Téléphone\u00a0: ' + f.telephone.value.trim() : ''));
      if (f.pro && f.pro.checked) t.push('Entreprise\u00a0: ' + f.societe.value.trim() + (f.besoin.value.trim() ? ' · Besoin\u00a0: ' + f.besoin.value.trim() : ''));
      return t.join('\n');
    }

    // Le bloc « Commander et payer » : le récapitulatif (gâteaux, prix TTC, jour et plage, annulation), ou la phrase qui dit pourquoi
    // le paiement en ligne n'est pas proposé pour ce panier (seulement quand il l'est pour ce jour-là)
    function totalPanier(ls) { var t = 0; ls.forEach(function (l) { t += prixLigne(l); }); return t; }
    function payerPossible() { return !!payerBloc && !payerBloc.hidden; }
    function majPayer() {
      if (!payerBloc || !envoyer) return;
      var f = form.elements, ls = lignes(), pourquoi = '';
      var possible = !!payerJour && payerJour.date === f.date.value;
      if (possible) {
        if ((f.pro && f.pro.checked) || f.perso.value.trim() || !ls.length || manques(ls).length)
          pourquoi = 'Le paiement en ligne est proposé pour les gâteaux de la carte, sans personnalisation au-delà de la plaque. Pour votre demande, Madame Cho vous répond sous 24 heures.';
        else if (totalPanier(ls) < payerJour.minimum)
          pourquoi = 'Le paiement en ligne commence à ' + euros(payerJour.minimum) + '\u00a0: envoyez votre demande, vous paierez au retrait.';
      }
      var ok = possible && !pourquoi && !!f.plage.value && f.date.value >= dateIn.min;
      payerBloc.hidden = !ok;
      if (payerNon) { payerNon.hidden = !pourquoi; payerNon.textContent = pourquoi; }
      if (ok) {
        var t = [];
        ls.forEach(function (l) { var k = cleLigne(l); t.push(l.q + ' × ' + CAT.produits[l.p].nom + ', ' + libelle(l) + '\u00a0: ' + euros(prixLigne(l)) + (plaques[k] && plaques[k].trim() ? ' (plaque\u00a0: «\u00a0' + plaques[k].trim() + '\u00a0»)' : '')); });
        t.push('Total à payer\u00a0: ' + euros(totalPanier(ls)) + ' TTC');
        t.push('Retrait\u00a0: ' + dateFr(f.date.value) + ', ' + (CAT.plages[f.plage.value] || '').toLowerCase());
        t.push('Annulation\u00a0: par écrit ou au 06 61 31 17 79\u00a0; une commande payée en ligne puis annulée est remboursée en entier.');
        $('[data-payer-recap]').textContent = t.join('\n');
        if (payerBouton && !envoiEnCours) payerBouton.textContent = 'Commander et payer ' + euros(totalPanier(ls));
      }
      texteEnvoyer = ok ? TEXTE_SANS_PAYER : TEXTE_ENVOYER;
      if (!envoiEnCours) envoyer.textContent = texteEnvoyer;
    }
    form.addEventListener('input', function (e) { if (e.target && e.target.closest && e.target.closest('.plaque')) majPayer(); });

    function verifier() {
      var f = form.elements, out = [], ls = lignes();
      $$('[aria-invalid]', form).forEach(function (x) { x.removeAttribute('aria-invalid'); });
      function err(el, msg) {
        out.push(msg);
        if (el) {
          el.setAttribute('aria-invalid', 'true');
          var d = (el.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
          if (d.indexOf('erreurs') < 0) { d.push('erreurs'); el.setAttribute('aria-describedby', d.join(' ')); }
        }
      }
      if (!ls.length && !f.perso.value.trim() && !(f.pro.checked && f.besoin.value.trim()))
        err(f.perso, 'Ajoutez au moins un gâteau de la carte, ou décrivez votre gâteau personnalisé.');
      else if (ENVIE && f.perso.value.trim() === ENVIE.envie.trim())   // les premiers mots, laissés tels quels : à compléter, ou à effacer
        err(f.perso, typo(ENVIE.incomplet) + (ls.length ? ' Ou effacez « Votre envie » pour ne demander que les gâteaux de la carte.' : ''));
      manques(ls).forEach(function (m) { out.push(m); });
      if (!f.date.value) err(f.date, 'Choisissez une date de retrait.');
      else if (f.date.value < dateIn.min) err(f.date, 'La date de retrait doit être au plus tôt le ' + dateFr(dateIn.min) + '.');
      else if (f.date.value.slice(5) === '12-24') err(f.date, 'Pas de retrait le 24 décembre\u00a0: choisissez une autre date.');
      else if (estDimanche(f.date.value)) err(f.date, MSG_DIMANCHE);
      else if (f.date.value > dateIn.max) err(f.date, 'Choisissez une date dans les six prochains mois.');
      else if (indispo.message && indispo.cle === f.date.value) err(f.date, indispo.message);
      else if (!f.plage.value) err(f.plage, 'Choisissez une plage horaire de retrait.');
      if (f.prenom && !f.prenom.value.trim()) err(f.prenom, 'Indiquez votre prénom.');
      if (!f.nom.value.trim()) err(f.nom, 'Indiquez votre nom.');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(f.email.value.trim())) err(f.email, 'Indiquez une adresse email valide, pour recevoir la réponse de Madame Cho.');
      if (f.pro.checked && !f.societe.value.trim()) err(f.societe, 'Indiquez le nom de l’entreprise.');
      return out;
    }

    /* L'envoi : une demande sans engagement (bouton du formulaire), ou « Commander et payer » (spécification 006) : la commande est
       enregistrée, la place retenue, et la page de paiement de Qonto s'ouvre dans un nouvel onglet ; cette page suit le paiement. */
    var boutonsTexte = function () { envoyer.textContent = texteEnvoyer; if (payerBouton) majPayer(); };
    function rouvrir() { envoiEnCours = false; envoyer.disabled = false; if (payerBouton) payerBouton.disabled = false; boutonsTexte(); }
    function fini(ref, variante) {
      var r = $('[data-recu]'), texte = recap(ref);
      $('[data-ref]').textContent = ref; $('[data-recap]').textContent = texte;
      var pr = form.elements.prenom, prenom = pr ? pr.value.trim() : ''; if (/^[A-Za-zÀ-ɏㄱ-힝'’ -]{1,40}$/.test(prenom)) $('[data-merci-prenom]').textContent = ' ' + prenom;
      if (variante && variante.paye) {   // payée en ligne : la commande est confirmée, le mail de confirmation vaut reçu
        $('[data-recu-etiquette]').textContent = 'Commande confirmée et payée';
        $('[data-recu-titre]').textContent = ', votre commande est confirmée';
        $('[data-recu-texte]').textContent = 'Paiement reçu. Un mail de confirmation, qui vaut reçu, part à votre adresse. Madame Cho vous attend ' + (form.elements.date.value ? 'le ' + dateFr(form.elements.date.value) + ', ' + (CAT.plages[form.elements.plage.value] || '').toLowerCase() : 'au jour convenu') + '.';
        $('[data-recu-suite]').textContent = 'Un empêchement ? Prévenez Madame Cho au plus tôt, en répondant au mail ou au 06 61 31 17 79 : une commande payée en ligne puis annulée est remboursée en entier.';
      }
      var msg = $('[data-recu-message]');
      if (msg && variante && variante.message) { msg.textContent = variante.message; msg.hidden = false; }
      form.hidden = true; var ch = $('.demande .chapo'); if (ch) ch.hidden = true;
      var sv = $('[data-suivi]'); if (sv) sv.hidden = true;
      r.hidden = false; r.focus(); window.scrollTo(0, 0);
      $('[data-copier]').addEventListener('click', function () {
        var b = this; copier(texte, function (ok) { b.textContent = ok ? 'Récapitulatif copié' : 'Sélectionnez le texte pour le copier'; });
      });
      ecrire([]);
      try { sessionStorage.removeItem('mc-suivi'); } catch (e) {}
    }
    function echec(msg) {
      rouvrir();
      erreurs.hidden = false;
      erreurs.innerHTML = '';
      erreurs.appendChild(document.createTextNode(msg + ' Votre demande est gardée. Vous pouvez réessayer, appeler le 06 61 31 17 79 ou '));
      var a = document.createElement('a');
      a.href = 'mailto:contact@madamecho.com?subject=' + encodeURIComponent('Demande depuis le site') + '&body=' + encodeURIComponent(recap(''));
      a.textContent = 'l’envoyer par email';
      erreurs.appendChild(a); erreurs.appendChild(document.createTextNode('.'));
      erreurs.focus();
    }

    // Le suivi du paiement : toutes les 5 secondes, 40 minutes au plus ; le jeton seul, sans aucune donnée personnelle
    var suivi = null, suiviMinuterie = null;
    function suivre(etatSuivi) {
      suivi = etatSuivi;
      try { sessionStorage.setItem('mc-suivi', JSON.stringify(suivi)); } catch (e) {}
      var sv = $('[data-suivi]');
      form.hidden = true; var ch = $('.demande .chapo'); if (ch) ch.hidden = true;
      $('[data-suivi-ref]').textContent = suivi.ref;
      $('[data-suivi-lien]').href = suivi.url;
      var j = new Date(suivi.jusqua);
      $('[data-suivi-delai]').textContent = isNaN(j) ? '' : 'Votre plage est retenue jusqu’à ' + j.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) + '. Sans paiement d’ici là, votre demande arrive quand même chez Madame Cho, sans paiement.';
      sv.hidden = false; sv.focus(); window.scrollTo(0, 0);
      interroger();
    }
    function interroger(sansPayer) {
      clearTimeout(suiviMinuterie);
      if (!suivi) return;
      if (!sansPayer && Date.now() - suivi.debut > 40 * 60000) { $('[data-suivi-texte]').textContent = 'Nous n’avons pas encore la réponse de Qonto. Si vous avez payé, le mail de confirmation vous arrivera ; sinon, Madame Cho a reçu votre demande et vous répond sous 24 heures.'; return; }
      var fd = new FormData(); fd.append(sansPayer ? 'sans_payer' : 'suivi', suivi.suivi);
      fetch(form.getAttribute('action'), { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j && j.etat === 'paye') { if (window._paq) window._paq.push(['trackEvent', 'Demande', 'Payée en ligne']); fini(suivi.ref, { paye: true }); suivi = null; return; }
          if (j && j.etat === 'non_abouti') { fini(suivi.ref, { message: j.message }); suivi = null; return; }
          if (j && j.etat === 'inconnu') { fini(suivi.ref, {}); suivi = null; return; }
          if (sansPayer && j && j.message) $('[data-suivi-texte]').textContent = j.message;
          suiviMinuterie = setTimeout(interroger, 5000);
        })
        .catch(function () { suiviMinuterie = setTimeout(interroger, 10000); });
    }
    var sansPayerB = $('[data-sans-payer]');
    if (sansPayerB) sansPayerB.addEventListener('click', function () { interroger(true); });
    // Revenu sur la page (onglet rechargé) pendant un paiement : le suivi reprend
    try {
      var sv0 = JSON.parse(sessionStorage.getItem('mc-suivi') || 'null');
      if (sv0 && sv0.suivi && Date.now() - sv0.debut < 40 * 60000) suivre(sv0); else sessionStorage.removeItem('mc-suivi');
    } catch (e) {}

    function envoyerDemande(payer) {
      if (envoiEnCours) return;
      var errs = verifier();
      if (payer && !errs.length && !(form.elements.conditions && form.elements.conditions.checked)) {
        errs.push('Cochez « J’accepte les conditions de commande » pour commander et payer.');
        if (form.elements.conditions) form.elements.conditions.setAttribute('aria-invalid', 'true');
      }
      if (errs.length) {
        erreurs.hidden = false; erreurs.textContent = errs.join('\n'); erreurs.focus(); return;
      }
      erreurs.hidden = true;
      var donnees = new FormData(form);
      donnees.append('lignes', JSON.stringify(lignes().map(function (l) {
        var o = { p: l.p, q: l.q }; if (l.t) o.t = l.t; if (l.v) o.v = l.v;
        if (plaques[cleLigne(l)]) o.plaque = plaques[cleLigne(l)].trim().slice(0, 60);
        return o;
      })));
      if (payer) donnees.append('payer', '1'); else donnees.delete && donnees.delete('conditions');
      // L'onglet de paiement s'ouvre tout de suite (sinon le navigateur le bloque), puis reçoit l'adresse de Qonto
      var onglet = null;
      if (payer && !APERCU) { try { onglet = window.open('', '_blank'); if (onglet) { onglet.opener = null; onglet.document.title = 'Paiement · Madame Cho'; } } catch (e) { onglet = null; } }
      var fermer = function () { try { if (onglet) onglet.close(); } catch (e) {} };
      envoiEnCours = true; envoyer.disabled = true; if (payerBouton) payerBouton.disabled = true;
      (payer ? payerBouton : envoyer).textContent = payer ? 'Préparation du paiement…' : 'Envoi en cours…';
      if (APERCU) {
        setTimeout(function () { fini('MC-APERCU', payer ? { paye: true } : null); }, 500);
        return;
      }
      var ctrl = window.AbortController ? new AbortController() : null;
      var minuterie = setTimeout(function () { if (ctrl) ctrl.abort(); }, 30000);
      fetch(form.getAttribute('action'), { method: 'POST', body: donnees, headers: { 'Accept': 'application/json' }, signal: ctrl ? ctrl.signal : undefined })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (j) {
          clearTimeout(minuterie);
          if (j && j.ok && j.ref && j.paiement && j.paiement.url) {
            if (window._paq) window._paq.push(['trackEvent', 'Demande', 'Commander et payer']);
            ecrire([]);
            try { if (onglet) onglet.location.href = j.paiement.url; } catch (e) { onglet = null; }
            envoiEnCours = false;
            suivre({ ref: j.ref, url: j.paiement.url, suivi: j.paiement.suivi, jusqua: j.paiement.jusqua, debut: Date.now() });
          }
          else if (j && j.ok && j.ref) {
            fermer();
            // Mesure d'audience : la demande envoyée est comptée, sans aucune donnée de la demande (ni référence, ni contenu)
            if (window._paq) window._paq.push(['trackEvent', 'Demande', 'Envoyée']);
            fini(j.ref, j.message ? { message: j.message } : null);
          }
          else if (j && j.payer === false) {   // le paiement n'est plus possible : la même demande peut partir sans payer, en un toucher
            fermer(); payerJour = null; rouvrir();
            erreurs.hidden = false; erreurs.textContent = (j.message || 'Le paiement en ligne n’est pas possible pour le moment.') + ' Votre demande est gardée : touchez « ' + texteEnvoyer + ' ».';
            erreurs.focus();
          }
          else if (j && j.indispo) {   // la plage ou le jour vient d'être fermé (ou rempli) dans l'agenda : à changer, comme une erreur de saisie
            fermer(); rouvrir(); verifierAgenda();
            erreurs.hidden = false; erreurs.textContent = (j.erreurs || [j.indispo]).join('\n'); erreurs.focus();
          }
          else { fermer(); echec((j && j.erreurs && j.erreurs.length) ? j.erreurs.join(' ') : 'L’envoi n’a pas abouti.'); }
        })
        .catch(function () { clearTimeout(minuterie); fermer(); echec('L’envoi n’a pas abouti (connexion interrompue ?).'); });
    }
    form.addEventListener('submit', function (e) { e.preventDefault(); envoyerDemande(false); });
    if (payerBouton) payerBouton.addEventListener('click', function () { envoyerDemande(true); });
  }

  var rendreDemande = null;
  compteur();
  pageGateau();
  boutonPartager();
  pageDemande();
  // Une demande changée ou vidée dans un autre onglet est reprise ici, jamais un ancien contenu (audit C05)
  window.addEventListener('storage', function (e) { if (e.key === CLE || e.key === null) { compteur(); if (rendreDemande) rendreDemande(); } });
})();

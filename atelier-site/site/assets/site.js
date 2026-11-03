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

  /* ---------- Panier ---------- */
  function valide(l) {
    var p = CAT.produits[l && l.p];
    if (!p || !(l.q > 0) || l.q > 500) return false;
    if (p.mode === 'taille') return !!(p.tailles[l.t]) && l.q <= 10;
    return !!(p.parfums[l.v]);
  }
  function lire() {
    try {
      var d = JSON.parse(localStorage.getItem(CLE) || 'null');
      if (!d || !d.maj || Date.now() - d.maj > DUREE || !Array.isArray(d.lignes)) return [];
      return d.lignes.filter(valide);
    } catch (e) { return []; }
  }
  function ecrire(lignes) {
    try {
      if (lignes.length) localStorage.setItem(CLE, JSON.stringify({ lignes: lignes, maj: Date.now() }));
      else localStorage.removeItem(CLE);
    } catch (e) { /* navigation privée : le panier vit le temps de la page */ }
    memoire = lignes;
    compteur();
  }
  var memoire = lire();
  function lignes() { var l = lire(); return l.length ? l : memoire; }
  function cleLigne(l) { return l.p + '|' + (l.t || l.v); }
  function ajouter(nouv) {
    var ls = lignes().slice();
    nouv.forEach(function (n) {
      var trouve = ls.filter(function (l) { return cleLigne(l) === cleLigne(n); })[0];
      if (trouve) trouve.q += n.q; else ls.push(n);
    });
    ecrire(ls);
  }
  function compteur() {
    var vus = {}; lignes().forEach(function (l) { vus[l.p] = 1; });
    var n = Object.keys(vus).length;
    $$('[data-compteur]').forEach(function (c) {
      c.hidden = !n; c.textContent = n;
      c.setAttribute('aria-label', n + (n > 1 ? ' gâteaux' : ' gâteau'));
    });
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
    var lienDemande = (APERCU ? '../../ma-demande/index.html' : '../../ma-demande/');
    function confirmer(texte) {
      conf.innerHTML = '';
      conf.appendChild(document.createTextNode(texte + ' '));
      var a = document.createElement('a'); a.href = lienDemande; a.textContent = 'Voir ma demande';
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
        ajouter([{ p: id, t: t, q: q }]);
        confirmer('Ajouté à votre demande\u00a0: ' + q + ' × ' + p.nom + ', ' + p.tailles[t].libelle + '.');
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
        ajouter(nouv);
        confirmer('Ajouté à votre demande\u00a0: ' + tot + ' ' + p.nom.toLowerCase() + '.');
        qs = {}; majP();
      });
    }
    var part = $('[data-partager]');
    if (part) part.addEventListener('click', function () {
      var url = location.href.replace(/index\.html$/, '');
      if (navigator.share) { navigator.share({ title: document.title, url: url }).catch(function () {}); return; }
      copier(url, function (ok) { part.textContent = ok ? 'Lien copié' : url; });
    });
  }

  function copier(texte, fin) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texte).then(function () { fin(true); }, function () { fin(false); });
    } else fin(false);
  }

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
    var plaques = {};
    var racine = '../';
    var lienCarte = ($('.vide a', panier) || { getAttribute: function () { return '../#carte'; } }).getAttribute('href');

    if (/[?&]pro=1/.test(location.search)) { var c = $('[data-pro]'); if (c) c.checked = true; }
    function majPro() {
      var on = $('[data-pro]') && $('[data-pro]').checked;
      $('[data-societe]').hidden = !on; $('[data-message-pro]').hidden = !on;
    }
    if ($('[data-pro]')) $('[data-pro]').addEventListener('change', majPro);
    majPro();

    function delaiMin(ls) {
      var d = 0;
      ls.forEach(function (l) { d = Math.max(d, CAT.produits[l.p].delai); });
      if (perso.value.trim() || !ls.length) d = Math.max(d, CAT.delaiPerso);
      return d;
    }
    function majDate() {
      var d = new Date(); d.setHours(12, 0, 0, 0);
      var n = delaiMin(lignes()); d.setDate(d.getDate() + n);
      dateIn.min = iso(d);
      var max = new Date(); max.setMonth(max.getMonth() + 6); dateIn.max = iso(max);
      dateAide.textContent = 'Au plus tôt le ' + dateFr(dateIn.min) + (n === 1 ? ' (commande la veille).' : ' (commande ' + n + ' jours avant).') + ' Pas de retrait le 24 décembre.';
    }

    function rendre() {
      var ls = lignes();
      panier.innerHTML = '';
      if (!ls.length) {
        var v = document.createElement('p'); v.className = 'vide';
        v.textContent = 'Votre demande est vide pour l’instant. ';
        var va = document.createElement('a'); va.href = lienCarte; va.textContent = 'Voir la carte'; v.appendChild(va);
        panier.appendChild(v); majDate(); return;
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
        m.addEventListener('click', function () { var ls2 = lignes(); ls2[i].q = Math.max(pas, ls2[i].q - pas); ecrire(ls2); rendre(); });
        pl.addEventListener('click', function () { var ls2 = lignes(); ls2[i].q = Math.min(maxQ, ls2[i].q + pas); ecrire(ls2); rendre(); });
        grp.appendChild(m); grp.appendChild(o); grp.appendChild(pl);
        var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'lien-bouton'; rm.textContent = 'Retirer';
        rm.setAttribute('aria-label', 'Retirer ' + p.nom + ', ' + libelle(l));
        rm.addEventListener('click', function () { var ls2 = lignes(); ls2.splice(i, 1); ecrire(ls2); rendre(); });
        var px = document.createElement('span'); px.className = 'l-prix'; px.textContent = euros(prixLigne(l)); total += prixLigne(l);
        act.appendChild(grp); act.appendChild(rm); act.appendChild(px);
        div.appendChild(im); div.appendChild(nom); div.appendChild(det); div.appendChild(act);
        if (p.plaque) {
          var k = cleLigne(l);
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
      majDate();
    }
    perso.addEventListener('input', majDate);
    form.addEventListener('input', function (e) { if (e.target.removeAttribute) e.target.removeAttribute('aria-invalid'); });
    rendre();

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
      if (f.date.value) t.push('Retrait souhaité\u00a0: ' + dateFr(f.date.value) + ', ' + (CAT.moments[f.moment.value] || '').toLowerCase());
      t.push('Nom\u00a0: ' + f.nom.value.trim());
      t.push('Email\u00a0: ' + f.email.value.trim() + (f.telephone.value.trim() ? ' · Téléphone\u00a0: ' + f.telephone.value.trim() : ''));
      if (f.pro && f.pro.checked) t.push('Entreprise\u00a0: ' + f.societe.value.trim() + (f.besoin.value.trim() ? ' · Besoin\u00a0: ' + f.besoin.value.trim() : ''));
      return t.join('\n');
    }

    function verifier() {
      var f = form.elements, out = [], ls = lignes();
      $$('[aria-invalid]', form).forEach(function (x) { x.removeAttribute('aria-invalid'); });
      function err(el, msg) { out.push(msg); if (el) el.setAttribute('aria-invalid', 'true'); }
      if (!ls.length && !f.perso.value.trim() && !(f.pro.checked && f.besoin.value.trim()))
        err(f.perso, 'Ajoutez au moins un gâteau de la carte, ou décrivez votre gâteau personnalisé.');
      manques(ls).forEach(function (m) { out.push(m); });
      if (!f.date.value) err(f.date, 'Choisissez une date de retrait.');
      else if (f.date.value < dateIn.min) err(f.date, 'La date de retrait doit être au plus tôt le ' + dateFr(dateIn.min) + '.');
      else if (f.date.value.slice(5) === '12-24') err(f.date, 'Pas de retrait le 24 décembre\u00a0: choisissez une autre date.');
      else if (f.date.value > dateIn.max) err(f.date, 'Choisissez une date dans les six prochains mois.');
      if (f.nom.value.trim().length < 2) err(f.nom, 'Indiquez votre prénom et votre nom.');
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(f.email.value.trim())) err(f.email, 'Indiquez une adresse email valide, pour recevoir la réponse de Youngmi.');
      if (f.pro.checked && !f.societe.value.trim()) err(f.societe, 'Indiquez le nom de l’entreprise.');
      return out;
    }

    var envoiEnCours = false;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (envoiEnCours) return;
      var errs = verifier();
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
      envoiEnCours = true; envoyer.disabled = true; envoyer.textContent = 'Envoi en cours…';
      function fini(ref) {
        var r = $('[data-recu]'), texte = recap(ref);
        $('[data-ref]').textContent = ref; $('[data-recap]').textContent = texte;
        form.hidden = true; var ch = $('.demande .chapo'); if (ch) ch.hidden = true;
        r.hidden = false; r.focus(); window.scrollTo(0, 0);
        $('[data-copier]').addEventListener('click', function () {
          var b = this; copier(texte, function (ok) { b.textContent = ok ? 'Récapitulatif copié' : 'Sélectionnez le texte pour le copier'; });
        });
        ecrire([]);
      }
      function echec(msg) {
        envoiEnCours = false; envoyer.disabled = false; envoyer.textContent = 'Envoyer ma demande, sans engagement';
        erreurs.hidden = false;
        erreurs.innerHTML = '';
        erreurs.appendChild(document.createTextNode(msg + ' Votre demande est gardée. Vous pouvez réessayer, appeler le 06 61 31 17 79 ou '));
        var a = document.createElement('a');
        a.href = 'mailto:contact@madamecho.com?subject=' + encodeURIComponent('Demande depuis le site') + '&body=' + encodeURIComponent(recap(''));
        a.textContent = 'l’envoyer par email';
        erreurs.appendChild(a); erreurs.appendChild(document.createTextNode('.'));
        erreurs.focus();
      }
      if (APERCU) {
        setTimeout(function () { fini('MC-APERCU'); }, 500);
        return;
      }
      var ctrl = window.AbortController ? new AbortController() : null;
      var minuterie = setTimeout(function () { if (ctrl) ctrl.abort(); }, 20000);
      fetch(form.getAttribute('action'), { method: 'POST', body: donnees, headers: { 'Accept': 'application/json' }, signal: ctrl ? ctrl.signal : undefined })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (j) {
          clearTimeout(minuterie);
          if (j && j.ok && j.ref) fini(j.ref);
          else echec((j && j.erreurs && j.erreurs.length) ? j.erreurs.join(' ') : 'L’envoi n’a pas abouti.');
        })
        .catch(function () { clearTimeout(minuterie); echec('L’envoi n’a pas abouti (connexion interrompue\u00a0?).'); });
    });
  }

  compteur();
  pageGateau();
  pageDemande();
  window.addEventListener('storage', function (e) { if (e.key === CLE) compteur(); });
})();

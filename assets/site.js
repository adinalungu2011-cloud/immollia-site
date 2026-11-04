// IMMOLLIA, script commun à toutes les pages.
(function () {
  // Menu mobile
  var btn = document.getElementById('menu-btn'), nav = document.getElementById('nav');
  if (btn && nav) {
    btn.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) { nav.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); }
    });
  }

  // Afficher / masquer les notes de prévisualisation (choix mémorisé sur cet appareil)
  var tn = document.getElementById('toggle-notes');
  if (tn) {
    var setNotes = function (off) {
      document.body.classList.toggle('notes-off', off);
      tn.textContent = off ? 'Afficher les notes' : 'Masquer les notes';
      tn.setAttribute('aria-pressed', off ? 'false' : 'true');
    };
    try { setNotes(localStorage.getItem('immollia-notes') === 'off'); } catch (e) {}
    tn.addEventListener('click', function () {
      var off = !document.body.classList.contains('notes-off');
      setNotes(off);
      try { localStorage.setItem('immollia-notes', off ? 'off' : 'on'); } catch (e) {}
    });
  }

  // Boutons « data-intent » : cochent le bon motif et pré-remplissent le message
  var form = document.getElementById('contact-form');
  if (form) {
    var msg = document.getElementById('f-message');
    document.querySelectorAll('[data-intent]').forEach(function (a) {
      a.addEventListener('click', function () {
        var motif = a.getAttribute('data-motif');
        if (motif) { var r = form.querySelector('input[name="motif"][value="' + motif + '"]'); if (r) r.checked = true; }
        if (msg && !msg.value.trim()) msg.value = a.getAttribute('data-intent');
      });
    });

    // Formulaire : « Rejoindre la communauté » = inscription (compteur de membres + lien WhatsApp),
    // les autres motifs = message envoyé à contact@immollia.fr
    var WHATSAPP_URL = 'https://chat.whatsapp.com/Djf761IEdpFA8f8ASbIshz';
    var out = document.getElementById('form-result');
    var envoi = form.querySelector('button[type="submit"]');
    var erreur = function (t, champ) {
      out.hidden = false; out.className = 'form-status form-status--error'; out.textContent = t;
      if (champ) champ.focus();
    };
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      out.hidden = true;
      var g = function (n) { var el = form.elements[n]; return el ? String(el.value || '').trim() : ''; };
      var motif = (form.querySelector('input[name="motif"]:checked') || {}).value || 'rejoindre';
      if (!form.checkValidity()) {
        var bad = form.querySelector(':invalid');
        return erreur(bad && bad.type === 'email' && bad.value
          ? "L'adresse e-mail semble incomplète. Vérifiez-la puis réessayez."
          : 'Merci de remplir votre prénom, votre nom, votre e-mail, votre téléphone et votre activité.', bad);
      }
      if (motif !== 'rejoindre' && !g('message')) return erreur('Merci d\'écrire un petit message.', msg);
      var data = { motif: motif, prenom: g('prenom'), nom: g('nom'), email: g('email'), tel: g('tel'),
        activite: g('activite'), ville: g('ville'), message: g('message'), site: g('site'), source: 'accueil' };
      envoi.disabled = true;
      fetch(motif === 'rejoindre' ? 'inscription.php' : 'contact.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
      }).then(function (r) { return r.json(); }).then(function (j) {
        envoi.disabled = false;
        if (!j || !j.ok) return erreur((j && j.message) || 'Envoi impossible pour le moment. Réessayez dans un instant.');
        var ok = document.createElement('div');
        ok.className = 'form-done';
        ok.setAttribute('tabindex', '-1');
        var h = document.createElement('h3'), p = document.createElement('p');
        if (motif === 'rejoindre') {
          h.textContent = 'Bienvenue, ' + data.prenom + ' !';
          p.textContent = (j.membres ? 'Vous êtes la ' + j.membres + 'e membre de la communauté IMMOLLIA 🎉 ' : '')
            + 'Dernière étape : rejoignez la communauté sur WhatsApp.';
          if (j.membres) document.querySelectorAll('[data-membres]').forEach(function (el) { el.textContent = j.membres; });
          var wa = document.createElement('a');
          wa.href = WHATSAPP_URL; wa.target = '_blank'; wa.rel = 'noopener';
          wa.className = 'btn btn--primary'; wa.textContent = 'Rejoindre la communauté WhatsApp';
          ok.append(h, p, wa);
        } else {
          h.textContent = 'Merci, ' + data.prenom + ' !';
          p.textContent = 'Votre message est bien envoyé. Une membre de l\'équipe vous répond personnellement.';
          ok.append(h, p);
        }
        form.replaceChildren(ok);
        ok.focus();
      }, function () {
        envoi.disabled = false;
        erreur('Connexion impossible. Vérifiez votre réseau puis réessayez.');
      });
    });
  }

  // Copie d'un texte (adresse, téléphone, réponses) dans le presse-papiers
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var text = b.getAttribute('data-copy') === 'self' ? b.textContent.trim() : document.getElementById(b.getAttribute('data-copy')).value;
      var done = function () { var old = b.textContent; b.textContent = 'Copié'; setTimeout(function () { b.textContent = old; }, 1500); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(done, function () {});
    });
  });

  // Compteur de membres (accueil) : valeur réelle lue sur le serveur
  var nbMembres = document.querySelectorAll('[data-membres]');
  if (nbMembres.length && window.fetch) {
    fetch('membres.php', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
      if (j && j.membres) nbMembres.forEach(function (el) { el.textContent = j.membres; });
    }, function () {});
  }
})();

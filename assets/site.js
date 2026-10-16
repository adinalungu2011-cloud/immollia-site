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

    // Formulaire : vérifie les champs, n'envoie rien (pas encore connecté)
    var out = document.getElementById('form-result');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      out.hidden = false;
      if (!form.checkValidity()) {
        var bad = form.querySelector(':invalid');
        out.className = 'form-status form-status--error';
        out.textContent = bad && bad.type === 'email' && bad.value
          ? "L'adresse e-mail semble incomplète. Vérifiez-la puis réessayez."
          : 'Merci de remplir votre nom, votre e-mail, votre activité et un message.';
        if (bad) bad.focus();
        return;
      }
      out.className = 'form-status';
      out.textContent = "Message non envoyé : ce formulaire n'est pas encore relié à contact@immollia.fr. Les champs sont bien remplis, mais rien n'a été transmis.";
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
})();

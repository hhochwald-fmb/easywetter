/* EasyWetter – allgemeine Helfer für alle Seiten */
(function () {
  'use strict';

  var EW = (window.EW = window.EW || {});

  EW.icons = function () {
    if (window.lucide && window.lucide.createIcons) {
      window.lucide.createIcons();
    }
  };

  EW.copy = function (text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy') ? resolve() : reject(); } catch (e) { reject(e); }
      document.body.removeChild(ta);
    });
  };

  // Bestätigungsdialog für gefährliche Formulare
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) {
      e.preventDefault();
    }
  }, true);

  document.addEventListener('click', function (e) {
    // Kopieren
    var b = e.target.closest('[data-copy],[data-copy-target]');
    if (b) {
      var text = b.getAttribute('data-copy');
      if (text === null) {
        var t = document.querySelector(b.getAttribute('data-copy-target'));
        text = t ? (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' ? t.value : t.textContent) : '';
      }
      EW.copy(text).then(function () {
        var old = b.getAttribute('data-label') || b.innerHTML;
        b.setAttribute('data-label', old);
        b.textContent = '✓ Kopiert';
        setTimeout(function () { b.innerHTML = old; EW.icons(); }, 1600);
      }, function () { window.prompt('Bitte manuell kopieren:', text); });
      return;
    }
    // Drucken
    if (e.target.closest('[data-print]')) {
      window.print();
      return;
    }
    // Passwort anzeigen
    var tp = e.target.closest('[data-toggle-password]');
    if (tp) {
      var inp = document.querySelector(tp.getAttribute('data-toggle-password'));
      if (inp) inp.type = inp.type === 'password' ? 'text' : 'password';
      return;
    }
    // Tabs
    var tab = e.target.closest('[data-tabs] [data-tab]');
    if (tab) {
      var box = tab.closest('[data-tabs]');
      box.querySelectorAll('[data-tab]').forEach(function (t) {
        var on = t === tab;
        t.classList.toggle('active', on);
        var panel = document.getElementById(t.getAttribute('data-tab'));
        if (panel) panel.hidden = !on;
      });
      return;
    }
    // Offene Popover schließen, wenn außerhalb geklickt wird
    document.querySelectorAll('details.pop[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('details.pop[open]').forEach(function (d) { d.removeAttribute('open'); });
    }
  });

  // Seiten, die sich selbst aktualisieren (Klassenübersicht)
  var ar = document.querySelector('[data-autoreload]');
  if (ar) {
    var sec = Math.max(15, parseInt(ar.getAttribute('data-autoreload'), 10) || 60);
    setInterval(function () {
      var busy = document.querySelector('details.pop[open]') || (document.activeElement && /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName));
      if (!document.hidden && !busy) window.location.reload();
    }, sec * 1000);
  }

  EW.icons();
})();

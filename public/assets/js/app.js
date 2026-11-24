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

  // Popover (details.pop) fest im Fenster positionieren, damit Tabellen/Karten
  // mit overflow es nicht abschneiden. Öffnet nach oben, wenn unten kein Platz ist.
  function placePop(d) {
    var body = d.querySelector('.pop-body');
    var sum = d.querySelector('summary');
    if (!body || !sum) return;
    var vw = document.documentElement.clientWidth;
    var vh = window.innerHeight;
    body.style.position = 'fixed';
    body.style.right = 'auto';
    body.style.bottom = 'auto';
    var w = Math.min(body.offsetWidth || 300, vw - 32);
    body.style.width = w + 'px';
    var r = sum.getBoundingClientRect();
    var h = body.offsetHeight;
    var left = Math.max(16, Math.min(r.right - w, vw - w - 16));
    var top = r.bottom + 6;
    if (top + h > vh - 16 && r.top - h - 6 > 16) top = r.top - h - 6;
    top = Math.max(16, Math.min(top, vh - h - 16));
    body.style.left = left + 'px';
    body.style.top = top + 'px';
    body.style.maxHeight = (vh - 32) + 'px';
    body.style.overflowY = 'auto';
  }

  document.addEventListener('toggle', function (e) {
    var d = e.target;
    if (!d.matches || !d.matches('details.pop')) return;
    if (d.open) {
      // andere offene Popover schließen
      document.querySelectorAll('details.pop[open]').forEach(function (o) { if (o !== d) o.removeAttribute('open'); });
      placePop(d);
      var first = d.querySelector('.pop-body input:not([type=hidden]), .pop-body select, .pop-body textarea');
      if (first) first.focus();
    }
  }, true);

  function repositionOpen() {
    document.querySelectorAll('details.pop[open]').forEach(placePop);
  }
  window.addEventListener('resize', repositionOpen);
  window.addEventListener('scroll', repositionOpen, true);

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

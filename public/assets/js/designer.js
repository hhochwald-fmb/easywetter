/* EasyWetter – Dashboard-Designer für Schülergruppen */
(function () {
  'use strict';

  var D = window.EWDash;
  var host = document.getElementById('designer');
  if (!D || !host) return;

  var el = D.el, iconEl = D.iconEl;
  var panel = document.getElementById('designer-panel');
  var preview = document.getElementById('dash');
  var config = JSON.parse(document.getElementById('dash-config').textContent);
  var dataUrl = host.getAttribute('data-url');
  var saveUrl = host.getAttribute('data-save-url');
  var dashboardUrl = host.getAttribute('data-dashboard-url');
  var csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';

  var renderer = new D.Renderer(preview, {});
  var data = { stations: [], series: {}, now: Date.now() / 1000 };
  var openId = null;
  var dirty = false;
  var timer = null;
  var statusEl = null;

  var ICONS = ['thermometer', 'thermometer-sun', 'droplets', 'gauge', 'leaf', 'sun', 'sun-medium', 'moon', 'cloud', 'cloud-sun',
    'cloud-rain', 'cloud-snow', 'cloud-lightning', 'cloud-fog', 'snowflake', 'wind', 'tornado', 'rainbow', 'umbrella', 'waves',
    'sprout', 'trees', 'flower-2', 'factory', 'mountain', 'volume-2', 'activity', 'zap', 'flame', 'lightbulb', 'battery-medium',
    'compass', 'map-pin', 'radio-tower', 'satellite', 'rocket', 'atom', 'magnet', 'microscope', 'timer', 'heart', 'star', 'bike',
    'fish', 'bug', 'sticky-note', 'info', 'smile'];

  // ---------- Daten & Vorschau ----------

  function stationById(id) {
    return data.stations.find(function (s) { return s.id === id; }) || null;
  }

  function knownSensors(stationId) {
    var s = stationById(stationId);
    var keys = s ? Object.keys(s.latest || {}) : [];
    Object.keys(D.PRESETS).forEach(function (k) { if (keys.indexOf(k) < 0) keys.push(k); });
    return keys;
  }

  function refreshPreview() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      var keys = D.requiredSeries(config.widgets.length ? config.widgets : D.autoWidgets(data.stations));
      var missing = keys.filter(function (k) { return !(k in data.series); });
      var p = missing.length ? D.fetchData(dataUrl, missing).then(function (d) {
        Object.keys(d.series || {}).forEach(function (k) { data.series[k] = d.series[k]; });
        missing.forEach(function (k) { if (!(k in data.series)) data.series[k] = []; });
      }) : Promise.resolve();
      p.catch(function () {}).then(function () { renderer.render(config, data); });
    }, 200);
  }

  function changed(rebuild) {
    dirty = true;
    setStatus('Ungespeicherte Änderungen', 'warn');
    if (rebuild) buildPanel();
    refreshPreview();
  }

  function setStatus(text, kind) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.className = 'small ' + (kind === 'ok' ? 'badge badge-success' : kind === 'warn' ? 'badge badge-warn' : kind === 'err' ? 'badge badge-danger' : 'muted');
  }

  // ---------- Formular-Bausteine ----------

  function field(label, input, hint) {
    return el('div', { class: 'field' }, [el('label', { text: label }), input, hint ? el('span', { class: 'hint', text: hint }) : null]);
  }

  function textInput(value, onInput, attrs) {
    var i = el('input', Object.assign({ type: 'text', value: value || '' }, attrs || {}));
    i.addEventListener('input', function () { onInput(i.value); });
    return i;
  }

  function numInput(value, onInput) {
    var i = el('input', { type: 'number', step: 'any', value: String(value) });
    i.addEventListener('input', function () { var v = parseFloat(i.value); if (!isNaN(v)) onInput(v); });
    return i;
  }

  function select(options, value, onChange) {
    var s = el('select');
    options.forEach(function (o) {
      var opt = el('option', { value: String(o[0]), text: o[1] });
      if (String(o[0]) === String(value)) opt.selected = true;
      s.appendChild(opt);
    });
    s.addEventListener('change', function () { onChange(s.value); });
    return s;
  }

  function colorInput(value, onInput) {
    var i = el('input', { type: 'color', value: value });
    i.addEventListener('input', function () { onInput(i.value); });
    return i;
  }

  function iconPicker(value, onPick) {
    var box = el('div', { class: 'icon-picker' });
    ICONS.forEach(function (name) {
      var b = el('button', { type: 'button', title: name, class: name === value ? 'active' : '' }, [iconEl(name)]);
      b.addEventListener('click', function () {
        box.querySelectorAll('button').forEach(function (x) { x.classList.remove('active'); });
        b.classList.add('active');
        onPick(name);
      });
      box.appendChild(b);
    });
    return box;
  }

  // ---------- Panel ----------

  function buildGeneral() {
    var sec = el('div', null, [el('h3', { text: 'Allgemein' })]);
    sec.appendChild(field('Titel', textInput(config.title, function (v) { config.title = v; changed(); }, { maxlength: 80 })));
    sec.appendChild(field('Untertitel', textInput(config.subtitle, function (v) { config.subtitle = v; changed(); }, { maxlength: 160, placeholder: 'z. B. Messstation auf dem Schuldach' })));

    var sw = el('div', { class: 'theme-swatches' });
    D.THEMES.forEach(function (t) {
      var b = el('button', { type: 'button', class: 'swatch' + (config.theme === t.id ? ' active' : '') }, [
        el('span', { class: 'sw-prev', style: { background: t.prev } }),
        el('span', { class: 'sw-name', text: t.name })
      ]);
      b.addEventListener('click', function () {
        config.theme = t.id;
        sw.querySelectorAll('.swatch').forEach(function (x) { x.classList.remove('active'); });
        b.classList.add('active');
        changed();
      });
      sw.appendChild(b);
    });
    sec.appendChild(el('div', { class: 'field' }, [el('label', { text: 'Design' }), sw]));
    sec.appendChild(el('div', { class: 'form-grid', style: { 'grid-template-columns': '1fr 1fr' } }, [
      field('Akzentfarbe', colorInput(config.accent, function (v) { config.accent = v; changed(); })),
      field('Aktualisieren', select([[30, 'alle 30 s'], [60, 'jede Minute'], [300, 'alle 5 Min.']], config.refresh, function (v) { config.refresh = parseInt(v, 10); changed(); }))
    ]));
    return sec;
  }

  function widgetTitle(w) {
    var st = stationById(w.station);
    var base = w.label || D.preset(w.sensor).label;
    if (w.type === 'text' || w.type === 'status') return base || D.TYPE_NAMES[w.type];
    return base + (st && data.stations.length > 1 ? ' · ' + st.name : '');
  }

  function buildWidgetItem(w, idx) {
    var item = el('div', { class: 'witem' });
    var titleSpan = el('span', { class: 'wt', text: widgetTitle(w) });
    var head = el('div', { class: 'witem-head' }, [
      el('span', { style: { color: w.color } }, [iconEl(D.TYPE_ICONS[w.type] || 'square', 'icon')]),
      titleSpan,
      el('span', { class: 'wbadge', text: D.TYPE_NAMES[w.type] }),
      btn('arrow-up', 'Nach oben', idx === 0, function () { move(idx, -1); }),
      btn('arrow-down', 'Nach unten', idx === config.widgets.length - 1, function () { move(idx, 1); }),
      btn('copy', 'Duplizieren', false, function () {
        var c = JSON.parse(JSON.stringify(w));
        c.id = Math.random().toString(36).slice(2, 10);
        config.widgets.splice(idx + 1, 0, c);
        openId = c.id;
        changed(true);
      }),
      btn('trash-2', 'Entfernen', false, function () {
        config.widgets.splice(idx, 1);
        changed(true);
      })
    ]);
    head.addEventListener('click', function (e) {
      if (e.target.closest('button')) return;
      openId = openId === w.id ? null : w.id;
      buildPanel();
    });
    item.appendChild(head);

    if (openId !== w.id) return item;

    var body = el('div', { class: 'witem-body' });
    var upd = function (k, rebuild) {
      return function (v) { w[k] = v; titleSpan.textContent = widgetTitle(w); changed(rebuild); };
    };

    body.appendChild(field('Baustein-Art', select(Object.keys(D.TYPE_NAMES).map(function (t) { return [t, D.TYPE_NAMES[t]]; }), w.type, function (v) {
      w.type = v;
      if (v === 'chart' && w.size === 's') w.size = 'm';
      changed(true);
    })));

    if (w.type !== 'text' && w.type !== 'status') {
      var grid = el('div', { class: 'form-grid' });
      if (data.stations.length) {
        grid.appendChild(field('Station', select(data.stations.map(function (s) { return [s.id, s.name]; }), w.station, function (v) { w.station = parseInt(v, 10); changed(true); })));
      }
      var keys = knownSensors(w.station);
      if (keys.indexOf(w.sensor) < 0) keys.unshift(w.sensor);
      var opts = keys.map(function (k) {
        var p = D.PRESETS[k];
        var s = stationById(w.station);
        var has = s && s.latest && s.latest[k];
        return [k, (p ? p.label + ' (' + k + ')' : k) + (has ? ' ✓' : '')];
      });
      opts.push(['__custom', 'Eigener Name …']);
      grid.appendChild(field('Messwert', select(opts, w.sensor, function (v) {
        if (v === '__custom') {
          var name = (window.prompt('Name des Messwerts (wie im Programm, z. B. "temp2"):') || '').trim().toLowerCase();
          if (!/^[a-z0-9_]{1,32}$/.test(name)) { buildPanel(); return; }
          v = name;
        }
        var p = D.preset(v);
        w.sensor = v; w.label = p.label; w.unit = p.unit; w.icon = p.icon; w.color = p.color; w.min = p.min; w.max = p.max;
        changed(true);
      }), '✓ = hat schon Daten gesendet. Fehlt ein Name? Er erscheint automatisch, sobald eure Station ihn zum ersten Mal sendet (z. B. add("bodentemp", …)).'));
      body.appendChild(grid);
    }

    var g2 = el('div', { class: 'form-grid' });
    g2.appendChild(field(w.type === 'text' ? 'Überschrift' : 'Beschriftung', textInput(w.label, upd('label'), { maxlength: 40 })));
    if (w.type !== 'text' && w.type !== 'status') g2.appendChild(field('Einheit', textInput(w.unit, upd('unit'), { maxlength: 12 })));
    body.appendChild(g2);

    if (w.type === 'text') {
      var ta = el('textarea', { maxlength: 600, rows: 4 });
      ta.value = w.text || '';
      ta.addEventListener('input', function () { w.text = ta.value; changed(); });
      body.appendChild(field('Text', ta));
    }

    if (w.type === 'gauge') {
      body.appendChild(el('div', { class: 'form-grid' }, [field('Minimum', numInput(w.min, upd('min'))), field('Maximum', numInput(w.max, upd('max')))]));
    }

    var g3 = el('div', { class: 'form-grid' });
    if (w.type === 'chart' || w.type === 'minmax') {
      g3.appendChild(field('Zeitraum', select(Object.keys(D.RANGE_NAMES).map(function (r) { return [r, D.RANGE_NAMES[r]]; }), w.range, upd('range'))));
    }
    if (w.type === 'chart') {
      g3.appendChild(field('Darstellung', select([['area', 'Fläche'], ['line', 'Linie'], ['bar', 'Balken']], w.style, upd('style'))));
    }
    if (w.type !== 'text' && w.type !== 'status' && w.type !== 'chart') {
      g3.appendChild(field('Nachkommastellen', select([[0, '0'], [1, '1'], [2, '2'], [3, '3']], w.decimals, function (v) { w.decimals = parseInt(v, 10); changed(); })));
    }
    g3.appendChild(field('Größe', select([['s', 'Klein'], ['m', 'Mittel (2 Spalten)'], ['l', 'Ganze Breite']], w.size, upd('size'))));
    g3.appendChild(field('Farbe', colorInput(w.color, upd('color'))));
    body.appendChild(g3);

    if (w.type !== 'status') {
      body.appendChild(el('div', { class: 'field' }, [el('label', { text: 'Symbol' }), iconPicker(w.icon, function (n) { w.icon = n; changed(); })]));
    }
    item.appendChild(body);
    return item;
  }

  function btn(icon, title, disabled, fn) {
    var b = el('button', { type: 'button', class: 'btn btn-ghost btn-icon btn-sm', title: title, disabled: disabled }, [iconEl(icon, 'icon icon-sm')]);
    b.addEventListener('click', function (e) { e.stopPropagation(); fn(); });
    return b;
  }

  function move(idx, dir) {
    var j = idx + dir;
    if (j < 0 || j >= config.widgets.length) return;
    var t = config.widgets[idx];
    config.widgets[idx] = config.widgets[j];
    config.widgets[j] = t;
    changed(true);
  }

  function buildPanel() {
    var scroll = panel.parentNode.scrollTop;
    panel.replaceChildren();
    panel.appendChild(buildGeneral());
    panel.appendChild(el('hr'));

    panel.appendChild(el('div', { class: 'row row-between', style: { 'margin-bottom': '10px' } }, [
      el('h3', { class: 'mb-0', text: 'Bausteine (' + config.widgets.length + ')' }),
      (function () {
        var b = el('button', { type: 'button', class: 'btn btn-sm', title: 'Bausteine aus euren Messwerten vorschlagen' }, [iconEl('sparkles', 'icon icon-sm'), ' Vorschlag']);
        b.addEventListener('click', function () {
          if (config.widgets.length && !window.confirm('Alle Bausteine durch einen Vorschlag ersetzen?')) return;
          config.widgets = D.autoWidgets(data.stations);
          if (!config.widgets.length) window.alert('Es gibt noch keine Messwerte, aus denen ein Vorschlag erstellt werden kann.');
          openId = null;
          changed(true);
        });
        return b;
      })()
    ]));

    var list = el('div', { class: 'wlist' });
    if (!config.widgets.length) {
      list.appendChild(el('p', { class: 'muted small', text: 'Noch keine Bausteine – es wird die automatische Ansicht gezeigt. Fügt unten Bausteine hinzu!' }));
    }
    config.widgets.forEach(function (w, i) { list.appendChild(buildWidgetItem(w, i)); });
    panel.appendChild(list);

    panel.appendChild(el('h3', { style: { 'margin-top': '16px' }, text: 'Hinzufügen' }));
    var add = el('div', { class: 'addbar' });
    Object.keys(D.TYPE_NAMES).forEach(function (t) {
      var b = el('button', { type: 'button', class: 'btn' }, [iconEl(D.TYPE_ICONS[t], 'icon'), D.TYPE_NAMES[t]]);
      b.addEventListener('click', function () {
        if (config.widgets.length >= 24) { window.alert('Maximal 24 Bausteine.'); return; }
        var st = data.stations[0];
        var sensor = st ? (Object.keys(st.latest || {})[0] || 'temp') : 'temp';
        var w = D.makeWidget(t, st ? st.id : 0, sensor);
        config.widgets.push(w);
        openId = w.id;
        changed(true);
      });
      add.appendChild(b);
    });
    panel.appendChild(add);

    statusEl = el('span', { class: 'small muted', text: dirty ? '' : 'Alles gespeichert' });
    var save = el('button', { type: 'button', class: 'btn btn-primary' }, [iconEl('check', 'icon'), ' Speichern']);
    save.addEventListener('click', doSave);
    panel.appendChild(el('div', { class: 'savebar' }, [save, el('a', { class: 'btn', href: dashboardUrl, text: 'Ansehen' }), statusEl]));
    if (dirty) setStatus('Ungespeicherte Änderungen', 'warn');

    if (window.lucide) window.lucide.createIcons();
    panel.parentNode.scrollTop = scroll;
  }

  function doSave() {
    setStatus('Speichere …');
    fetch(saveUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Accept: 'application/json' },
      body: JSON.stringify(config)
    }).then(function (r) {
      return r.json().then(function (j) { if (!r.ok || !j.ok) throw new Error(j.error || 'HTTP ' + r.status); return j; });
    }).then(function (j) {
      config = j.config;
      dirty = false;
      buildPanel();
      setStatus('✓ Gespeichert', 'ok');
      refreshPreview();
    }).catch(function (e) {
      setStatus('Fehler: ' + e.message + ' – bitte Seite neu laden', 'err');
    });
  }

  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  // Start
  D.fetchData(dataUrl, D.requiredSeries(config.widgets)).then(function (d) {
    data = d;
    data.series = d.series || {};
  }).catch(function () {}).then(function () {
    buildPanel();
    refreshPreview();
  });
})();

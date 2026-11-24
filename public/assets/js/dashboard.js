/* EasyWetter – Dashboard-Renderer (wird auch vom Designer für die Live-Vorschau genutzt) */
(function () {
  'use strict';

  var PRESETS = {
    temp: { label: 'Temperatur', unit: '°C', icon: 'thermometer', color: '#ef4444', min: -10, max: 40 },
    hum: { label: 'Luftfeuchte', unit: '%', icon: 'droplets', color: '#3b82f6', min: 0, max: 100 },
    press: { label: 'Luftdruck', unit: 'hPa', icon: 'gauge', color: '#10b981', min: 950, max: 1050 },
    co2: { label: 'CO₂', unit: 'ppm', icon: 'leaf', color: '#84cc16', min: 400, max: 2000 },
    light: { label: 'Helligkeit', unit: 'lx', icon: 'sun', color: '#f59e0b', min: 0, max: 1000 },
    uv: { label: 'UV-Index', unit: '', icon: 'sun-medium', color: '#a855f7', min: 0, max: 11 },
    wind: { label: 'Wind', unit: 'km/h', icon: 'wind', color: '#06b6d4', min: 0, max: 80 },
    rain: { label: 'Regen', unit: 'mm', icon: 'cloud-rain', color: '#0ea5e9', min: 0, max: 30 },
    soil: { label: 'Bodenfeuchte', unit: '%', icon: 'sprout', color: '#65a30d', min: 0, max: 100 },
    pm25: { label: 'Feinstaub PM2.5', unit: 'µg/m³', icon: 'factory', color: '#64748b', min: 0, max: 100 },
    noise: { label: 'Lautstärke', unit: 'dB', icon: 'volume-2', color: '#ec4899', min: 20, max: 110 },
    alt: { label: 'Höhe', unit: 'm', icon: 'mountain', color: '#78716c', min: 0, max: 1000 }
  };
  var THEMES = [
    { id: 'hell', name: 'Hell', prev: 'linear-gradient(135deg,#f6f7fb,#e2e8f0)' },
    { id: 'dunkel', name: 'Dunkel', prev: 'linear-gradient(135deg,#0b1120,#1e293b)' },
    { id: 'ozean', name: 'Ozean', prev: 'linear-gradient(135deg,#e0f2fe,#99f6e4)' },
    { id: 'sonnenuntergang', name: 'Abendrot', prev: 'linear-gradient(135deg,#fed7aa,#fbcfe8)' },
    { id: 'wald', name: 'Wald', prev: 'linear-gradient(135deg,#dcfce7,#bef264)' },
    { id: 'weltraum', name: 'Weltraum', prev: 'linear-gradient(135deg,#020617,#312e81,#0e7490)' }
  ];
  var TYPE_NAMES = { value: 'Wert', gauge: 'Zeiger', chart: 'Verlauf', minmax: 'Min / Max', text: 'Notiz', status: 'Stationen' };
  var TYPE_ICONS = { value: 'hash', gauge: 'gauge', chart: 'chart-line', minmax: 'arrow-up-down', text: 'sticky-note', status: 'radio-tower' };
  var RANGE_NAMES = { '6h': '6 Stunden', '24h': '24 Stunden', '7d': '7 Tage', '30d': '30 Tage' };
  var ONLINE_SECONDS = 900;

  function preset(key) {
    return PRESETS[key] || { label: key, unit: '', icon: 'activity', color: '#4f46e5', min: 0, max: 100 };
  }

  /** Sicherer DOM-Baukasten – Texte immer über textContent, nie innerHTML. */
  function el(tag, attrs, children) {
    var n = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (v === null || v === undefined || v === false) return;
        if (k === 'class') n.className = v;
        else if (k === 'text') n.textContent = v;
        else if (k === 'style') Object.keys(v).forEach(function (p) { n.style.setProperty(p, v[p]); });
        else if (k.indexOf('on') === 0 && typeof v === 'function') n.addEventListener(k.slice(2), v);
        else n.setAttribute(k, v === true ? '' : String(v));
      });
    }
    (children || []).forEach(function (c) {
      if (c === null || c === undefined || c === false) return;
      n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return n;
  }

  function iconEl(name, cls) {
    var i = document.createElement('i');
    i.setAttribute('data-lucide', /^[a-z0-9-]+$/.test(name || '') ? name : 'circle');
    if (cls) i.className = cls;
    return i;
  }

  function fmt(v, d) {
    if (v === null || v === undefined || isNaN(v)) return '–';
    return Number(v).toLocaleString('de-DE', { minimumFractionDigits: d, maximumFractionDigits: d });
  }

  function ago(ts, now) {
    if (!ts) return 'noch nie';
    var d = Math.max(0, (now || Date.now() / 1000) - ts);
    if (d < 60) return 'gerade eben';
    if (d < 3600) return 'vor ' + Math.floor(d / 60) + ' Min.';
    if (d < 86400) return 'vor ' + Math.floor(d / 3600) + ' Std.';
    return 'vor ' + Math.floor(d / 86400) + ' Tagen';
  }

  function hexA(hex, a) {
    var m = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex || '');
    if (!m) return 'rgba(79,70,229,' + a + ')';
    return 'rgba(' + parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16) + ',' + a + ')';
  }

  function seriesKey(w) { return w.station + ':' + w.sensor + ':' + w.range; }
  function needsSeries(w) { return w.type === 'chart' || w.type === 'minmax'; }

  function requiredSeries(widgets) {
    var keys = [];
    widgets.forEach(function (w) {
      if (needsSeries(w) && w.station) {
        var k = seriesKey(w);
        if (keys.indexOf(k) < 0) keys.push(k);
      }
    });
    return keys;
  }

  function makeWidget(type, station, sensor) {
    var p = preset(sensor);
    return {
      id: Math.random().toString(36).slice(2, 10),
      type: type, station: station || 0, sensor: sensor || 'temp',
      label: type === 'text' ? 'Notiz' : (type === 'status' ? 'Unsere Stationen' : p.label),
      unit: p.unit, color: p.color, icon: type === 'text' ? 'sticky-note' : p.icon,
      min: p.min, max: p.max, decimals: 1, range: '24h',
      size: type === 'chart' ? 'm' : 's', style: 'area',
      text: type === 'text' ? 'Hier könnt ihr etwas über eure Station schreiben.' : ''
    };
  }

  /** Automatische Ansicht, solange eine Gruppe noch nichts gestaltet hat. */
  function autoWidgets(stations) {
    var out = [];
    stations.slice(0, 4).forEach(function (s) {
      Object.keys(s.latest || {}).slice(0, 8).forEach(function (k) {
        out.push(makeWidget('value', s.id, k));
      });
    });
    var first = stations.find(function (s) { return Object.keys(s.latest || {}).length; });
    if (first) {
      var key = PRESETS.temp && first.latest.temp ? 'temp' : Object.keys(first.latest)[0];
      var c = makeWidget('chart', first.id, key);
      c.size = 'l';
      out.push(c);
    }
    return out;
  }

  function fetchData(url, keys) {
    var u = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'series=' + encodeURIComponent((keys || []).join(','));
    return fetch(u, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  // ------------------------------------------------------------------

  function Renderer(root, opts) {
    this.root = root;
    this.opts = opts || {};
    this.charts = [];
    this.rendered = false;
  }

  Renderer.prototype.destroy = function () {
    this.charts.forEach(function (c) { c.destroy(); });
    this.charts = [];
  };

  Renderer.prototype.render = function (config, data) {
    var self = this;
    var root = this.root;
    var stations = data.stations || [];
    var now = data.now || Date.now() / 1000;
    this.destroy();
    root.replaceChildren();
    root.setAttribute('data-theme', config.theme || 'hell');
    root.style.setProperty('--accent', config.accent || '#4f46e5');

    // Kopf
    var chips = el('div', { class: 'dash-status' });
    stations.forEach(function (s) {
      var on = s.last_seen && now - s.last_seen < ONLINE_SECONDS;
      chips.appendChild(el('span', { class: 'dash-chip', title: 'Letzte Daten: ' + ago(s.last_seen, now) }, [
        el('span', { class: 'dot ' + (on ? 'dot-on' : (s.last_seen ? 'dot-off' : '')) }), s.name
      ]));
    });
    root.appendChild(el('div', { class: 'dash-head' }, [
      el('div', null, [
        el('h1', { class: 'dash-title', text: config.title || 'Unsere Wetterstation' }),
        config.subtitle ? el('p', { class: 'dash-sub', text: config.subtitle }) : null
      ]),
      chips
    ]));

    var widgets = config.widgets && config.widgets.length ? config.widgets : autoWidgets(stations);

    if (!stations.length && !config.widgets.length) {
      var box = el('div', { class: 'dash-empty' }, [
        iconEl('radio-tower', 'icon icon-lg'),
        el('h3', { text: 'Noch keine Station' }),
        el('p', { text: this.opts.public ? 'Hier erscheinen bald Messwerte.' : 'Legt eine Station an und sendet die ersten Messwerte – dann erscheinen sie hier.' })
      ]);
      if (this.opts.stationsUrl) box.appendChild(el('a', { class: 'btn btn-primary', href: this.opts.stationsUrl, text: 'Station anlegen' }));
      root.appendChild(box);
    } else {
      if (!config.widgets.length && this.opts.designerUrl) {
        root.appendChild(el('div', { class: 'alert alert-info' }, [
          iconEl('sparkles', 'icon'),
          el('div', null, ['Das ist die automatische Ansicht. ', el('a', { href: this.opts.designerUrl, text: 'Gestaltet jetzt euer eigenes Dashboard!' })])
        ]));
      }
      var grid = el('div', { class: 'dash-grid' });
      widgets.forEach(function (w) {
        var st = stations.find(function (s) { return s.id === w.station; }) || stations[0] || null;
        grid.appendChild(self.widget(w, st, data, stations.length > 1, now));
      });
      root.appendChild(grid);
    }

    var foot = el('div', { class: 'dash-footer' }, ['Aktualisiert ' + new Date(now * 1000).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })]);
    if (this.opts.public) {
      foot.appendChild(document.createTextNode(' · Erstellt mit '));
      var home = this.opts.homeUrl || '/';
      foot.appendChild(el('a', { href: home, text: 'EasyWetter', target: '_blank', rel: 'noopener' }));
      foot.appendChild(document.createTextNode(' · '));
      foot.appendChild(el('a', { href: home + 'impressum', text: 'Impressum', target: '_blank', rel: 'noopener' }));
      foot.appendChild(document.createTextNode(' · '));
      foot.appendChild(el('a', { href: home + 'datenschutz', text: 'Datenschutz', target: '_blank', rel: 'noopener' }));
    }
    root.appendChild(foot);

    if (window.lucide) window.lucide.createIcons();
    this.rendered = true;
  };

  Renderer.prototype.head = function (w, st, showStation) {
    var p = preset(w.sensor);
    var label = w.label || p.label;
    return el('div', { class: 'w-head' }, [
      el('span', { class: 'w-ic', style: { background: hexA(w.color, 0.14), color: w.color } }, [iconEl(w.icon || p.icon)]),
      el('span', { text: label + (showStation && st && w.type !== 'text' && w.type !== 'status' ? ' · ' + st.name : '') })
    ]);
  };

  Renderer.prototype.widget = function (w, st, data, multi, now) {
    var card = el('div', { class: 'w w-' + (w.size || 's'), 'data-type': w.type });
    var latest = st && st.latest ? st.latest[w.sensor] : null;
    var unit = w.unit;
    var self = this;

    if (w.type === 'text') {
      card.appendChild(el('div', { class: 'w-text' }, [
        w.label ? el('h4', { text: w.label, style: { color: w.color } }) : null,
        el('div', { text: w.text || '' })
      ]));
      return card;
    }

    card.appendChild(this.head(w, st, multi));

    if (w.type === 'status') {
      var ul = el('ul', { class: 'w-status' });
      (data.stations || []).forEach(function (s) {
        var on = s.last_seen && now - s.last_seen < ONLINE_SECONDS;
        ul.appendChild(el('li', null, [
          el('span', null, [el('span', { class: 'dot ' + (on ? 'dot-on' : (s.last_seen ? 'dot-off' : '')) }), ' ' + s.name]),
          el('span', { style: { color: 'var(--d-muted)' }, text: on ? 'online' : ago(s.last_seen, now) })
        ]));
      });
      if (!(data.stations || []).length) ul.appendChild(el('li', { text: 'Keine Stationen' }));
      card.appendChild(ul);
      return card;
    }

    if (!st) {
      card.appendChild(el('div', { class: 'w-empty', text: 'Keine Station ausgewählt.' }));
      return card;
    }

    if (w.type === 'value') {
      card.appendChild(el('div', { class: 'w-value', style: { color: w.color } }, [
        latest ? fmt(latest.v, w.decimals) : '–',
        el('small', { text: unit })
      ]));
      card.appendChild(el('div', { class: 'w-foot' }, [
        el('span', { text: latest ? ago(latest.t, now) : 'noch keine Daten für „' + w.sensor + '“' })
      ]));
      card.appendChild(el('div', { class: 'w-bg-icon', style: { color: w.color } }, [iconEl(w.icon)]));
      return card;
    }

    if (w.type === 'gauge') {
      var v = latest ? latest.v : null;
      var pct = v === null ? 0 : Math.max(0, Math.min(100, (v - w.min) / (w.max - w.min) * 100));
      var NS = 'http://www.w3.org/2000/svg';
      var svg = document.createElementNS(NS, 'svg');
      svg.setAttribute('viewBox', '0 0 200 112');
      var d = 'M 20 100 A 80 80 0 0 1 180 100';
      var bg = document.createElementNS(NS, 'path');
      bg.setAttribute('d', d); bg.setAttribute('fill', 'none'); bg.setAttribute('stroke', 'var(--d-border)');
      bg.setAttribute('stroke-width', '16'); bg.setAttribute('stroke-linecap', 'round');
      var fg = document.createElementNS(NS, 'path');
      fg.setAttribute('d', d); fg.setAttribute('fill', 'none'); fg.setAttribute('stroke', w.color);
      fg.setAttribute('stroke-width', '16'); fg.setAttribute('stroke-linecap', 'round');
      fg.setAttribute('pathLength', '100'); fg.setAttribute('stroke-dasharray', pct.toFixed(2) + ' 100');
      if (pct === 0) fg.setAttribute('stroke-opacity', '0');
      svg.appendChild(bg); svg.appendChild(fg);
      card.appendChild(el('div', { class: 'w-gauge' }, [
        svg,
        el('div', { class: 'g-val' }, [fmt(v, w.decimals), el('small', { text: ' ' + unit })]),
        el('div', { class: 'g-range' }, [el('span', { text: fmt(w.min, 0) }), el('span', { text: fmt(w.max, 0) })])
      ]));
      card.appendChild(el('div', { class: 'w-foot' }, [el('span', { text: latest ? ago(latest.t, now) : 'noch keine Daten' })]));
      return card;
    }

    var series = (data.series || {})[seriesKey(w)] || [];

    if (w.type === 'minmax') {
      var mn = null, mx = null, sum = 0;
      series.forEach(function (p) {
        mn = mn === null ? p[2] : Math.min(mn, p[2]);
        mx = mx === null ? p[3] : Math.max(mx, p[3]);
        sum += p[1];
      });
      var avg = series.length ? sum / series.length : null;
      function cell(k, val, col) {
        return el('div', null, [el('div', { class: 'k', text: k }), el('div', { class: 'v', style: { color: col }, text: fmt(val, w.decimals) })]);
      }
      card.appendChild(el('div', { class: 'w-minmax' }, [
        cell('Min', mn, '#3b82f6'), cell('Mittel', avg, w.color), cell('Max', mx, '#ef4444')
      ]));
      card.appendChild(el('div', { class: 'w-foot' }, [el('span', { text: 'letzte ' + RANGE_NAMES[w.range] }), el('span', { text: unit })]));
      return card;
    }

    // Verlauf (Chart.js)
    var box = el('div', { class: 'w-chart' });
    card.appendChild(box);
    card.appendChild(el('div', { class: 'w-foot' }, [el('span', { text: 'letzte ' + RANGE_NAMES[w.range] }), el('span', { text: latest ? 'aktuell ' + fmt(latest.v, w.decimals) + ' ' + unit : '' })]));
    if (!series.length) {
      box.appendChild(el('div', { class: 'w-empty', text: 'Für diesen Zeitraum gibt es noch keine Messwerte.' }));
      return card;
    }
    if (!window.Chart) return card;
    var canvas = el('canvas');
    box.appendChild(canvas);
    // Chart erst nach dem Einhängen ins DOM erzeugen
    setTimeout(function () { self.chart(canvas, w, series); }, 0);
    return card;
  };

  Renderer.prototype.chart = function (canvas, w, series) {
    if (!canvas.isConnected) return;
    var cs = getComputedStyle(this.root);
    var muted = cs.getPropertyValue('--d-muted').trim() || '#64748b';
    var gridC = cs.getPropertyValue('--d-grid').trim() || 'rgba(0,0,0,.06)';
    var long = w.range === '7d' || w.range === '30d';
    var points = series.map(function (p) { return { x: p[0] * 1000, y: p[1] }; });
    var ctx = canvas.getContext('2d');
    var fill = false;
    if (w.style === 'area') {
      var g = ctx.createLinearGradient(0, 0, 0, canvas.parentNode.clientHeight || 240);
      g.addColorStop(0, hexA(w.color, 0.35));
      g.addColorStop(1, hexA(w.color, 0));
      fill = { target: 'origin', above: g, below: g };
    }
    var chart = new window.Chart(ctx, {
      type: w.style === 'bar' ? 'bar' : 'line',
      data: {
        datasets: [{
          data: points,
          borderColor: w.color,
          backgroundColor: w.style === 'bar' ? hexA(w.color, 0.7) : hexA(w.color, 0.2),
          fill: fill,
          borderWidth: 2.5,
          pointRadius: 0,
          pointHoverRadius: 4,
          tension: 0.35,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: this.rendered ? false : { duration: 500 },
        interaction: { mode: 'index', intersect: false },
        parsing: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title: function (items) {
                var d = new Date(items[0].parsed.x);
                return d.toLocaleString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
              },
              label: function (item) { return ' ' + fmt(item.parsed.y, w.decimals) + ' ' + w.unit; }
            }
          }
        },
        scales: {
          x: {
            type: 'linear',
            min: points[0].x,
            max: points[points.length - 1].x,
            grid: { display: false },
            border: { display: false },
            ticks: {
              color: muted, maxTicksLimit: 6, maxRotation: 0,
              callback: function (v) {
                var d = new Date(v);
                return long ? d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' })
                  : d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
              }
            }
          },
          y: {
            grid: { color: gridC },
            border: { display: false },
            ticks: { color: muted, maxTicksLimit: 6, callback: function (v) { return fmt(v, Math.abs(v) >= 100 ? 0 : 1); } }
          }
        }
      }
    });
    this.charts.push(chart);
  };

  window.EWDash = {
    PRESETS: PRESETS, THEMES: THEMES, TYPE_NAMES: TYPE_NAMES, TYPE_ICONS: TYPE_ICONS, RANGE_NAMES: RANGE_NAMES,
    preset: preset, el: el, iconEl: iconEl, fmt: fmt, makeWidget: makeWidget, autoWidgets: autoWidgets,
    requiredSeries: requiredSeries, seriesKey: seriesKey, fetchData: fetchData, Renderer: Renderer
  };

  // ---------- Normale Dashboard-Seite ----------
  var root = document.getElementById('dash');
  var cfgEl = document.getElementById('dash-config');
  if (!root || !cfgEl || root.hasAttribute('data-preview')) return;

  var config = JSON.parse(cfgEl.textContent);
  var url = root.getAttribute('data-url');
  var renderer = new Renderer(root, {
    public: root.hasAttribute('data-public'),
    stationsUrl: root.getAttribute('data-stations-url'),
    designerUrl: root.getAttribute('data-designer-url'),
    homeUrl: root.getAttribute('data-home-url') || '/'
  });
  var widgetsFor = function (data) { return config.widgets.length ? config.widgets : autoWidgets(data.stations || []); };
  var lastKeys = requiredSeries(config.widgets);

  function load() {
    return fetchData(url, lastKeys).then(function (data) {
      var keys = requiredSeries(widgetsFor(data));
      var missing = keys.filter(function (k) { return !(k in (data.series || {})); });
      if (missing.length) {
        lastKeys = keys;
        return fetchData(url, keys);
      }
      return data;
    }).then(function (data) {
      renderer.render(config, data);
    }).catch(function () {
      if (!renderer.rendered) {
        root.replaceChildren(el('div', { class: 'dash-empty', text: 'Die Messdaten konnten nicht geladen werden. Bitte später erneut versuchen.' }));
      }
    });
  }

  load();
  setInterval(function () { if (!document.hidden) load(); }, Math.max(30, config.refresh || 60) * 1000);
})();

/*
 * EasyWetter – Prognose-Modelle
 * Alle Modelle rechnen ausschließlich mit den eigenen Messwerten der Station (keine externen Dienste).
 * Jede Funktion liefert: { ok, title, icon, value?, details: [[Bezeichnung, Wert]], why, basis, quality, explain }
 *   quality: 'gering' | 'mittel' | 'hoch'  (wie belastbar ist die Datengrundlage?)
 *   explain: ausführlicher Rechenweg für das „?“-Fenster:
 *     { intro, data: [[Bezeichnung, Wert]], steps: [{ title, text?, formula?, calc?, table?: { head, rows, hi } }], limits: [] }
 *
 * Zeitreihen haben das Format [[unixzeit, mittelwert, min, max], ...] (wie von /api/v1/data geliefert).
 */
(function (root) {
  'use strict';

  var MODELS = {
    pressure: {
      name: 'Drucktendenz', needs: 'Luftdruck',
      intro: 'Die Drucktendenz ist die älteste Methode der Wettervorhersage und funktioniert mit jedem Barometer: Entscheidend ist nicht, wie hoch der Luftdruck ist, sondern wie schnell er sich ändert. Fallender Druck bedeutet, dass ein Tiefdruckgebiet näher kommt (aufsteigende Luft → Wolken, Regen). Steigender Druck kündigt ein Hoch an (absinkende Luft → Wolken lösen sich auf).'
    },
    zambretti: {
      name: 'Zambretti', needs: 'Luftdruck',
      intro: 'Der Zambretti-Vorhersager wurde 1915 von der Londoner Firma Negretti & Zambra als Rechenscheibe für Barometer verkauft. Er kombiniert den Luftdruck auf Meereshöhe, dessen Tendenz und die Jahreszeit und wählt daraus einen von 26 Wettertexten (A bis Z) aus.'
    },
    trend: {
      name: 'Trend-Hochrechnung', needs: 'beliebiger Messwert',
      intro: 'Die Trend-Hochrechnung ist ein rein mathematisches Modell: Durch die Messpunkte der letzten Stunden wird die Gerade gelegt, die am besten passt (lineare Regression, Methode der kleinsten Quadrate). Diese Gerade wird dann in die Zukunft verlängert.'
    },
    dewpoint: {
      name: 'Nebel & Frost (Taupunkt)', needs: 'Temperatur + Luftfeuchte',
      intro: 'Warme Luft kann mehr Wasserdampf aufnehmen als kalte. Kühlt feuchte Luft ab, ist sie irgendwann gesättigt (100 % Luftfeuchte) – diese Temperatur heißt Taupunkt. Wird es noch kälter, kondensiert der Wasserdampf zu Tröpfchen: Es entstehen Nebel, Tau, Wolken oder bei Minusgraden Reif.'
    }
  };

  /** Rechenschritt „Umrechnung auf Meereshöhe“ (barometrische Höhenformel). */
  function qnhStep(p, altitude, temp, qnh) {
    var T = (temp === null || temp === undefined || isNaN(temp)) ? 15 : temp;
    if (!altitude) {
      return {
        title: 'Umrechnung auf Meereshöhe',
        text: 'Mit zunehmender Höhe sinkt der Luftdruck (ca. 1 hPa pro 8 m). Damit Messungen von verschiedenen Orten vergleichbar sind, rechnet man auf Meereshöhe um. Für eure Station ist keine Höhe eingetragen, daher wird ohne Umrechnung gerechnet: p₀ = p = ' + de(p, 1) + ' hPa.'
      };
    }
    return {
      title: 'Umrechnung auf Meereshöhe',
      text: 'Mit zunehmender Höhe sinkt der Luftdruck (ca. 1 hPa pro 8 m). Damit Messungen von verschiedenen Orten vergleichbar sind, rechnet man mit der barometrischen Höhenformel auf Meereshöhe (p₀) um. h ist die Höhe der Station, T die Temperatur' + (temp === null || temp === undefined ? ' (keine Messung vorhanden – Standardwert 15 °C)' : '') + '.',
      formula: 'p₀ = p · ( 1 − 0,0065 · h / (T + 0,0065 · h + 273,15) )^(−5,257)',
      calc: 'p₀ = ' + de(p, 1) + ' hPa · ( 1 − 0,0065 · ' + de(altitude, 0) + ' / (' + par(T, 1) + ' + 0,0065 · ' + de(altitude, 0) + ' + 273,15) )^(−5,257) = ' + de(qnh, 1) + ' hPa'
    };
  }

  /** Rechenschritt „Ausgleichsgerade“ (gemeinsam für Druck-Modelle). */
  function regressionStep(fit, unit, what) {
    return {
      title: 'Ausgleichsgerade durch die Messpunkte',
      text: 'Würde man nur den ersten und letzten Wert vergleichen, könnte ein einzelner Messfehler das Ergebnis verfälschen. Deshalb wird durch alle ' + fit.n + ' Messpunkte die Gerade ' + what + '(t) = a + b · t gelegt, die am besten passt (Methode der kleinsten Quadrate). t ist die Zeit in Stunden, „jetzt“ ist t = 0, frühere Messungen haben negative t. Die Steigung b gibt an, um wie viel sich der Wert pro Stunde ändert.',
      formula: 'b = ( n · Σ(t·' + what + ') − Σt · Σ' + what + ' ) / ( n · Σt² − (Σt)² )',
      calc: 'n = ' + fit.n + ',  Σt = ' + de(fit.sx, 2) + ',  Σ' + what + ' = ' + de(fit.sy, 1) + ',  Σ(t·' + what + ') = ' + de(fit.sxy, 1) + ',  Σt² = ' + de(fit.sxx, 2) + '\n' +
        'b = ' + de(fit.b, 3) + ' ' + unit + ' pro Stunde'
    };
  }

  var PRESSURE_CLASSES = [
    ['mehr als +3,5 hPa', 'Rasche Besserung, noch windig', function (c) { return c > 3.5; }],
    ['+1,5 bis +3,5 hPa', 'Wetterbesserung', function (c) { return c > 1.5; }],
    ['+0,5 bis +1,5 hPa', 'Langsame Besserung', function (c) { return c > 0.5; }],
    ['−0,5 bis +0,5 hPa', 'Beständig', function (c) { return c >= -0.5; }],
    ['−1,5 bis −0,5 hPa', 'Langsame Verschlechterung', function (c) { return c >= -1.5; }],
    ['−3,5 bis −1,5 hPa', 'Verschlechterung, Regen wahrscheinlich', function (c) { return c >= -3.5; }],
    ['weniger als −3,5 hPa', 'Sturm- und Unwettergefahr', function () { return true; }]
  ];

  function de(v, d) {
    if (v === null || v === undefined || isNaN(v)) return '–';
    return Number(v).toLocaleString('de-DE', { minimumFractionDigits: d, maximumFractionDigits: d }).replace('-', '−');
  }
  /** Zahl für Rechnungen: negative Werte in Klammern, z. B. „(−2,5)“. */
  function par(v, d) {
    var t = de(v, d);
    return t.charAt(0) === '−' ? '(' + t + ')' : t;
  }
  function signed(v, d) {
    var r = Math.round(v * Math.pow(10, d)) / Math.pow(10, d);
    return (r > 0 ? '+' : r < 0 ? '−' : '±') + de(Math.abs(r), d);
  }

  /** Warnzeile, wenn die Stationshöhe fehlt, der Druck aber nach Bergstation aussieht. */
  function altitudeWarning(p, altitude) {
    return !altitude && p < 990 ? ['⚠ Höhe fehlt', 'Im Designer die Höhe der Station eintragen – sonst ist die Prognose zu pessimistisch.'] : null;
  }

  /** Lineare Regression y = a + b·x  →  { b (Steigung), a, r2, n } */
  function linreg(pts) {
    var n = pts.length, sx = 0, sy = 0, sxx = 0, sxy = 0, syy = 0;
    for (var i = 0; i < n; i++) {
      var x = pts[i][0], y = pts[i][1];
      sx += x; sy += y; sxx += x * x; sxy += x * y; syy += y * y;
    }
    var den = n * sxx - sx * sx;
    if (n < 2 || den === 0) return null;
    var b = (n * sxy - sx * sy) / den;
    var a = (sy - b * sx) / n;
    var ssTot = syy - sy * sy / n;
    var ssRes = 0;
    for (var j = 0; j < n; j++) { var e = pts[j][1] - (a + b * pts[j][0]); ssRes += e * e; }
    return { a: a, b: b, r2: ssTot > 1e-12 ? Math.max(0, 1 - ssRes / ssTot) : 1, n: n,
      sx: sx, sy: sy, sxx: sxx, sxy: sxy, ssRes: ssRes, ssTot: ssTot };
  }

  /** Punkte der letzten `hours` Stunden, x in Stunden relativ zum letzten Messpunkt (also ≤ 0). */
  function window(series, hours) {
    if (!series || !series.length) return null;
    var tEnd = series[series.length - 1][0];
    var pts = [];
    for (var i = 0; i < series.length; i++) {
      if (series[i][0] >= tEnd - hours * 3600) pts.push([(series[i][0] - tEnd) / 3600, series[i][1]]);
    }
    return { pts: pts, tEnd: tEnd, span: pts.length ? -pts[0][0] : 0 };
  }

  function quality(spanH, needH, n) {
    if (spanH >= needH * 0.9 && n >= 20) return 'hoch';
    if (spanH >= needH * 0.4 && n >= 8) return 'mittel';
    return 'gering';
  }

  function basisText(spanH, n) {
    return 'Datenbasis: ' + de(spanH, 1) + ' h, ' + n + ' Messpunkte';
  }

  /** Luftdruck auf Meereshöhe (QNH) umrechnen – internationale Höhenformel. */
  function seaLevel(p, altitude, tempC) {
    if (!altitude) return p;
    var t = (tempC === null || tempC === undefined || isNaN(tempC)) ? 15 : tempC;
    return p * Math.pow(1 - (0.0065 * altitude) / (t + 0.0065 * altitude + 273.15), -5.257);
  }

  function stale(tEnd, now) {
    return now - tEnd > 2 * 3600;
  }

  function notEnough(msg) {
    return { ok: false, title: 'Noch zu wenig Daten', icon: 'hourglass', details: [], why: msg, basis: '', quality: 'gering' };
  }

  /** Gemeinsame Druck-Auswertung: Tendenz in hPa pro 3 Stunden. */
  function pressureAnalysis(series, now) {
    var w = window(series, 3);
    if (!w || w.pts.length < 5 || w.span < 0.75) {
      return { error: notEnough('Für eine Prognose braucht es Luftdruckwerte aus mindestens 45 Minuten (besser 3 Stunden). Lasst eure Station einfach weiter messen.') };
    }
    if (stale(w.tEnd, now)) {
      return { error: { ok: false, title: 'Daten veraltet', icon: 'clock', details: [], why: 'Die letzte Luftdruck-Messung ist älter als 2 Stunden. Sendet die Station noch?', basis: '', quality: 'gering' } };
    }
    var fit = linreg(w.pts);
    return { change3h: fit.b * 3, fit: fit, w: w, current: w.pts[w.pts.length - 1][1] };
  }

  // ---------------------------------------------------------------------------
  // Modell 1: Drucktendenz (Barometer-Regel)
  // ---------------------------------------------------------------------------
  function pressureModel(series, opts) {
    var A = pressureAnalysis(series, opts.now);
    if (A.error) return A.error;
    var c = A.change3h;
    var qnh = seaLevel(A.current, opts.altitude, opts.temp);
    var r;
    if (c > 3.5) r = ['Rasche Besserung, noch windig', 'cloud-sun', 'Der Luftdruck steigt sehr schnell. Ein Hoch drängt heran – meist klart es auf, oft aber zunächst mit kräftigem Wind, weil der Druckunterschied groß ist.'];
    else if (c > 1.5) r = ['Wetterbesserung', 'sun', 'Der Luftdruck steigt deutlich. Ein Hochdruckgebiet baut sich auf: Die Luft sinkt ab, erwärmt sich dabei und Wolken lösen sich auf.'];
    else if (c > 0.5) r = ['Langsame Besserung', 'cloud-sun', 'Der Luftdruck steigt leicht. Die Wetterlage stabilisiert sich, Regen wird unwahrscheinlicher.'];
    else if (c >= -0.5) r = ['Beständig', 'equal', 'Der Luftdruck bleibt fast gleich. Das aktuelle Wetter – ob gut oder schlecht – ändert sich in den nächsten Stunden wahrscheinlich kaum.'];
    else if (c >= -1.5) r = ['Langsame Verschlechterung', 'cloud', 'Der Luftdruck fällt leicht. Ein Tiefausläufer kann sich nähern: mehr Wolken, später vielleicht Regen.'];
    else if (c >= -3.5) r = ['Verschlechterung, Regen wahrscheinlich', 'cloud-rain', 'Der Luftdruck fällt deutlich. Ein Tief zieht heran – Bewölkung verdichtet sich und Regen wird wahrscheinlich.'];
    else r = ['Sturm- und Unwettergefahr', 'cloud-lightning', 'Der Luftdruck fällt sehr schnell! Das ist ein typisches Vorzeichen für ein kräftiges Tief oder eine Kaltfront mit Sturmböen und Starkregen.'];

    var level = qnh >= 1025 ? 'hoch (Hochdruck)' : qnh <= 1000 ? 'tief (Tiefdruck)' : 'normal';
    var why = r[2] + ' Faustregel: Steigt der Druck, wird das Wetter meist besser – fällt er, meist schlechter. Je schneller die Änderung, desto heftiger.';
    if (!opts.altitude && A.current < 990) {
      why += ' Hinweis: Tragt die Höhe eurer Station ein – dann wird der Druck auf Meereshöhe umgerechnet und vergleichbar mit dem Wetterbericht.';
    }
    return {
      ok: true, title: r[0], icon: r[1],
      details: [
        ['Änderung', signed(c, 1) + ' hPa in 3 h'],
        ['Luftdruck', de(A.current, 1) + ' hPa' + (opts.altitude ? ' (Meereshöhe: ' + de(qnh, 0) + ' hPa)' : '')],
        ['Druckniveau', level],
        altitudeWarning(A.current, opts.altitude)
      ].filter(Boolean),
      why: why,
      basis: basisText(A.w.span, A.fit.n),
      quality: quality(A.w.span, 3, A.fit.n),
      explain: {
        intro: MODELS.pressure.intro,
        data: [['Messwert', 'Luftdruck („' + (opts.sensor || 'press') + '“)'], ['Zeitraum', 'die letzten ' + de(A.w.span, 1) + ' Stunden'], ['Messpunkte', String(A.fit.n)], ['Aktueller Druck', de(A.current, 1) + ' hPa']],
        steps: [
          regressionStep(A.fit, 'hPa', 'p'),
          { title: 'Tendenz auf 3 Stunden hochrechnen', text: 'Wetterdienste geben die Drucktendenz immer als Änderung in 3 Stunden an. Deshalb wird die Steigung mit 3 multipliziert.', formula: 'Δp₃ₕ = 3 h · b', calc: 'Δp₃ₕ = 3 h · ' + par(A.fit.b, 3) + ' hPa/h = ' + signed(c, 2) + ' hPa' },
          { title: 'Einordnung', text: 'Die Tendenz wird mit festen Schwellenwerten verglichen. Die markierte Zeile trifft gerade zu:', table: { head: ['Änderung in 3 h', 'Prognose'], rows: PRESSURE_CLASSES.map(function (k) { return [k[0], k[1]]; }), hi: PRESSURE_CLASSES.findIndex(function (k) { return k[2](c); }) } },
          qnhStep(A.current, opts.altitude, opts.temp, qnh)
        ],
        limits: [
          'Die Regel sagt nur voraus, ob sich das Wetter ändert – nicht, wie es gerade ist. „Beständig“ kann auch „bleibt verregnet“ heißen.',
          'Örtliche Ereignisse wie Sommergewitter entstehen oft ohne große Druckänderung und werden nicht erkannt.',
          'Der Luftdruck schwankt auch im Tagesverlauf um ca. 1 hPa (atmosphärische Gezeiten) – kleine Änderungen sind deshalb kein sicheres Zeichen.'
        ]
      }
    };
  }

  // ---------------------------------------------------------------------------
  // Modell 2: Zambretti-Vorhersager (Negretti & Zambra, 1915) – Nordhalbkugel, ohne Windrichtung
  // ---------------------------------------------------------------------------
  var ZAMBRETTI = [
    'Beständig schön', 'Schönes Wetter', 'Wird schön', 'Schön, wird unbeständiger', 'Schön, vereinzelt Schauer möglich',
    'Ziemlich schön, Besserung', 'Ziemlich schön, anfangs Schauer möglich', 'Ziemlich schön, später Schauer',
    'Anfangs Schauer, dann Besserung', 'Wechselhaft, Besserung', 'Ziemlich schön, Schauer wahrscheinlich',
    'Eher unbeständig, später aufklarend', 'Unbeständig, wahrscheinlich Besserung', 'Schauer mit sonnigen Abschnitten',
    'Schauer, wird unbeständiger', 'Wechselhaft, etwas Regen', 'Unbeständig, kurze schöne Abschnitte',
    'Unbeständig, später Regen', 'Unbeständig, etwas Regen', 'Überwiegend sehr unbeständig',
    'Zeitweise Regen, Verschlechterung', 'Zeitweise Regen, sehr unbeständig', 'Häufig Regen',
    'Regen, sehr unbeständig', 'Stürmisch, eventuell Besserung', 'Stürmisch, viel Regen'
  ];
  var Z_RISE = [25, 25, 25, 24, 24, 19, 16, 12, 11, 9, 8, 6, 5, 2, 1, 1, 0, 0, 0, 0, 0, 0];
  var Z_STEADY = [25, 25, 25, 25, 25, 25, 23, 23, 22, 18, 15, 13, 10, 4, 1, 1, 0, 0, 0, 0, 0, 0];
  var Z_FALL = [25, 25, 25, 25, 25, 25, 25, 25, 23, 23, 21, 20, 17, 14, 7, 3, 1, 1, 1, 0, 0, 0];

  function zambrettiIcon(i) {
    if (i <= 2) return 'sun';
    if (i <= 8) return 'cloud-sun';
    if (i <= 15) return 'cloud-sun-rain';
    if (i <= 23) return 'cloud-rain';
    return 'cloud-lightning';
  }

  function zambrettiModel(series, opts) {
    var A = pressureAnalysis(series, opts.now);
    if (A.error) return A.error;
    var qnh = seaLevel(A.current, opts.altitude, opts.temp);
    var trend = A.change3h >= 1.6 ? 'rise' : A.change3h <= -1.6 ? 'fall' : 'steady';
    var month = new Date((opts.now || Date.now() / 1000) * 1000).getMonth() + 1;
    var summer = month >= 4 && month <= 9;
    var top = 1050, bottom = 950, range = top - bottom;
    var p = qnh;
    if (summer && trend === 'rise') p += 0.07 * range;
    if (summer && trend === 'fall') p -= 0.07 * range;
    var pSeason = p;
    if (p >= top) p = top - 1;
    var optRaw = Math.floor((p - bottom) / (range / 22));
    var opt = optRaw;
    var exceptional = opt < 0 || opt > 21;
    opt = Math.max(0, Math.min(21, opt));
    var idx = (trend === 'rise' ? Z_RISE : trend === 'fall' ? Z_FALL : Z_STEADY)[opt];
    var trendText = { rise: 'steigend', fall: 'fallend', steady: 'gleichbleibend' }[trend];
    var why = 'Das Zambretti-Verfahren wurde 1915 für Barometer entwickelt. Es kombiniert drei Dinge: den Luftdruck auf Meereshöhe (' + de(qnh, 0) +
      ' hPa), seine Tendenz (' + trendText + ') und die Jahreszeit (' + (summer ? 'Sommerhalbjahr' : 'Winterhalbjahr') +
      '). Daraus wird einer von 26 Wettertexten ausgewählt (hier Nr. ' + String.fromCharCode(65 + idx) + '). Trefferquote laut Erfinder: rund 90 % für die nächsten 12 Stunden – in der Praxis eher weniger, besonders im Gebirge.';
    if (!opts.altitude) {
      why += ' Wichtig: Tragt die Höhe eurer Station über dem Meer ein, sonst rechnet Zambretti mit einem zu niedrigen Druck und sagt zu schlechtes Wetter voraus.';
    }
    return {
      ok: true, title: (exceptional ? 'Außergewöhnlich: ' : '') + ZAMBRETTI[idx], icon: zambrettiIcon(idx),
      details: [
        ['Druck auf Meereshöhe', de(qnh, 1) + ' hPa'],
        ['Tendenz', trendText + ' (' + signed(A.change3h, 1) + ' hPa/3 h)'],
        ['Gilt für', 'die nächsten ca. 12 Stunden'],
        altitudeWarning(A.current, opts.altitude)
      ].filter(Boolean),
      why: why,
      basis: basisText(A.w.span, A.fit.n),
      quality: quality(A.w.span, 3, A.fit.n),
      explain: {
        intro: MODELS.zambretti.intro,
        data: [['Messwert', 'Luftdruck („' + (opts.sensor || 'press') + '“)'], ['Zeitraum', 'die letzten ' + de(A.w.span, 1) + ' Stunden'], ['Messpunkte', String(A.fit.n)], ['Höhe der Station', opts.altitude ? de(opts.altitude, 0) + ' m' : 'nicht eingetragen'], ['Monat', String(month)]],
        steps: [
          qnhStep(A.current, opts.altitude, opts.temp, qnh),
          regressionStep(A.fit, 'hPa', 'p'),
          { title: 'Tendenz bestimmen', text: 'Aus der Steigung wird die Änderung in 3 Stunden berechnet. Ab ±1,6 hPa gilt der Druck als steigend bzw. fallend, sonst als gleichbleibend.', formula: 'Δp₃ₕ = 3 h · b', calc: 'Δp₃ₕ = 3 h · ' + par(A.fit.b, 3) + ' hPa/h = ' + signed(A.change3h, 2) + ' hPa  →  ' + trendText },
          { title: 'Jahreszeit berücksichtigen', text: 'Im Sommerhalbjahr (April–September) wirken Druckänderungen stärker. Bei steigendem Druck werden deshalb 7 hPa addiert, bei fallendem 7 hPa abgezogen. Im Winterhalbjahr oder bei gleichbleibendem Druck bleibt der Wert unverändert.', formula: 'p_korr = p₀ ± 7 hPa', calc: 'p_korr = ' + de(qnh, 1) + ' hPa' + (pSeason !== qnh ? (pSeason > qnh ? ' + 7 hPa' : ' − 7 hPa') : ' (keine Korrektur: ' + (summer ? 'Druck gleichbleibend' : 'Winterhalbjahr') + ')') + ' = ' + de(pSeason, 1) + ' hPa' },
          { title: 'Position auf der Zambretti-Skala', text: 'Der Bereich von 950 bis 1050 hPa wird in 22 gleich große Abschnitte geteilt (je 100/22 ≈ 4,545 hPa). Z gibt an, in welchem Abschnitt der korrigierte Druck liegt (⌊ ⌋ bedeutet: abrunden).', formula: 'Z = ⌊ (p_korr − 950 hPa) / 4,545 hPa ⌋', calc: 'Z = ⌊ (' + de(pSeason, 1) + ' − 950) / 4,545 ⌋ = ' + optRaw + (exceptional ? '  → außerhalb der Skala, begrenzt auf ' + opt : '') },
          { title: 'Wettertext nachschlagen', text: 'Für jede Tendenz gibt es eine eigene Zuordnungstabelle. In der Tabelle „' + trendText + '“ steht an Position Z = ' + opt + ' der Text ' + String.fromCharCode(65 + idx) + '. Alle 26 Texte (markiert: der gewählte):', table: { head: ['', 'Wettertext'], rows: ZAMBRETTI.map(function (t, i) { return [String.fromCharCode(65 + i), t]; }), hi: idx } }
        ],
        limits: [
          'Das Verfahren wurde für die britischen Inseln entwickelt. In Süddeutschland und im Gebirge trifft es seltener zu.',
          'Das Original berücksichtigt zusätzlich die Windrichtung – die messen die meisten Schulstationen nicht, deshalb fehlt sie hier.',
          'Ohne korrekt eingetragene Stationshöhe ist der Druck auf Meereshöhe falsch und damit auch die Vorhersage.'
        ]
      }
    };
  }

  // ---------------------------------------------------------------------------
  // Modell 3: Trend-Hochrechnung (lineare Regression, beliebiger Messwert)
  // ---------------------------------------------------------------------------
  function trendModel(series, opts) {
    var horizon = opts.horizon || 1;
    var basisH = Math.max(1, Math.min(6, horizon * 2));
    var w = window(series, basisH);
    if (!w || w.pts.length < 5 || w.span < 0.5) {
      return notEnough('Für eine Hochrechnung braucht es Messwerte aus mindestens 30 Minuten.');
    }
    if (stale(w.tEnd, opts.now)) {
      return { ok: false, title: 'Daten veraltet', icon: 'clock', details: [], why: 'Die letzte Messung ist älter als 2 Stunden.', basis: '', quality: 'gering' };
    }
    var fit = linreg(w.pts);
    var lastAge = Math.max(0, (opts.now - w.tEnd) / 3600);
    var predicted = fit.a + fit.b * (horizon + lastAge);
    var unit = opts.unit ? ' ' + opts.unit : '';
    var d = opts.decimals === undefined ? 1 : opts.decimals;
    var dir = fit.r2 < 0.2 || Math.abs(fit.b) < 1e-9 ? 'kein klarer Trend' : fit.b > 0 ? 'steigend' : 'fallend';
    var icon = dir === 'steigend' ? 'trending-up' : dir === 'fallend' ? 'trending-down' : 'move-right';
    var fitText = fit.r2 >= 0.8 ? 'sehr gut' : fit.r2 >= 0.5 ? 'ordentlich' : fit.r2 >= 0.2 ? 'mäßig' : 'schlecht';
    var why = 'Durch die Messpunkte der letzten ' + de(w.span, 1) + ' Stunden wird eine Ausgleichsgerade gelegt (lineare Regression) und einfach in die Zukunft verlängert. ' +
      'Das Bestimmtheitsmaß R² = ' + de(fit.r2, 2) + ' zeigt, wie gut die Gerade zu den Messwerten passt (1 = perfekt, 0 = gar nicht) – hier: ' + fitText + '. ' +
      'Achtung: Das Wetter verläuft nicht geradlinig (z. B. Tag/Nacht). Je weiter man hochrechnet, desto unsicherer wird es.';
    var q = quality(w.span, basisH, fit.n);
    if (fit.r2 < 0.2 && q === 'hoch') q = 'mittel';
    return {
      ok: true, title: (opts.label || 'Wert') + ' in ' + horizon + ' h: ' + de(predicted, d) + unit + (fit.r2 < 0.2 ? ' (unsicher)' : ''), icon: icon,
      value: predicted,
      details: [
        ['Jetzt', de(w.pts[w.pts.length - 1][1], d) + unit],
        ['Trend', dir + ' (' + signed(fit.b, d + 1) + unit + ' pro Stunde)'],
        ['Güte der Geraden', 'R² = ' + de(fit.r2, 2) + ' (' + fitText + ')']
      ],
      why: why,
      basis: basisText(w.span, fit.n),
      quality: q,
      explain: {
        intro: MODELS.trend.intro,
        data: [['Messwert', (opts.label || 'Wert') + ' („' + (opts.sensor || '') + '“)'], ['Zeitraum', 'die letzten ' + de(w.span, 1) + ' Stunden'], ['Messpunkte', String(fit.n)], ['Hochrechnen um', horizon + ' h']],
        steps: [
          regressionStep(fit, unit.trim() || 'Einheiten', 'y'),
          { title: 'Achsenabschnitt a berechnen', text: 'a ist der Wert der Geraden zum Zeitpunkt t = 0 (jetzt) – also der „geglättete“ aktuelle Wert.', formula: 'a = ( Σy − b · Σt ) / n', calc: 'a = ( ' + de(fit.sy, 1) + ' − ' + par(fit.b, 3) + ' · ' + par(fit.sx, 2) + ' ) / ' + fit.n + ' = ' + de(fit.a, d + 1) + unit },
          { title: 'Gerade in die Zukunft verlängern', text: 'Für den Zeitpunkt in ' + horizon + ' h wird t = ' + de(horizon + lastAge, 2) + ' eingesetzt' + (lastAge > 0.02 ? ' (die letzte Messung ist ' + de(lastAge * 60, 0) + ' min alt, das wird mitgerechnet)' : '') + '.', formula: 'y(t) = a + b · t', calc: 'y = ' + de(fit.a, d + 1) + ' + ' + par(fit.b, 3) + ' · ' + de(horizon + lastAge, 2) + ' = ' + de(predicted, d) + unit },
          { title: 'Wie gut passt die Gerade? (Bestimmtheitsmaß R²)', text: 'SQ_Res ist die Summe der quadrierten Abstände der Messpunkte von der Geraden, SQ_Tot die Summe der quadrierten Abstände vom Mittelwert. R² = 1 heißt: alle Punkte liegen genau auf der Geraden. R² = 0 heißt: die Gerade erklärt die Schwankungen überhaupt nicht.', formula: 'R² = 1 − SQ_Res / SQ_Tot', calc: 'R² = 1 − ' + de(fit.ssRes, 3) + ' / ' + de(fit.ssTot, 3) + ' = ' + de(fit.r2, 2) + '  →  ' + fitText,
            table: { head: ['R²', 'Bewertung'], rows: [['ab 0,8', 'sehr gut'], ['0,5 bis 0,8', 'ordentlich'], ['0,2 bis 0,5', 'mäßig'], ['unter 0,2', 'schlecht – kein klarer Trend']], hi: fit.r2 >= 0.8 ? 0 : fit.r2 >= 0.5 ? 1 : fit.r2 >= 0.2 ? 2 : 3 } }
        ],
        limits: [
          'Viele Messgrößen verlaufen nicht geradlinig: Die Temperatur steigt morgens und fällt abends. Eine Gerade kann solche Kurven nicht abbilden.',
          'Je weiter man hochrechnet, desto größer wird der Fehler. Deshalb wird höchstens 3 Stunden voraus gerechnet.',
          'Einzelne Ausreißer (z. B. ein Messfehler) können die Gerade stark verbiegen – ein kleines R² ist dann ein Warnsignal.'
        ]
      }
    };
  }

  // ---------------------------------------------------------------------------
  // Modell 4: Nebel & Frost über den Taupunkt (Magnus-Formel)
  // ---------------------------------------------------------------------------
  function dewPoint(t, rh) {
    var a = 17.62, b = 243.12;
    var g = Math.log(Math.max(1, Math.min(100, rh)) / 100) + (a * t) / (b + t);
    return (b * g) / (a - g);
  }

  function dewpointModel(latestT, latestH, opts) {
    if (!latestT || !latestH) {
      return notEnough('Für dieses Modell braucht eure Station Temperatur und Luftfeuchte. Prüft im Designer, welche Messwert-Namen eingestellt sind.');
    }
    var now = opts.now || Date.now() / 1000;
    if (now - Math.min(latestT.t, latestH.t) > 2 * 3600) {
      return { ok: false, title: 'Daten veraltet', icon: 'clock', details: [], why: 'Die letzten Messungen sind älter als 2 Stunden.', basis: '', quality: 'gering' };
    }
    var t = latestT.v, rh = latestH.v;
    var td = dewPoint(t, rh);
    var gamma = Math.log(Math.max(1, Math.min(100, rh)) / 100) + (17.62 * t) / (243.12 + t);
    var spread = t - td;
    var r;
    if (t <= 3 && td <= 0 && spread <= 3) r = ['Reif- und Glättegefahr', 'snowflake', 'Die Temperatur ist nahe am Gefrierpunkt und die Luft fast gesättigt. Kühlt es weiter ab, setzt sich Feuchtigkeit als Reif ab – auf Wegen und Autoscheiben kann es glatt werden.'];
    else if (spread <= 1) r = ['Nebel oder Tau sehr wahrscheinlich', 'cloud-fog', 'Temperatur und Taupunkt liegen fast gleich auf – die Luft ist mit Wasserdampf gesättigt. Der Wasserdampf kondensiert zu feinen Tröpfchen: Nebel, Tau oder beschlagene Scheiben.'];
    else if (spread <= 2.5) r = ['Nebel oder Tau möglich', 'cloud-fog', 'Die Luft ist schon sehr feucht. Kühlt sie (z. B. nachts) nur noch um ' + de(spread, 1) + ' °C ab, bildet sich Nebel oder Tau.'];
    else if (spread <= 5) r = ['Feucht, aber kein Nebel', 'droplets', 'Bis zur Sättigung fehlen noch ' + de(spread, 1) + ' °C Abkühlung. In einer klaren Nacht kann sich bis morgens trotzdem Tau bilden.'];
    else r = ['Trockene Luft – kein Nebel', 'sun', 'Die Luft ist weit von der Sättigung entfernt (' + de(spread, 1) + ' °C Abstand zum Taupunkt). Nebel oder Tau sind nicht zu erwarten.'];
    var cloudBase = Math.max(0, spread * 125);
    var why = r[2] + ' Der Taupunkt ist die Temperatur, auf die man die Luft abkühlen müsste, damit der Wasserdampf kondensiert. Er wird mit der Magnus-Formel aus Temperatur und relativer Luftfeuchte berechnet.';
    return {
      ok: true, title: r[0], icon: r[1],
      details: [
        ['Taupunkt', de(td, 1) + ' °C'],
        ['Abstand zum Taupunkt', de(spread, 1) + ' °C'],
        ['Geschätzte Wolkenuntergrenze', spread > 0.5 ? 'ca. ' + de(Math.round(cloudBase / 50) * 50, 0) + ' m' : 'am Boden (Nebel)']
      ],
      why: why + (opts.outdoor === false ? '' : ' Die Wolkenuntergrenze (Abstand × 125 m) gilt nur für Messungen im Freien.'),
      basis: 'Aktuelle Werte: ' + de(t, 1) + ' °C, ' + de(rh, 0) + ' % Luftfeuchte',
      quality: 'hoch',
      explain: {
        intro: MODELS.dewpoint.intro,
        data: [['Temperatur T', de(t, 1) + ' °C („' + (opts.sensor || 'temp') + '“)'], ['Relative Luftfeuchte rF', de(rh, 1) + ' % („' + (opts.sensor2 || 'hum') + '“)']],
        steps: [
          { title: 'Hilfsgröße γ (Gamma) berechnen', text: 'Die Magnus-Formel beschreibt näherungsweise, wie viel Wasserdampf Luft bei einer Temperatur höchstens aufnehmen kann. Die Konstanten a = 17,62 und b = 243,12 °C gelten für Temperaturen über Wasser von −45 °C bis +60 °C. ln ist der natürliche Logarithmus.', formula: 'γ = ln( rF / 100 % ) + a · T / ( b + T )', calc: 'γ = ln( ' + de(rh, 1) + ' / 100 ) + 17,62 · ' + par(t, 1) + ' / ( 243,12 + ' + par(t, 1) + ' ) = ' + de(gamma, 4) },
          { title: 'Taupunkt Td berechnen', formula: 'Td = b · γ / ( a − γ )', calc: 'Td = 243,12 · ' + par(gamma, 4) + ' / ( 17,62 − ' + par(gamma, 4) + ' ) = ' + de(td, 1) + ' °C' },
          { title: 'Abstand zum Taupunkt (Spread)', text: 'Der Spread sagt, um wie viel Grad sich die Luft noch abkühlen müsste, bis sie gesättigt ist.', formula: 'Spread = T − Td', calc: 'Spread = ' + de(t, 1) + ' °C − ' + par(td, 1) + ' °C = ' + de(spread, 1) + ' °C' },
          { title: 'Einordnung', text: 'Die markierte Zeile trifft gerade zu (die Reif-Regel wird zuerst geprüft):',
            table: { head: ['Bedingung', 'Prognose'], rows: [['T ≤ 3 °C, Td ≤ 0 °C und Spread ≤ 3 °C', 'Reif- und Glättegefahr'], ['Spread ≤ 1 °C', 'Nebel oder Tau sehr wahrscheinlich'], ['Spread ≤ 2,5 °C', 'Nebel oder Tau möglich'], ['Spread ≤ 5 °C', 'Feucht, aber kein Nebel'], ['Spread > 5 °C', 'Trockene Luft – kein Nebel']],
              hi: (t <= 3 && td <= 0 && spread <= 3) ? 0 : spread <= 1 ? 1 : spread <= 2.5 ? 2 : spread <= 5 ? 3 : 4 } },
          { title: 'Wolkenuntergrenze schätzen', text: 'Steigt Luft auf, kühlt sie sich um ca. 1 °C pro 100 m ab, der Taupunkt aber nur um ca. 0,2 °C pro 100 m. Der Abstand schrumpft also um ca. 0,8 °C pro 100 m – das ergibt rund 125 m pro Grad Spread (Faustformel nach Espy).', formula: 'h ≈ 125 m/°C · ( T − Td )', calc: 'h ≈ 125 · ' + de(spread, 1) + ' = ' + de(cloudBase, 0) + ' m' }
        ],
        limits: [
          'Die Werte gelten für den Ort des Sensors. Steht er im Klassenzimmer, sagt das Modell etwas über Beschlagen von Fenstern, nicht über Nebel draußen.',
          'Ob sich nachts wirklich Nebel bildet, hängt auch von Wind und Bewölkung ab (klare, windstille Nächte kühlen am stärksten ab).',
          'Billige Feuchtesensoren (z. B. DHT11) messen oft mehrere Prozent daneben – bei hoher Luftfeuchte wirkt sich das stark auf den Taupunkt aus.'
        ]
      }
    };
  }

  /** Einstiegspunkt für das Dashboard. */
  function run(model, ctx) {
    var r;
    switch (model) {
      case 'zambretti': r = zambrettiModel(ctx.series, ctx); break;
      case 'trend': r = trendModel(ctx.series, ctx); break;
      case 'dewpoint': r = dewpointModel(ctx.latestT, ctx.latestH, ctx); break;
      default: model = 'pressure'; r = pressureModel(ctx.series, ctx);
    }
    if (!r.explain) {
      r.explain = { intro: MODELS[model].intro, data: [['Benötigt', MODELS[model].needs]], steps: [{ title: 'Noch keine Berechnung möglich', text: r.why + ' Sobald genug Messwerte da sind, steht hier der komplette Rechenweg mit euren Zahlen.' }], limits: [] };
    }
    return r;
  }

  var api = {
    MODELS: MODELS, run: run, linreg: linreg, seaLevel: seaLevel, dewPoint: dewPoint,
    pressureModel: pressureModel, zambrettiModel: zambrettiModel, trendModel: trendModel, dewpointModel: dewpointModel
  };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.EWForecast = api;
})(this);

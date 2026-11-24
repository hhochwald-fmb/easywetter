<?php
// Diese Datei wird nur aufgerufen, wenn die Weiterleitung nach public/ NICHT funktioniert
// (z. B. weil die .htaccess beim Hochladen gefehlt hat oder mod_rewrite aus ist).
// Normalerweise leitet die .htaccess alle Anfragen nach public/index.php weiter.
http_response_code(500);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>EasyWetter – Einrichtung prüfen</title>
<style>body{font-family:system-ui,sans-serif;max-width:42rem;margin:3rem auto;padding:0 1rem;line-height:1.6;color:#0f172a}code{background:#f1f5f9;padding:.1em .35em;border-radius:4px}h1{font-size:1.5rem}</style></head>
<body>
<h1>Fast geschafft – die Weiterleitung fehlt noch</h1>
<p>EasyWetter ist hochgeladen, aber der Webserver leitet die Aufrufe noch nicht in den Ordner <code>public/</code> weiter. Eine der beiden Lösungen:</p>
<ol>
  <li><strong>Empfohlen:</strong> Im Kundenbereich deines Hosters das Verzeichnis (Document Root) der Domain auf den Unterordner <code>public</code> setzen.</li>
  <li>Oder prüfen, ob die unsichtbare Datei <code>.htaccess</code> im Hauptordner mit hochgeladen wurde (im FTP-Programm „versteckte Dateien anzeigen“ einschalten). Sie muss auch in <code>public/</code> und den anderen Unterordnern vorhanden sein.</li>
</ol>
<p>Danach diese Seite neu laden – dann startet der Einrichtungsassistent.</p>
</body></html>

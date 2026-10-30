# Local Link Preview für HumHub

**Dieses Modul wird in HumHub installiert.** Es erzeugt Linkvorschauen beim Schreiben und unter veröffentlichten Beiträgen. Metadaten und Bilder ruft der HumHub-Server ab; Vorschaubilder werden lokal zwischengespeichert. PeerTube und die beiden PeerTube-Projekte werden dafür nicht benötigt.

## Referenzumgebung und Versionen

Stand: 7. September 2026. HumHub **Community Edition 1.18.5** ist in der Betriebsdokumentation und im lokalen Core bestätigt. PeerTube **8.2.4** wurde am selben Tag über die öffentliche Server-API `/api/v1/config` der bestehenden Installation geprüft. Diese Angaben beschreiben die Referenzumgebung, keine vollständige Kompatibilitätsprüfung sämtlicher Funktionen.

Modulversion: **0.0.6** (Funktionsupdate: 30. Oktober 2026). Laut `module.json`: HumHub mindestens **1.18.0**, höchstens **1.18.\***. Die PeerTube-Version ist für dieses Modul keine Voraussetzung.

## Nutzung durch andere Communities

Im geprüften Quellcode wurden keine festen Bindungen an Selbstsein-Domains, bestimmte Konten oder Serverpfade gefunden. Cache, Bildgröße, Kartenanzahl und gesperrte Domains lassen sich administrativ konfigurieren. Damit ist das Modul technisch auch für andere HumHub-1.18-Installationen geeignet; die Oberfläche ist derzeit deutsch. Die Lizenz ist [MIT](LICENSE). Vor produktiver Übernahme sollten Vorschauerstellung, Berechtigungen und Bildauslieferung auf der eigenen Installation geprüft werden.

## Verhältnis zum Marketplace-Modul LinkPreview

Die Prüfung am 7. September 2026 fand keine Hinweise auf eine Fork-Abstammung im lokalen Code oder in den Metadaten. Dieses Projekt verwendet die Modul-ID `local-link-preview`, den Namespace `humhub\modules\localLinkPreview` und eine eigene MIT-Lizenzdatei. Das neu angelegte Git-Repository enthält allerdings nur den Import des vorhandenen Ordners und keine frühere Entwicklungsgeschichte; daraus lässt sich die ursprüngliche Codeherkunft nicht beweisen.

Das [Marketplace-Angebot LinkPreview](https://marketplace.humhub.com/checkout/new-customer?id=linkpreview) stammt von HumHub GmbH & Co. KG. Der dort verlinkte [GitHub-Auftritt](https://github.com/humhub/linkpreview-issues) ist lediglich ein Issue-Tracker und enthält keinen Modulquellcode für einen Vergleich. Ergebnis: **keine belegte Fork-Beziehung; eine unabhängige Neuentwicklung ist damit ebenfalls nicht abschließend nachgewiesen**. Ähnliche Funktionen allein belegen keine gemeinsame Codeherkunft. Austauschbarkeit, Datenmigration und paralleler Betrieb mit dem Marketplace-Modul wurden nicht geprüft.

## Datenschutz und Sicherheit

- Externe Seiten und Bilder werden ausschließlich vom HumHub-Server abgerufen.
- Vorschaubilder liegen in `protected/runtime/local-link-preview/images` und werden über HumHub ausgeliefert.
- Browser der Nutzer laden keine externen Vorschaubilder; deren IP-Adresse wird dem Zielserver nicht offengelegt.
- Private, lokale und reservierte IP-Bereiche werden blockiert (SSRF-Schutz).
- Weiterleitungen werden einzeln geprüft; Downloads haben Zeit-, Größen- und Typgrenzen.
- Metadaten werden sieben Tage zwischengespeichert.

Wichtig: Der Zielserver sieht beim ersten Abruf die IP-Adresse des HumHub-Servers. Vollständig ohne ausgehende Serververbindung lassen sich Metadaten fremder Webseiten nicht erzeugen.

## Installation

1. Den Ordner `local-link-preview` nach `protected/modules/` der HumHub-Installation kopieren.
2. In HumHub als Administrator zu **Administration → Module → Installiert** wechseln.
3. **Local Link Preview** aktivieren. Dabei wird die Cache-Tabelle automatisch angelegt.
4. Falls Assets nach einem Update alt wirken: HumHub-Cache leeren.

Voraussetzungen: HumHub 1.18.x, PHP 8.2+, PHP-Erweiterungen cURL, DOM, mbstring und fileinfo.

## Verhalten

Das Modul erkennt HTTP(S)-Links während des Schreibens. Interne Profil-Links und Erwähnungen (`/u/…` und `user/profile`, auch als Query-Route) erzeugen keine Vorschau und werden nicht abgerufen. Bei mehreren geeigneten Links lässt sich der Vorschau-Link auswählen.

Externe Vorschauen verwenden verfügbare Open-Graph-, Twitter- oder image_src-Bilder. Alternative Bildadressen werden bei Fehlern ebenfalls geprüft und Bilder lokal gespeichert. Ohne Bild bleibt eine Textkarte; bei fehlgeschlagenem Seitenabruf bleibt der ursprüngliche Link. Original-Links werden nicht ausgeblendet.

**Ausschalten:**

- „Linkvorschauen anzeigen“ schaltet Vorschauen persönlich aus bzw. ein. Die Einstellung wird serverseitig gespeichert und verhindert bei ausgeschalteter Anzeige weitere Vorschauabrufe.
- „Vorschau entfernen“ beim Schreiben gilt für den einzelnen Beitrag, bereits während des Ladens. Das bleibt auch beim Hinzufügen weiterer Links erhalten, bis die Vorschau ausdrücklich aktiviert wird.
- Veröffentlichte Vorschauen können Leser persönlich ausblenden und wieder anzeigen; diese einzelne Auswahl wird pro Nutzer im aktuellen Browser gespeichert.
- Personen mit Bearbeitungsrecht können die Vorschau für alle entfernen oder wieder aktivieren. Textänderungen ohne Vorschaufeld heben diese Entscheidung nicht auf.
- Administratoren können das Modul weiterhin insgesamt ausschalten.

Bildvorschauen lassen sich beim Schreiben separat ausblenden. Abruf gespeicherter Beitragseinstellungen erfordert Leserecht; Änderungen erfordern Bearbeitungsrecht, POST und CSRF. Cacheleeren ist auf POST beschränkt.

## Prüfungen

- `php tests/policy.php`: Profilfilter, Metadaten, alternative Bilder und URL-Schutz (PHP DOM/mbstring).
- `node tests/policy.test.cjs`: clientseitige Profilausschlüsse und URL-Erkennung.
- `node tests/browser.cjs`: Bedienprüfung in separat gestarteter Chromium-Testinstanz auf Debug-Port 9241; lokale Testdaten und simulierte Vorschauantworten.
- `php tests/runtime.php /pfad/zur/testinstallation`: echte HumHub-Rechte, Abschaltung und Bearbeiten mit vorhandenen Testkonten. Auswahländerungen werden zurückgerollt, persönliche Einstellungen wiederhergestellt. Optional prüft `PREVIEW_TEST_URL` den echten Abruf einer öffentlichen Webseite samt lokalem Bild. Nur auf der Testinstallation ausführen.

Unter **Administration → Module → Local Link Preview → Konfigurieren** lassen sich Cache-Dauer, maximale Bildgröße, Anzahl der Karten und gesperrte Domains verwalten.

## Grenzen

- Seiten, die Metadaten nur per JavaScript erzeugen oder Bots blockieren, liefern eventuell keine vollständige Vorschau.
- Vorschauen werden derzeit nur angemeldeten Nutzern angezeigt und abgerufen.
- Animierte GIFs werden unverändert lokal ausgeliefert.

## Deinstallation

Das Modul zuerst in HumHub deaktivieren und anschließend den Modulordner entfernen. Die Datenbankmigration wird beim Deaktivieren zurückgenommen; lokale Bilddateien können danach aus `protected/runtime/local-link-preview` entfernt werden.

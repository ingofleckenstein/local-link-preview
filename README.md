# Local Link Preview für HumHub

Datenschutzfreundliche Linkvorschauen für HumHub 1.18.x. Das Modul erkennt Links bereits beim Schreiben und zeigt die Vorschau auch unter veröffentlichten Beiträgen an.

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

Das Modul erkennt HTTP(S)-Links während des Schreibens. Bei mehreren Links kann der gewünschte Vorschau-Link ausgewählt werden. Eine Vorschau kann entfernt und ihr Bild ausgeblendet werden. Alleinstehende URLs werden nach erfolgreicher Kartendarstellung ausgeblendet. Ohne geeignete Metadaten erscheint eine kompakte Textkarte.

Unter **Administration → Module → Local Link Preview → Konfigurieren** lassen sich Cache-Dauer, maximale Bildgröße, Anzahl der Karten und gesperrte Domains verwalten.

## Grenzen

- Seiten, die Metadaten nur per JavaScript erzeugen oder Bots blockieren, liefern eventuell keine vollständige Vorschau.
- Vorschauen werden derzeit nur angemeldeten Nutzern angezeigt und abgerufen.
- Animierte GIFs werden unverändert lokal ausgeliefert.

## Deinstallation

Das Modul zuerst in HumHub deaktivieren und anschließend den Modulordner entfernen. Die Datenbankmigration wird beim Deaktivieren zurückgenommen; lokale Bilddateien können danach aus `protected/runtime/local-link-preview` entfernt werden.

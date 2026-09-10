# Changelog

## 0.0.5 – 2026-09-10

- Linkvorschauen werden nur noch im Eingabefeld für neue Beiträge sowie unter veröffentlichten HumHub-Posts im Stream erzeugt. Rich-Text-Felder und Stream-Inhalte anderer Apps bleiben unberührt.

## 0.0.4 – 2026-09-05

- Leere Editoren erzeugen beim Laden keine versteckten Linkvorschau-Felder mehr. Falls nach einer echten URL-Eingabe interne Felder benötigt werden, sind sie zusätzlich von HumHubs Formularzustandsprüfung ausgeschlossen.

## 0.0.3 – 2026-09-04

- Alleinstehende Quell-URLs werden bei erfolgreicher Karte ausgeblendet.
- Breitere Karten, größere 16:9-Bilder, klarere Hierarchie und verbesserter Hover-/Fokuszustand.
- Auswahl des Vorschau-Links bei Beiträgen mit mehreren Links.
- Bedienelemente zum Entfernen der Vorschau und Ausblenden des Bildes.
- Robuste Textkarte als Rückfalllösung bei fehlenden Metadaten.
- Administrationsseite für Aktivierung, Cache-Dauer, Bildgröße, Kartenanzahl, Domain-Sperrliste und Cache-Löschung.

## 0.0.2 – 2026-09-04

- Kritischen Startfehler behoben: Assets verwenden nun einen absoluten Modulpfad und hängen nicht mehr von einem erst später gesetzten Yii-Alias ab.

## 0.0.1 – 2026-09-04

- Live-Linkvorschau beim Schreiben
- Vorschaukarten unter veröffentlichten Beiträgen
- Lokaler Bildcache und serverseitiger Proxy
- Schutz vor SSRF, Redirect-Missbrauch und übergroßen Antworten

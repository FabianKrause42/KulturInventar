# KulturInventar

Interne Material- und Technikverwaltung als mobile-first WebApp für einen Kulturbetrieb.

---

## Projektbeschreibung

KulturInventar ist eine einfache, alltagstaugliche Inventarverwaltung für ein kleines Team, das technische Aufbauten und Ausstellungen in einem Kulturbetrieb betreut (kleines Theater, Kunstgalerie, Museum, Bibliothek).

Das Ziel ist **keine komplexe Warenwirtschaft**, sondern eine schnelle, übersichtliche Inventarliste, die sich mit möglichst wenig Klicks bedienen lässt. Die App soll sich wie eine gut benutzbare, moderne Inventarliste anfühlen – nicht wie ein großes Verwaltungssystem.

---

## Wichtige Nutzungsszenarien

- Artikel über Suchfeld schnell finden
- Artikel über Inventarnummer oder QR-Code direkt öffnen
- Neue Artikel schnell anlegen
- Standort eines Artikels mit einem Tipp direkt ändern
- Artikel anhand eines Fotos oder Kategoriebilds schnell wiedererkennen

Die App wird zu **ca. 90 % auf dem Smartphone** genutzt.

---

## Datenmodell – Artikel (Version 1)

| Feld           | Typ        | Pflicht | Hinweis                    |
|----------------|------------|---------|----------------------------|
| Inventarnummer | String     | Ja      | Eindeutig, manuell oder QR |
| Bezeichnung    | String     | Ja      |                            |
| Kategorie      | Enum       | Ja      | Siehe Liste unten          |
| Standort       | Enum       |         | Siehe Liste unten          |
| Menge          | Integer    |         | Standardwert: 1            |
| Maße           | String     |         | Freitext                   |
| Bemerkung      | Text       |         | Freitext                   |
| Foto           | Datei      |         | Max. 1 Bild pro Artikel    |

### Kategorien

- Audio
- Licht
- Video
- IT
- Bühne
- Möbel
- Kabel
- Werkzeug
- Sonstiges

### Standorte

- RON
- Schubertsaal
- Großes Magazin
- Theaterkeller
- Werkzeuglager
- In Gebrauch

---

## UI-Prinzipien

- **Mobile first** – große Touch-Flächen, große Buttons, große Eingabefelder
- Schlichtes, helles, ruhiges und aufgeräumtes Design
- Keine überladene Navigation
- **Suche als zentrale Funktion**
- Ergebnisliste mit Thumbnail und kurzer Beschreibung
- Detailseite kompakt, mit Fokus auf Standortwechsel
- Standortwechsel per Schnellbutton, sofort gespeichert
- Bild und Bezeichnung sind in Listen besonders wichtig
- QR-Code-Scan hilfreich, aber manuelle Eingabe der Inventarnummer genauso wichtig

### Bilder

- Pro Artikel nur ein Bild
- Fehlendes Bild → Platzhalterbild passend zur Kategorie
- Suchergebnisliste zeigt immer ein Thumbnail

---

## Leitprinzipien für alle Entscheidungen

> **Einfachheit** ist wichtiger als Vollständigkeit.  
> **Klare Bedienbarkeit** ist wichtiger als viele Funktionen.  
> **Mobile Nutzbarkeit** ist wichtiger als Desktop-Komfort.  
> **Version 1** soll bewusst klein und pragmatisch bleiben.

---

## Technologie

- PHP (kein Framework, kein Composer-Zwang)
- MySQL / MariaDB via PDO
- HTML, CSS, wenig JavaScript
- Kein Node.js, kein Build-Prozess
- Hosting: Strato Webhosting
- Repository: Public GitHub
- Deployment: später per GitHub Actions nach Strato

---

## Projektstruktur

```
public/
  index.php             ← Einstiegspunkt
  assets/
    css/
      styles.css        ← Mobiles Basislayout
src/
  config/
    config.example.php  ← Vorlage für lokale Konfiguration
    config.local.php    ← Lokale Zugangsdaten (nicht im Repo!)
    database.php        ← PDO-Verbindung
.gitignore
README.md
```

---

## Einrichtung

1. `src/config/config.example.php` kopieren und als `src/config/config.local.php` speichern
2. Zugangsdaten in `config.local.php` eintragen
3. `public/` als Document Root auf dem Server konfigurieren

## Sicherheitshinweise

- `config.local.php` und `db_login.txt` sind per `.gitignore` vom Repository ausgeschlossen
- Sensible Zugangsdaten dürfen **niemals** ins Repository gelangen
- Der Einstiegspunkt ist immer `public/index.php`

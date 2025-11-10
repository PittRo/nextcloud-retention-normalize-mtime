# Retention Normalize Mtime

Nextcloud-App, die bei jedem Datei-Upload automatisch den `mtime` (Änderungszeitpunkt) auf die aktuelle Upload-Zeit setzt.

## Warum ist diese App notwendig?

### Das Problem mit Retention und alten Zeitstempeln

Wenn Sie in Nextcloud einen **Retention-Flow** (automatische Löschung alter Dateien) verwenden, basiert die Löschentscheidung typischerweise auf dem `mtime` der Datei.

**Problematisches Szenario:**
1. Sie haben einen Flow: *"Lösche Dateien in /Retention, die älter als 30 Tage sind"*
2. Ein Nutzer lädt eine Datei hoch, die ursprünglich vom 15.09.2024 stammt
3. Nextcloud übernimmt standardmäßig den **Original-Zeitstempel** (15.09.2024)
4. Der Retention-Flow prüft den `mtime` und löscht die Datei **sofort**, weil sie scheinbar älter als 30 Tage ist
5. Die Datei wird gelöscht, obwohl sie gerade erst hochgeladen wurde! ❌

### Die Lösung

Diese App normalisiert den `mtime` auf die **tatsächliche Upload-Zeit**:
- Datei wird am 15.12.2024 hochgeladen → `mtime` = 15.12.2024 ✅
- Retention-Flow rechnet: 15.12.2024 + 30 Tage = 14.01.2025
- Datei wird korrekt erst am 14.01.2025 gelöscht ✅

**Wichtig:** Der Retention-Flow in Nextcloud hat **kein Upload-Datum-Feld**. Er kann nur den `mtime` prüfen. Ohne diese App würden alte Dateien sofort gelöscht werden.

## Features

- ✅ Setzt `mtime` automatisch auf aktuelle Upload-Zeit
- ✅ Funktioniert bei allen Upload-Methoden (Web-UI, WebDAV, Apps)
- ✅ Reagiert auf `NodeCreatedEvent` und `NodeWrittenEvent`
- ✅ Verwendet `Node->touch()` API mit DB-Fallback
- ✅ Optionale Filter für Gruppen und Ordner
- ✅ Fehler-Logging in `data/retention_normalize_mtime.log`
- ✅ Shutdown-Handler für zuverlässiges Touch

## Funktionsweise

### 1. Event-Listener
Die App registriert sich auf:
- `OCP\Files\Events\Node\NodeCreatedEvent` (neue Datei)
- `OCP\Files\Events\Node\NodeWrittenEvent` (überschriebene Datei)

### 2. Touch-Strategie
1. **Immediate Touch:** Sofortiger Versuch, `mtime` zu setzen via `Node->touch()`
2. **Shutdown Touch:** Falls immediate fehlschlägt, Retry am Ende der Anfrage
3. **DB-Fallback:** Falls auch Shutdown fehlschlägt, direktes UPDATE in `oc_filecache`

## Installation

### Via Git

```bash
cd /var/www/html/nextcloud/apps/
git clone https://github.com/PittRo/nextcloud-retention-normalize-mtime.git
cd nextcloud-retention-normalize-mtime
sudo -u www-data php ../../occ app:enable retention_normalize_mtime
```

### Via ZIP-Download

1. Download der neuesten Version: [Releases](https://github.com/PittRo/nextcloud-retention-normalize-mtime/releases)
2. Entpacken nach `apps/nextcloud-retention-normalize-mtime/`
3. App aktivieren: `occ app:enable retention_normalize_mtime`

## Konfiguration

### Alle Dateien aller Nutzer (Standard)

Die App funktioniert ohne weitere Konfiguration für **alle Uploads**.

### Nur bestimmte Gruppe

Bearbeiten Sie `lib/Listener/NormalizeMtimeListener.php`:

```php
private ?string $limitToGroup  = 'hundh';  // nur User in Gruppe 'hundh'
```

### Nur bestimmter Ordner

```php
private ?string $limitToPrefix = '/Retention';  // nur Dateien in /Retention/
```

### Kombination

```php
private ?string $limitToGroup  = 'hundh';
private ?string $limitToPrefix = '/Retention';
// Nur User in 'hundh' UND nur in /Retention/
```

## Verwendung mit Retention-Flow

### Beispiel-Flow in Nextcloud

**Ziel:** Lösche Dateien in `/Retention`, die älter als 30 Tage sind.

1. **Workflows** → **Neuer Flow**
2. **Trigger:** "Datei erstellt oder aktualisiert"
3. **Bedingungen:**
   - Dateipfad beginnt mit `/Retention`
   - Änderungsdatum ist älter als 30 Tage
4. **Aktion:** Datei löschen

**Ohne diese App:** Datei mit altem Original-Zeitstempel wird sofort gelöscht  
**Mit dieser App:** Datei erhält aktuellen Upload-Zeitstempel und wird erst nach 30 Tagen gelöscht ✅

## Logging

### Log-Dateien

Die App schreibt Logs nach:
- `data/nextcloud.log` (Standard Nextcloud-Log via `error_log()`)
- `data/retention_normalize_mtime.log` (persistentes App-Log)

### Nur Fehler werden geloggt

Erfolgreiche Operationen werden **nicht** geloggt. Die Log-Datei enthält nur:
- Fehler beim Pfad-Abruf
- Fehler beim Touch (immediate/shutdown)
- Fehler beim DB-Fallback

```bash
# Log-Datei ansehen
tail -f data/retention_normalize_mtime.log
```

Wenn die Datei nicht existiert oder leer ist, funktioniert alles! ✅

## Dateistruktur

```
nextcloud-retention-normalize-mtime/
├── README.md
├── appinfo/
│   ├── application.php          # App-Bootstrap
│   └── info.xml                 # App-Metadaten & Dependencies
└── lib/
    ├── AppInfo/
    │   └── Application.php      # Haupt-Application-Klasse
    └── Listener/
        └── NormalizeMtimeListener.php  # Event-Listener für Datei-Events
```

## Technische Details

### Kompatibilität

- Nextcloud 27+
- PHP 8.1+
- Keine zusätzlichen Dependencies

### Version

Aktuelle Version: **1.2.0**

## Lizenz

AGPL-3.0

## Support

Bei Problemen bitte ein [Issue](https://github.com/PittRo/nextcloud-retention-normalize-mtime/issues) erstellen.

Logs prüfen: `data/retention_normalize_mtime.log`

## Entwickler

Pitt Roscher


-- ============================================================
-- KulturInventar – Datenbankschema Version 1
-- Zeichensatz: UTF8MB4
-- Kompatibel mit MySQL / MariaDB (Strato-Webhosting)
--
-- Bereits existierende Datenbank? Dann diese ALTER-Befehle
-- einmalig ausführen, um die Login-Schutz-Spalten zu ergänzen:
--
--   ALTER TABLE `users`
--     ADD COLUMN `login_versuche` TINYINT NOT NULL DEFAULT 0,
--     ADD COLUMN `gesperrt_bis`   DATETIME DEFAULT NULL;
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 1;

-- ------------------------------------------------------------
-- Tabelle: inventar
-- Zentrale Artikeltabelle
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `inventar` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `inventarnummer`  VARCHAR(50)     NOT NULL,
    `bezeichnung`     VARCHAR(255)    NOT NULL,
    `kategorie`       VARCHAR(100)    NOT NULL,
    `standort`        VARCHAR(100)    DEFAULT NULL,
    `menge`           INT             NOT NULL DEFAULT 1,
    `masse`           VARCHAR(100)    DEFAULT NULL,
    `bemerkung`       TEXT            DEFAULT NULL,
    `bild_pfad`       VARCHAR(500)    DEFAULT NULL,
    `erstellt_am`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_inventarnummer` (`inventarnummer`),
    INDEX `idx_bezeichnung`  (`bezeichnung`),
    INDEX `idx_kategorie`    (`kategorie`),
    INDEX `idx_standort`     (`standort`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabelle: inventar_bilder
-- Mehrere Bilder pro Artikel (n:1 → inventar)
-- Migration für bestehende DB:
--
--   CREATE TABLE IF NOT EXISTS `inventar_bilder` (
--     ... (siehe unten)
--   );
--
-- Das alte Feld `bild_pfad` in `inventar` bleibt vorerst
-- für Rückwärtskompatibilität erhalten.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `inventar_bilder` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `inventar_id`  INT UNSIGNED    NOT NULL,
    `dateiname`    VARCHAR(255)    NOT NULL,
    `reihenfolge`  TINYINT         NOT NULL DEFAULT 0,
    `erstellt_am`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_inventar_id` (`inventar_id`),
    INDEX `idx_reihenfolge` (`inventar_id`, `reihenfolge`),

    CONSTRAINT `fk_bilder_inventar`
        FOREIGN KEY (`inventar_id`)
        REFERENCES `inventar` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabelle: users
-- Benutzer mit PIN-Authentifizierung
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(100)    NOT NULL,
    `pin_code_hash`   VARCHAR(255)    NOT NULL,
    `aktiv`           TINYINT(1)      NOT NULL DEFAULT 1,
    `login_versuche`  TINYINT         NOT NULL DEFAULT 0,
    `gesperrt_bis`    DATETIME        DEFAULT NULL,
    `erstellt_am`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_aktiv` (`aktiv`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabelle: logs
-- Protokoll aller Änderungen an Inventarartikeln
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `logs` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `inventar_id`  INT UNSIGNED    NOT NULL,
    `user_id`      INT UNSIGNED    DEFAULT NULL,
    `aktion`       VARCHAR(100)    NOT NULL,
    `feldname`     VARCHAR(100)    DEFAULT NULL,
    `alter_wert`   TEXT            DEFAULT NULL,
    `neuer_wert`   TEXT            DEFAULT NULL,
    `erstellt_am`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_inventar_id` (`inventar_id`),
    INDEX `idx_user_id`     (`user_id`),
    INDEX `idx_erstellt_am` (`erstellt_am`),

    CONSTRAINT `fk_logs_inventar`
        FOREIGN KEY (`inventar_id`)
        REFERENCES `inventar` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT `fk_logs_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

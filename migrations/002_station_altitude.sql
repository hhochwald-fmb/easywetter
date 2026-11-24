-- Höhe der Station über dem Meeresspiegel (Meter), für die Umrechnung des Luftdrucks auf Meereshöhe
ALTER TABLE stations ADD COLUMN altitude SMALLINT NULL DEFAULT NULL AFTER name;

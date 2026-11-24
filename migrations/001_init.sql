-- EasyWetter 2 – Grundschema
-- Alle Zeitstempel werden in UTC gespeichert.

CREATE TABLE IF NOT EXISTS settings (
  name VARCHAR(64) NOT NULL PRIMARY KEY,
  value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schools (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  city VARCHAR(100) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rollen:
--   admin   = Oberadmin (Betreiber der Plattform)
--   teacher = Lehrkraft (is_school_admin = 1 -> darf Kolleg:innen der eigenen Schule verwalten)
--   group   = Schülergruppe (meldet sich an, verwaltet Stationen, gestaltet ihr Dashboard)
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  role ENUM('admin','teacher','group') NOT NULL,
  school_id INT UNSIGNED NULL,
  teacher_id INT UNSIGNED NULL,
  username VARCHAR(60) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_school_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  public_slug VARCHAR(32) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_public_slug (public_slug),
  KEY idx_users_school (school_id),
  KEY idx_users_teacher (teacher_id),
  CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
  CONSTRAINT fk_users_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Selbstregistrierungen von Schulen, warten auf Freigabe durch den Oberadmin
CREATE TABLE IF NOT EXISTS registrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  school_name VARCHAR(150) NOT NULL,
  school_city VARCHAR(100) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  message TEXT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reg_username (username),
  KEY idx_reg_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  token_hint VARCHAR(12) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NULL,
  UNIQUE KEY uq_stations_token (token_hash),
  KEY idx_stations_group (group_id),
  CONSTRAINT fk_stations_group FOREIGN KEY (group_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS readings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  station_id INT UNSIGNED NOT NULL,
  sensor_key VARCHAR(32) NOT NULL,
  value DOUBLE NOT NULL,
  recorded_at DATETIME NOT NULL,
  KEY idx_readings_lookup (station_id, sensor_key, recorded_at),
  KEY idx_readings_time (recorded_at),
  CONSTRAINT fk_readings_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dashboards (
  group_id INT UNSIGNED NOT NULL PRIMARY KEY,
  config MEDIUMTEXT NOT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  public_slug VARCHAR(32) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dash_slug (public_slug),
  CONSTRAINT fk_dash_group FOREIGN KEY (group_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  username VARCHAR(60) NOT NULL,
  attempted_at DATETIME NOT NULL,
  KEY idx_la_user (username, attempted_at),
  KEY idx_la_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

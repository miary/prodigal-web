CREATE TABLE IF NOT EXISTS signups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(254) NOT NULL,
  full_name VARCHAR(80) NULL,
  consent TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_replied_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_signups_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS signup_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_signup_attempts_ip (ip_hash),
  KEY idx_signup_attempts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

-- Anonymous visit statistics (api/track.php). Only today's salt is kept.
CREATE TABLE IF NOT EXISTS stats_salts (
  day DATE NOT NULL,
  salt CHAR(64) NOT NULL,
  PRIMARY KEY (day)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

CREATE TABLE IF NOT EXISTS stats_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  day DATE NOT NULL,
  visitor CHAR(64) NOT NULL,
  name VARCHAR(20) NOT NULL,
  label VARCHAR(60) NULL,
  referrer VARCHAR(120) NULL,
  source VARCHAR(60) NULL,
  campaign VARCHAR(60) NULL,
  device VARCHAR(10) NULL,
  timezone VARCHAR(40) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_stats_day_name (day, name),
  KEY idx_stats_day_visitor (day, visitor)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;

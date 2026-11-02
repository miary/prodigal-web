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

-- Only for your existing XAMPP database (it was created from an older dump).
-- Adds what the current code needs. Run it once in phpMyAdmin > trueballot > SQL.
-- Skip any line that says the column/table already exists.

ALTER TABLE elections
  ADD COLUMN applications_open_at  DATETIME NULL AFTER description,
  ADD COLUMN applications_close_at DATETIME NULL AFTER applications_open_at;

CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reset_token (token_hash),
  KEY idx_reset_user (user_id, created_at),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE positions
  ADD COLUMN fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER min_candidate_age;

CREATE TABLE IF NOT EXISTS photos (
  user_id    INT UNSIGNED NOT NULL,
  image      MEDIUMBLOB NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_photo_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

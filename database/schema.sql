-- TrueBallot database
-- Works on XAMPP (phpMyAdmin) and on Aiven MySQL.
--
-- XAMPP: create a database called "trueballot", open its SQL tab, paste this file and click Go.
-- Aiven: connect to your "defaultdb" database (e.g. with MySQL Workbench or the mysql command) and run this file.

SET NAMES utf8mb4;

CREATE TABLE users (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name         VARCHAR(150) NOT NULL,
  email             VARCHAR(150) NOT NULL,
  password_hash     VARCHAR(255) NOT NULL,
  age               TINYINT UNSIGNED NOT NULL,
  state             VARCHAR(100) NOT NULL,
  sex               ENUM('male','female') NOT NULL DEFAULT 'no',
  disability_status ENUM('yes','no') NOT NULL DEFAULT 'no',
  role              ENUM('voter','admin') NOT NULL DEFAULT 'voter',
  status            ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE elections (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title                 VARCHAR(200) NOT NULL,
  description           TEXT NULL,
  applications_open_at  DATETIME NULL,
  applications_close_at DATETIME NULL,
  starts_at             DATETIME NOT NULL,
  ends_at               DATETIME NOT NULL,
  status                ENUM('draft','scheduled','open','closed','results_published') NOT NULL DEFAULT 'draft',
  created_by            INT UNSIGNED NOT NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_elections_creator FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE positions (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  election_id       INT UNSIGNED NOT NULL,
  title             VARCHAR(150) NOT NULL,
  min_candidate_age TINYINT UNSIGNED NOT NULL DEFAULT 18,
  fee_amount        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_position_title (election_id, title),
  CONSTRAINT fk_positions_election FOREIGN KEY (election_id) REFERENCES elections (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE candidate_applications (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id            INT UNSIGNED NOT NULL,
  position_id        INT UNSIGNED NOT NULL,
  display_name       VARCHAR(150) NOT NULL,
  manifesto          TEXT NULL,
  fee_amount         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  fee_status         ENUM('unpaid','recorded_paid','waived') NOT NULL DEFAULT 'unpaid',
  application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by        INT UNSIGNED NULL,
  reviewed_at        DATETIME NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_position_application (user_id, position_id),
  CONSTRAINT fk_application_user     FOREIGN KEY (user_id)     REFERENCES users (id)     ON DELETE CASCADE,
  CONSTRAINT fk_application_position FOREIGN KEY (position_id) REFERENCES positions (id) ON DELETE CASCADE,
  CONSTRAINT fk_application_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE candidates (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id INT UNSIGNED NOT NULL,
  position_id    INT UNSIGNED NOT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_candidate_application (application_id),
  CONSTRAINT fk_candidates_application FOREIGN KEY (application_id) REFERENCES candidate_applications (id) ON DELETE CASCADE,
  CONSTRAINT fk_candidates_position    FOREIGN KEY (position_id)    REFERENCES positions (id)              ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE votes (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  voter_id     INT UNSIGNED NOT NULL,
  election_id  INT UNSIGNED NOT NULL,
  position_id  INT UNSIGNED NOT NULL,
  candidate_id INT UNSIGNED NOT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_voter_position (voter_id, position_id),
  KEY idx_votes_election (election_id),
  CONSTRAINT fk_votes_voter     FOREIGN KEY (voter_id)     REFERENCES users (id),
  CONSTRAINT fk_votes_election  FOREIGN KEY (election_id)  REFERENCES elections (id),
  CONSTRAINT fk_votes_position  FOREIGN KEY (position_id)  REFERENCES positions (id),
  CONSTRAINT fk_votes_candidate FOREIGN KEY (candidate_id) REFERENCES candidates (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
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

-- One photo per user (voter card and ballot). Stored here because Render's free plan deletes files on restart.
CREATE TABLE photos (
  user_id    INT UNSIGNED NOT NULL,
  image      MEDIUMBLOB NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_photo_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id INT UNSIGNED NULL,
  action        VARCHAR(150) NOT NULL,
  details       TEXT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- First administrator. Password: Admin@12345
-- Change the email to your real email so admin notifications reach you, and change the password after logging in.
INSERT INTO users (full_name, email, password_hash, age, state, sex, disability_status, role, status)
VALUES ('System Administrator', 'admin@trueballot.test',
        '$2y$10$r7XYBIBczf/tB42Lvxi0S.vlO/7k9w7F0vY3zjgNqb4drzt.l/mdS',
        30, 'N/A', 'prefer_not_to_say', 'prefer_not_to_say', 'admin', 'approved');

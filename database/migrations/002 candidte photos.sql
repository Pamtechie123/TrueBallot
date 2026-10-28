-- Candidate photos live in the database (Render's free plan deletes files when it restarts).
CREATE TABLE IF NOT EXISTS candidate_photos (
  candidate_id INT UNSIGNED NOT NULL,
  image        MEDIUMBLOB NOT NULL,
  updated_at   DATETIME NOT NULL,
  PRIMARY KEY (candidate_id),
  CONSTRAINT fk_candidate_photo FOREIGN KEY (candidate_id) REFERENCES candidates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Each candidate is now one of the registered voters. (One statement.)
ALTER TABLE candidates
  ADD COLUMN user_id INT UNSIGNED NULL AFTER position_id,
  ADD KEY idx_candidates_user (user_id),
  ADD CONSTRAINT fk_candidates_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL;
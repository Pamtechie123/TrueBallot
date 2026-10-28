-- Candidates no longer need a voter account. They keep their own name, party, manifesto and fee.
-- (One statement, so it either fully applies or not at all.)
ALTER TABLE candidates
  MODIFY application_id INT UNSIGNED NULL,
  ADD COLUMN full_name  VARCHAR(150) NULL AFTER position_id,
  ADD COLUMN age        TINYINT UNSIGNED NULL AFTER full_name,
  ADD COLUMN party      VARCHAR(100) NULL AFTER age,
  ADD COLUMN email      VARCHAR(150) NULL AFTER party,
  ADD COLUMN manifesto  TEXT NULL AFTER email,
  ADD COLUMN fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER manifesto,
  ADD COLUMN fee_status ENUM('unpaid','recorded_paid','waived') NOT NULL DEFAULT 'unpaid' AFTER fee_amount;
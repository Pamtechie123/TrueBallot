-- Copy the people who are already on a ballot, so nothing is lost. Safe to run more than once.
UPDATE candidates c
JOIN candidate_applications a ON a.id = c.application_id
JOIN users u ON u.id = a.user_id
SET c.full_name  = a.display_name,
    c.age        = u.age,
    c.email      = u.email,
    c.manifesto  = a.manifesto,
    c.fee_amount = a.fee_amount,
    c.fee_status = a.fee_status
WHERE c.full_name IS NULL;

INSERT IGNORE INTO candidate_photos (candidate_id, image, updated_at)
SELECT c.id, ph.image, ph.updated_at
FROM candidates c
JOIN candidate_applications a ON a.id = c.application_id
JOIN photos ph ON ph.user_id = a.user_id;
-- Link the candidates that already exist to their voter account. Safe to run more than once.
UPDATE candidates c
JOIN candidate_applications a ON a.id = c.application_id
SET c.user_id = a.user_id
WHERE c.user_id IS NULL;
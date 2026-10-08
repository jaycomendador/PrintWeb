-- Unpaid jobs do not receive a queue number until payment is confirmed.
UPDATE print_jobs
SET queue_number = NULL
WHERE payment_status <> 'successful';

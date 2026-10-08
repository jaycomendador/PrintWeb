# Database and payment setup

## Database

Import `schema.sql` into MySQL. Configure the connection with `PRINTWEB_DB_HOST`, `PRINTWEB_DB_PORT`, `PRINTWEB_DB_NAME`, `PRINTWEB_DB_USER`, and `PRINTWEB_DB_PASS`; local XAMPP defaults are provided for development.

The schema creates tables and application settings, but does not insert demo users, printers, or ESP32 devices. Existing records are not deleted. Register an account, then promote the intended administrator in MySQL:

```sql
UPDATE users SET role = 'admin' WHERE email = 'administrator@example.com';
```

Create printer and ESP32 records from the admin pages after signing in.

For an existing database, run `migrations/002_simulated_payments.sql` to allow simulation payment records, followed by `migrations/003_queue_after_payment.sql` to remove queue numbers from unpaid jobs.

## Payment options

Users can choose online payment simulation or cash at the print counter. Online simulation records a payment with method `simulation` and queues the job; no card details are requested and no real money is collected. Cash stays pending until an administrator confirms receipt on the Payments page, then the job enters the queue. A queue number is assigned only after payment is confirmed. Admins can manage printing statuses without an ESP32 or connected printer; hardware commands are optional and only sent to connected ESP32 devices. Payment and print-status changes create website notifications. Do not use simulated payment records as actual revenue or evidence of funds received. A real payment provider must be integrated separately before accepting real online payments.

# TrueBallot

Voting management system built with plain PHP 8.2 (OOP, MVC, no framework) and MySQL.

## Email notifications

Users get an email when they:
- register (and admins are told a new voter is waiting)
- log in (security alert with time and IP address)
- are approved, rejected or suspended by an admin
- apply as a candidate (admins are told too), and when the application is approved or rejected
- cast a vote
- ask for a password reset, and after the password is changed

All approved voters get an email when a new election is created and when results are published.

## Other features
- Voters can add a photo when registering (optional) or later from their dashboard. Candidates must have a photo
  when they apply; it is shown next to their name on the ballot.
- The admin sets the application fee for each position when adding it (0 = free).
- On the Elections page the admin can close applications, start voting and end voting early, then publish results.

All the email wording is in `app/Services/Notifier.php`. Sending is done by `app/Services/Mailer.php`.

Emails go through [Brevo](https://www.brevo.com) (free plan: 300 emails a day). We use Brevo's web API
instead of SMTP because Render's free plan blocks SMTP. If no Brevo key is set (for example on XAMPP),
emails are saved as `.html` files in `storage/mail/` so you can open them in your browser.

## Run locally (XAMPP)

1. Put this folder in `C:\xampp\htdocs\TrueBallot` and start Apache and MySQL.
2. In phpMyAdmin create a database called `trueballot`, open its SQL tab, paste `database/schema.sql` and click Go.
   (Already have an old `trueballot` database? Run `database/update_existing_xampp_db.sql` instead.)
3. Open http://localhost/TrueBallot/
4. Admin login: `admin@trueballot.test` / `Admin@12345`

Optional: copy `.env.example` to `.env` to change settings locally (e.g. add your Brevo key to send real emails).

## Deploy: Aiven (database) + Render (website)

### 1. Aiven MySQL
1. Create a free MySQL service on https://aiven.io.
2. From the service overview copy the **host**, **port**, **user** (`avnadmin`) and **password**.
   The database name is `defaultdb`.
3. Download the **CA certificate** and save it as `config/ca.pem` in this project (it is public, safe to commit).
4. Run `database/schema.sql` on `defaultdb` (MySQL Workbench, DBeaver, or
   `mysql -h HOST -P PORT -u avnadmin -p --ssl-ca=config/ca.pem defaultdb < database/schema.sql`).
5. Change the admin email to your real email:
   `UPDATE users SET email = 'you@gmail.com' WHERE id = 1;`

### 2. Brevo
1. Sign up at https://www.brevo.com.
2. Senders, Domains & Dedicated IPs > Senders > add and verify the email you will send from.
3. SMTP & API > API Keys > create a key.

### 3. Render
1. Push this project to GitHub.
2. On https://render.com: New > Web Service > connect the repo. Render finds the `Dockerfile` by itself.
3. Add these environment variables:

| Key | Value |
|---|---|
| `APP_URL` | `https://your-app.onrender.com` |
| `APP_DEBUG` | `false` |
| `DB_HOST` | Aiven host |
| `DB_PORT` | Aiven port |
| `DB_NAME` | `defaultdb` |
| `DB_USER` | `avnadmin` |
| `DB_PASS` | Aiven password |
| `BREVO_API_KEY` | your Brevo API key |
| `MAIL_FROM_EMAIL` | the sender email you verified on Brevo |
| `MAIL_FROM_NAME` | `TrueBallot` |

4. Deploy. After the first deploy, set `APP_URL` to the real Render address if you guessed it.

**Note:** Render's free plan does not keep files between deploys, so log files are lost when the app
restarts. Photos are stored in the database, so they are safe.

## Folders
- `public/` the only folder visitors can reach (index.php, CSS, JS)
- `app/Core` router, base controller/model, auth, session, validation, errors, logger, helpers
- `app/Models`, `app/Controllers`, `app/Views`, `app/Interfaces`
- `app/Services` email (Mailer, Notifier) and photos (stored in the database)
- `config/` settings (read from environment variables) and routes
- `database/` SQL files
- `storage/` logs and saved emails (not committed)

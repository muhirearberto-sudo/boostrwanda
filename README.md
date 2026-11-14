# BoostRwanda - Work. Earn. Connect.

A social and earning platform built with **native PHP 8+ (no framework), MySQL (PDO), HTML5/CSS3/JavaScript and PWA support**,
following the *BoostRwanda System Level 1 Data Flow Diagram*.

> **Honest status:** all code was written and statically checked (file/include/URL consistency, SQL table and column names,
> bracket balance), but **it has not been executed** - the build environment had no PHP or MySQL. Expect that a first run
> on your machine may reveal small bugs. Run `tests/run_checks.php` first; it tests the important logic (see "Testing").

---------------------------------------------------------------------

## 1. Requirements source

The only requirements document supplied was the **Level 1 DFD image**. No written SRS was received, so:

| Confirmed by the DFD | Implemented as |
|---|---|
| External entities: User (Member), Freelancer, Advertiser, School Management, Admin, Payment Gateway | Roles `member`, `freelancer`, `advertiser`, `school`, `admin`; gateway = sandbox simulator + signed webhook |
| 1.0 Manage User Registration - details (name, student ID, contact) in, confirmation / Member ID out, D1 Users DB | `auth/`, `user/profile.php`, tables `users`, `profiles` (Member ID like `BR000005`) |
| 2.0 Manage Tasks (Services & Orders) - task details/proof in, approval/earnings/notifications out, D2 | `tasks/`, `services/`, tables `tasks`, `task_submissions`, `services`, `orders`, `categories` |
| 3.0 Manage Wallet & Payments - save transaction, payment request to gateway, confirmation/status back, D3; campaign report/status for Advertiser | `wallet/`, `includes/wallet_lib.php`, `advertiser/`, tables `wallets`, `transactions`, `payment_requests`, `withdrawal_requests`, `campaigns` |
| 4.0 Communication (Chat & Social) - messages/posts/likes in, replies/notifications out, moderation/reports to Admin, D5 | `messages/`, `social/`, `admin/moderation.php`, tables `conversations`, `messages`, `posts`, `post_likes`, `post_comments`, `content_reports`, `notifications` |
| 5.0 Generate Reports - request (type, date range) in; reports (earnings, users, tasks, payments) out to School Management; D4 | `includes/reports_lib.php`, `admin/reports.php`, `school/reports.php` |
| Stack banner: Native PHP, MySQL, HTML5, CSS3, JavaScript, PWA | exactly that |

### Assumptions (NOT stated in the DFD - change them if your SRS says otherwise)
1. **Roles at sign-up:** anyone can register as Member, Freelancer or Advertiser. Admin and School accounts are never self-registered; an admin creates them by changing a user's role in *Admin > Users*.
2. **Tasks are platform-funded and created/reviewed by Admin only.** Rewards are paid from the platform, not from advertiser money (no escrow/ad-budget billing).
3. Members **and** Freelancers can accept tasks and earn; Freelancers can additionally publish services.
4. One attempt per person per task; a rejected submission may be fixed and resubmitted. Each accepted task uses one "slot".
5. **Orders** (service requests) track status only (requested -> accepted -> completed/cancelled). Payment between buyer and provider is arranged outside the wallet.
6. **Withdrawals:** funds are held immediately; the administrator approves or rejects. Approval *records* the payout - the real mobile-money/bank transfer is done manually outside the system. Rejection returns the funds.
7. **Campaigns:** advertisers create campaigns; admin approves/rejects. The advertiser "report" shows status and budget only. There is no impression/click tracking, and no ad display inside the app.
8. **Messaging:** any active Member, Freelancer, Advertiser or Admin may message any other by Member ID. School Management has no chat access.
9. **School Management** sees users, tasks, earnings and payments reports (names and Member IDs, but not email/phone), plus CSV export. It cannot see campaigns, messages, or settings.
10. Currency is RWF, shown with 2 decimals. Limits (min/max deposit, min withdrawal) are editable in *Admin > Settings*.
11. Registration collects email as the login name in addition to name, student ID and phone.
12. D4 "Reports & Analytics Database" is **not a separate copy of data**: reports are live SQL queries over the operational tables, so statistics cannot drift or be fabricated.

---------------------------------------------------------------------

## 2. Installation on Windows with XAMPP

1. **Install XAMPP** (PHP 8.0+), from <https://www.apachefriends.org>. Default folder: `C:\xampp`.
2. Open the **XAMPP Control Panel** and click **Start** next to **Apache** and **MySQL**.
3. **Copy the folder**: unzip `BoostRwanda.zip` and copy the whole `BoostRwanda` folder into `C:\xampp\htdocs\`.
   You should have `C:\xampp\htdocs\BoostRwanda\index.php`.
4. **Create the database**
   1. Open <http://localhost/phpmyadmin>.
   2. Click **Import** -> *Choose file* -> `C:\xampp\htdocs\BoostRwanda\database\boostrwanda_db.sql` -> **Import**.
      (The script creates the `boostrwanda_db` database by itself and loads demo data.)
5. **Database credentials**: `config/config.php` is preconfigured for XAMPP (`root`, empty password, host `127.0.0.1`).
   If you set a MySQL password, edit `config/config.php`. `config/config.example.php` is a clean template.
6. **Open** <http://localhost/BoostRwanda/>
   If you put the folder somewhere else (e.g. `htdocs/apps/BoostRwanda`), change `base_url` in `config/config.php`
   *and* the paths in `manifest.json` and `service-worker.js`.
7. Required PHP extensions (all enabled in XAMPP by default): `pdo_mysql`, `fileinfo`, `mbstring`.
   If uploads fail, make sure `extension=fileinfo` is uncommented in `C:\xampp\php\php.ini`, then restart Apache.

### Demo accounts (created by the seed script - DEMONSTRATION ONLY)
All use the password **`Demo@12345`**. Delete or change them before any real use.

| Role | Email |
|---|---|
| Admin | admin@boostrwanda.test |
| School Management | school@boostrwanda.test |
| Advertiser | advertiser@boostrwanda.test |
| Freelancer | freelancer@boostrwanda.test |
| Member | member@boostrwanda.test |

Wallets start at **0**; no fake transactions or statistics are seeded.

---------------------------------------------------------------------

## 3. Testing the main workflows (manual)

Use two browsers (or one normal + one private window) so you can be a user and the admin at the same time.

1. **Register / profile (1.0):** *Join free* -> create an account (note the Member ID shown after sign-up) -> log in -> *My profile* (name button, top right) -> edit and save; try changing the password.
2. **Create a task (2.0):** log in as **admin** -> *Admin Panel > Tasks > + New task*.
3. **Take a task:** log in as **member** or **freelancer** -> *Tasks* -> open one -> *Accept* -> upload a JPG/PNG/PDF (<= 2 MB) or write a note -> *Submit for review*. Status is visible in *My Work*.
4. **Review (2.0 -> 3.0):** as **admin** -> *Review submissions* -> **Approve** (or Reject with a reason).
5. **Earning recorded:** as the user -> *Wallet*: balance increased by the reward and a completed "earning" transaction exists; the user also got a notification. Approving twice is impossible.
6. **Wallet / history:** *Wallet* and *Full history* (filter by type, status, dates).
7. **Withdrawal:** *Withdraw* (min 5,000 RWF by default) -> request is `pending` and the amount is held -> as admin: *Admin Panel > Transactions* -> Approve or Reject (rejecting returns the money).
8. **Advertiser:** log in as **advertiser** -> *Campaigns* -> *New campaign* -> status `pending`; admin approves in *Admin > Campaigns*; advertiser sees the new status on the dashboard.
9. **Messages:** as member, *Messages* -> enter another user's Member ID (e.g. `BR000004`) -> chat; messages appear live (polled every 4 s) in the other browser. A user who is not part of a conversation gets "Conversation not found".
10. **Reports (5.0):** as admin -> *Admin Panel > Reports*: pick type, date range, status; download CSV.
11. **School:** log in as **school** -> *Reports*: only Users, Tasks, Earnings, Payments are offered. Forging `?type=campaigns` in the URL falls back to an allowed report; visiting `/admin/dashboard.php` gives a 403 page.
12. **Payments (sandbox):** as member -> *Wallet > Deposit* -> enter an amount -> the **Sandbox Payment Gateway** page opens -> choose *successful* or *failed*. Only a success credits the wallet; the request cannot be processed twice.

### Automated checks
    C:\xampp\php\php.exe tests\run_checks.php
See `tests/README.md`.

---------------------------------------------------------------------

## 4. Payment gateway: sandbox and going live

* **Demo mode (default):** `wallet/deposit.php` creates a *pending* payment request. `wallet/sandbox.php` stands in for the external Payment Gateway - **no real money is involved** and pages are clearly labelled "SANDBOX". Only after a "success" outcome does `process_payment_result()` credit the wallet, inside a DB transaction, once.
* **Gateway callback endpoint:** `api/payment_webhook.php` accepts `POST` JSON `{"ref","result","signature"}` where `signature = HMAC-SHA256("ref|result", webhook_secret)`. Set a long random `payment.webhook_secret` in `config/config.php`. Example (PowerShell, after creating a deposit): compute the HMAC with your secret and POST it - an unsigned or wrongly signed call returns 403.
* **To use a real provider** (MTN MoMo, Airtel Money, Flutterwave, ...): you need merchant credentials and the provider's API documentation. Replace the redirect in `wallet/deposit.php` with the provider's payment-initiation call, and call `process_payment_result()` from a verified provider webhook. **Not implemented here because it requires external credentials.** Disable the sandbox in *Admin > Settings* when going live.
* Card numbers, PINs and CVVs are never collected or stored; withdrawals store only a phone/account number.

---------------------------------------------------------------------

## 5. Security measures

PDO prepared statements everywhere; `password_hash`/`password_verify`; `e()` output escaping; CSRF token on every state-changing form and fetch call; session cookie `HttpOnly` + `SameSite=Lax`, strict mode, ID regenerated at login and password change, idle timeout; login throttling (5 failures / 15 min per email and per IP); role check (`require_role`) on every protected page and API; uploads limited to JPG/PNG/PDF <= 2 MB, MIME-checked with `finfo`, stored under random names in `storage/uploads/`, blocked from direct URL access and streamed by `tasks/proof.php` only to the owner or an admin; DB transactions with row locks for all money movement; `CHECK (balance >= 0)`, unique `(type, related_submission_id)` to stop double earnings; amounts, rewards and statuses always come from the database, never from the browser; audit log for admin and financial actions; errors are logged, never shown (`debug => false`); `.htaccess` denies `config/`, `includes/`, `database/`, `storage/`.

---------------------------------------------------------------------

## 6. Feature status

| Feature | Status |
|---|---|
| Registration, login/logout, profile, roles, suspend/activate | Implemented |
| Tasks: list/search/filter/paginate, accept, proof upload, approve/reject, resubmit | Implemented |
| Services catalogue, orders (status tracking) | Implemented |
| Wallet, history with filters, earnings from approvals | Implemented |
| Deposit via payment gateway | **Demo / sandbox only** (real gateway needs credentials) |
| Withdrawals | Request + admin review implemented; **actual payout transfer is manual** |
| Advertiser campaigns + admin approval + status report | Implemented (no impression/click tracking, no ad serving) |
| Messaging (polling), notifications, feed (post/like/reply), reporting + moderation | Implemented |
| Admin dashboard, users, tasks, services, transactions, campaigns, moderation, settings, audit log | Implemented |
| Reports with date range/status, bar charts, CSV; school-restricted reports | Implemented |
| PWA: manifest, service worker, offline page, installable icons | Implemented (needs `localhost` or HTTPS; pages are network-only, only static assets cached) |
| Password reset by email, email verification, SMS | **Not implemented** (needs mail/SMS service) |
| Real-time chat via WebSockets | Not implemented (uses 4-second polling) |

## 7. Troubleshooting

| Problem | Fix |
|---|---|
| "Database connection failed" | MySQL not started in XAMPP, or wrong credentials in `config/config.php`; import the SQL file. |
| Blank page / "Something went wrong" | Look in `C:\xampp\apache\logs\error.log`. Temporarily set `'debug' => true` in `config/config.php`. |
| `Table ... doesn't exist` | The SQL import failed or is partial; re-import `database/boostrwanda_db.sql`. |
| Page styles missing / links 404 | Folder name or location differs from `BoostRwanda` in `htdocs`; fix `base_url`. |
| Upload fails | Enable `extension=fileinfo` in `php.ini`; keep files <= 2 MB (also check `upload_max_filesize`). |
| Locked out after failed logins | Wait 15 minutes, or run in phpMyAdmin: `DELETE FROM login_attempts;` |
| 403 "Access denied" page | Your role isn't allowed on that page - this is expected protection. |
| CHECK constraint error on import | Very old MariaDB/MySQL; use the XAMPP version shipped with PHP 8. |
| Service worker caches old CSS | In the browser dev tools > Application > Service Workers > Unregister, or bump `CACHE` in `service-worker.js`. |
# boostrwanda

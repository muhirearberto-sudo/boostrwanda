-- =====================================================================
-- BoostRwanda database  (MySQL 5.7+ / MariaDB 10.4+ as shipped with XAMPP)
-- Import with phpMyAdmin > Import, or:  mysql -u root < boostrwanda_db.sql
-- Maps to the Level 1 DFD data stores:
--   D1 Users DB .............. users, profiles, login_attempts
--   D2 Tasks & Services DB ... categories, tasks, task_submissions, services, orders
--   D3 Wallet & Payments DB .. wallets, transactions, payment_requests, withdrawal_requests
--   D4 Reports & Analytics ... NO duplicated tables: reports are live queries (includes/reports_lib.php)
--                              plus campaigns (advertiser reports) and audit_logs
--   D5 Messages & Social DB .. conversations, messages, posts, post_likes, post_comments,
--                              content_reports, notifications
-- =====================================================================
CREATE DATABASE IF NOT EXISTS boostrwanda_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE boostrwanda_db;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------- D1: USERS -------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id     VARCHAR(12)  NULL UNIQUE,                 -- "Member ID" returned on registration (DFD 1.0)
  full_name     VARCHAR(100) NOT NULL,
  student_id    VARCHAR(30)  NULL,                         -- DFD: registration details (name, student ID, contact)
  email         VARCHAR(150) NOT NULL UNIQUE,
  phone         VARCHAR(20)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,                     -- password_hash() output, never plaintext
  role          ENUM('member','freelancer','advertiser','admin','school') NOT NULL DEFAULT 'member',
  status        ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login    DATETIME NULL,
  INDEX idx_users_role (role),
  INDEX idx_users_status (status),
  INDEX idx_users_student (student_id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS profiles;
CREATE TABLE profiles (
  user_id    INT UNSIGNED PRIMARY KEY,
  bio        VARCHAR(500) NULL,
  location   VARCHAR(100) NULL,
  skills     VARCHAR(255) NULL,
  school     VARCHAR(120) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS login_attempts;
CREATE TABLE login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(150) NOT NULL,
  ip_address   VARCHAR(45)  NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempt_email (email, attempted_at),
  INDEX idx_attempt_ip (ip_address, attempted_at)
) ENGINE=InnoDB;

-- ------------------------ D2: TASKS & SERVICES ------------------------
DROP TABLE IF EXISTS categories;
CREATE TABLE categories (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  type ENUM('task','service') NOT NULL,
  UNIQUE KEY uq_category (name, type)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS tasks;
CREATE TABLE tasks (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  creator_id  INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  reward      DECIMAL(12,2) NOT NULL,
  slots       INT UNSIGNED NOT NULL DEFAULT 1,             -- how many people may take the task
  status      ENUM('open','closed') NOT NULL DEFAULT 'open',
  deadline    DATE NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (creator_id)  REFERENCES users(id),
  FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX idx_tasks_status (status),
  INDEX idx_tasks_category (category_id),
  FULLTEXT KEY ft_tasks (title, description)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS task_submissions;
CREATE TABLE task_submissions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id      INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  status       ENUM('accepted','submitted','approved','rejected') NOT NULL DEFAULT 'accepted',
  proof_note   TEXT NULL,
  proof_file   VARCHAR(255) NULL,                          -- stored (random) filename inside storage/uploads
  proof_orig   VARCHAR(255) NULL,                          -- original filename for display only
  review_note  VARCHAR(255) NULL,
  reviewed_by  INT UNSIGNED NULL,
  reviewed_at  DATETIME NULL,
  accepted_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at DATETIME NULL,
  UNIQUE KEY uq_task_user (task_id, user_id),              -- one attempt per person per task
  FOREIGN KEY (task_id)     REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)     REFERENCES users(id),
  FOREIGN KEY (reviewed_by) REFERENCES users(id),
  INDEX idx_sub_status (status)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS services;
CREATE TABLE services (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  price       DECIMAL(12,2) NOT NULL,
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (provider_id) REFERENCES users(id),
  FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX idx_services_status (status),
  FULLTEXT KEY ft_services (title, description)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  buyer_id   INT UNSIGNED NOT NULL,
  note       VARCHAR(500) NULL,
  price      DECIMAL(12,2) NOT NULL,                       -- copied from the service at request time
  status     ENUM('requested','accepted','completed','cancelled') NOT NULL DEFAULT 'requested',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services(id),
  FOREIGN KEY (buyer_id)   REFERENCES users(id),
  INDEX idx_orders_status (status)
) ENGINE=InnoDB;

-- ------------------------ D3: WALLET & PAYMENTS -----------------------
DROP TABLE IF EXISTS wallets;
CREATE TABLE wallets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL UNIQUE,
  balance    DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT chk_balance_nonneg CHECK (balance >= 0)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS transactions;
CREATE TABLE transactions (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wallet_id             INT UNSIGNED NOT NULL,
  user_id               INT UNSIGNED NOT NULL,
  type                  ENUM('earning','deposit','withdrawal') NOT NULL,
  amount                DECIMAL(14,2) NOT NULL,            -- always positive; "type" gives the direction
  status                ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
  reference             VARCHAR(40) NOT NULL UNIQUE,
  related_submission_id INT UNSIGNED NULL,
  balance_after         DECIMAL(14,2) NULL,
  description           VARCHAR(255) NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (wallet_id) REFERENCES wallets(id),
  FOREIGN KEY (user_id)   REFERENCES users(id),
  FOREIGN KEY (related_submission_id) REFERENCES task_submissions(id),
  UNIQUE KEY uq_one_earning_per_submission (type, related_submission_id),   -- blocks double payment
  INDEX idx_tx_user (user_id, created_at),
  INDEX idx_tx_status (status),
  INDEX idx_tx_type (type),
  CONSTRAINT chk_tx_amount CHECK (amount > 0)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS payment_requests;
CREATE TABLE payment_requests (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  transaction_id INT UNSIGNED NOT NULL,
  amount         DECIMAL(14,2) NOT NULL,
  provider       VARCHAR(30) NOT NULL DEFAULT 'sandbox',
  gateway_ref    VARCHAR(60) NOT NULL UNIQUE,              -- reference shared with the payment gateway
  status         ENUM('pending','confirmed','failed') NOT NULL DEFAULT 'pending',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)        REFERENCES users(id),
  FOREIGN KEY (transaction_id) REFERENCES transactions(id),
  INDEX idx_pr_status (status)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS withdrawal_requests;
CREATE TABLE withdrawal_requests (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  transaction_id  INT UNSIGNED NOT NULL,
  amount          DECIMAL(14,2) NOT NULL,
  method          ENUM('mobile_money','bank') NOT NULL,
  account_details VARCHAR(120) NOT NULL,                   -- phone number or account no. (never card numbers)
  status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note      VARCHAR(255) NULL,
  reviewed_by     INT UNSIGNED NULL,
  reviewed_at     DATETIME NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)        REFERENCES users(id),
  FOREIGN KEY (transaction_id) REFERENCES transactions(id),
  FOREIGN KEY (reviewed_by)    REFERENCES users(id),
  INDEX idx_wr_status (status)
) ENGINE=InnoDB;

-- ------------------- ADVERTISING (campaign reports) -------------------
DROP TABLE IF EXISTS campaigns;
CREATE TABLE campaigns (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  advertiser_id INT UNSIGNED NOT NULL,
  title         VARCHAR(150) NOT NULL,
  ad_text       VARCHAR(600) NOT NULL,
  target_url    VARCHAR(255) NULL,
  budget        DECIMAL(12,2) NOT NULL,
  start_date    DATE NOT NULL,
  end_date      DATE NOT NULL,
  status        ENUM('pending','active','paused','rejected','completed') NOT NULL DEFAULT 'pending',
  admin_note    VARCHAR(255) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (advertiser_id) REFERENCES users(id),
  INDEX idx_camp_status (status)
) ENGINE=InnoDB;

-- ------------------------ D5: MESSAGES & SOCIAL -----------------------
DROP TABLE IF EXISTS conversations;
CREATE TABLE conversations (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_a     INT UNSIGNED NOT NULL,                        -- always the smaller user id
  user_b     INT UNSIGNED NOT NULL,                        -- always the larger user id
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pair (user_a, user_b),
  FOREIGN KEY (user_a) REFERENCES users(id),
  FOREIGN KEY (user_b) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS messages;
CREATE TABLE messages (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  sender_id       INT UNSIGNED NOT NULL,
  body            VARCHAR(2000) NOT NULL,
  is_read         TINYINT(1) NOT NULL DEFAULT 0,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id)       REFERENCES users(id),
  INDEX idx_msg_conv (conversation_id, id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS posts;
CREATE TABLE posts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  body       VARCHAR(1000) NOT NULL,
  status     ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_posts_status (status, created_at)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS post_likes;
CREATE TABLE post_likes (
  post_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id, user_id),
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS post_comments;
CREATE TABLE post_comments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  body       VARCHAR(500) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_comments_post (post_id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS content_reports;
CREATE TABLE content_reports (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reporter_id INT UNSIGNED NOT NULL,
  target_type ENUM('post','comment','message','user') NOT NULL,
  target_id   INT UNSIGNED NOT NULL,
  reason      VARCHAR(300) NOT NULL,
  status      ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
  handled_by  INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reporter_id) REFERENCES users(id),
  FOREIGN KEY (handled_by)  REFERENCES users(id),
  INDEX idx_cr_status (status)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  message    VARCHAR(255) NOT NULL,
  link       VARCHAR(255) NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notif_user (user_id, is_read)
) ENGINE=InnoDB;

-- ------------------------- SYSTEM / AUDIT -----------------------------
DROP TABLE IF EXISTS audit_logs;
CREATE TABLE audit_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(60)  NOT NULL,
  details    VARCHAR(500) NULL,
  ip_address VARCHAR(45)  NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_action (action),
  INDEX idx_audit_user (user_id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
  setting_key   VARCHAR(50) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA  (DEMONSTRATION ONLY - delete these accounts before any real use)
-- All demo accounts share the password:  Demo@12345
-- No balances, transactions or statistics are seeded: wallets start at 0.
-- =====================================================================
INSERT INTO users (id, member_id, full_name, student_id, email, phone, password_hash, role) VALUES
 (1,'BR000001','Demo Admin',        NULL,      'admin@boostrwanda.test',      '0788000001','$2y$12$wfomTQf8f9r1BncJtqVi2OFd8C7167mV1ruIVwwtNLcFj4Kv4ZuL.','admin'),
 (2,'BR000002','Demo School Office', NULL,     'school@boostrwanda.test',     '0788000002','$2y$12$wfomTQf8f9r1BncJtqVi2OFd8C7167mV1ruIVwwtNLcFj4Kv4ZuL.','school'),
 (3,'BR000003','Demo Advertiser',   NULL,      'advertiser@boostrwanda.test', '0788000003','$2y$12$wfomTQf8f9r1BncJtqVi2OFd8C7167mV1ruIVwwtNLcFj4Kv4ZuL.','advertiser'),
 (4,'BR000004','Demo Freelancer',   'STU-1004','freelancer@boostrwanda.test', '0788000004','$2y$12$wfomTQf8f9r1BncJtqVi2OFd8C7167mV1ruIVwwtNLcFj4Kv4ZuL.','freelancer'),
 (5,'BR000005','Demo Member',       'STU-1005','member@boostrwanda.test',     '0788000005','$2y$12$wfomTQf8f9r1BncJtqVi2OFd8C7167mV1ruIVwwtNLcFj4Kv4ZuL.','member');
INSERT INTO profiles (user_id, bio, location) VALUES
 (1,'Platform administrator (demo).','Kigali'),(2,'School management (demo).','Kigali'),
 (3,'Advertiser (demo).','Kigali'),(4,'Freelancer (demo).','Huye'),(5,'Student member (demo).','Musanze');
INSERT INTO wallets (user_id, balance) VALUES (1,0),(2,0),(3,0),(4,0),(5,0);

INSERT INTO categories (name, type) VALUES
 ('Data Entry','task'),('Surveys','task'),('Social Promotion','task'),('Content Writing','task'),
 ('Design','service'),('Tutoring','service'),('Writing & Translation','service'),('Tech Help','service');

INSERT INTO tasks (creator_id, category_id, title, description, reward, slots, status, deadline) VALUES
 (1,1,'Type 20 product records into a spreadsheet','Copy 20 records from the provided list into a spreadsheet and upload a screenshot of the finished sheet as proof.',1500.00,10,'open',DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
 (1,2,'Complete a 5-minute campus survey','Answer the campus survey and upload a screenshot of the confirmation page as proof.',800.00,50,'open',DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
 (1,4,'Write a 150-word club introduction','Write an original 150-word introduction for a school club of your choice. Paste the text in the proof note.',2000.00,5,'open',DATE_ADD(CURDATE(), INTERVAL 21 DAY));

INSERT INTO services (provider_id, category_id, title, description, price, status) VALUES
 (4,6,'Math tutoring (secondary level)','One-hour online or in-person tutoring session for S1-S6 mathematics.',3000.00,'active'),
 (4,5,'Simple poster / flyer design','One poster or flyer for your event or club, delivered as PNG or PDF.',5000.00,'active');

INSERT INTO campaigns (advertiser_id, title, ad_text, budget, start_date, end_date, status) VALUES
 (3,'Back-to-school promotion','Discounted stationery for students - visit our shop in Kigali.',50000.00,CURDATE(),DATE_ADD(CURDATE(), INTERVAL 30 DAY),'pending');

INSERT INTO settings (setting_key, setting_value) VALUES
 ('min_deposit','1000'),('max_deposit','500000'),('min_withdrawal','5000'),
 ('registration_open','1'),('sandbox_gateway_enabled','1');

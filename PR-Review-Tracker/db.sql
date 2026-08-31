-- ============================================================
--  PR Review Tracker - Database Schema
--  Import this file into MySQL/MariaDB:  mysql -u root pr_review_tracker < db.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS pr_review_tracker
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pr_review_tracker;

-- Users of the system. role = admin | reviewer | developer
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  role ENUM('admin','reviewer','developer') NOT NULL DEFAULT 'developer',
  github_username VARCHAR(100) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Repositories tracked by the system
CREATE TABLE IF NOT EXISTS repositories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  provider ENUM('github','gitlab') NOT NULL DEFAULT 'github',
  repo_full_name VARCHAR(190) NOT NULL UNIQUE,
  owner_id INT UNSIGNED NOT NULL,
  sync_token VARCHAR(255) DEFAULT NULL,
  synced_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_repo_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pull requests fetched from GitHub
CREATE TABLE IF NOT EXISTS pull_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  repo_id INT UNSIGNED NOT NULL,
  github_pr_number INT UNSIGNED NOT NULL,
  title VARCHAR(500) NOT NULL,
  author VARCHAR(100) NOT NULL,
  url VARCHAR(500) DEFAULT NULL,
  state VARCHAR(20) NOT NULL DEFAULT 'open',
  last_activity_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_repo_pr (repo_id, github_pr_number),
  CONSTRAINT fk_pr_repo FOREIGN KEY (repo_id) REFERENCES repositories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Review decisions made by reviewers
CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pr_id INT UNSIGNED NOT NULL,
  reviewer_id INT UNSIGNED NOT NULL,
  decision ENUM('approved','changes','rejected') NOT NULL,
  comment TEXT DEFAULT NULL,
  reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_review_pr FOREIGN KEY (pr_id) REFERENCES pull_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_user FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Default admin account
--   email    : admin@example.com
--   password : admin123
INSERT INTO users (name, email, role, github_username, password_hash) VALUES
  ('Admin', 'admin@example.com', 'admin', 'admin',
   '$2y$12$zgk/vUpBQ0Jh5VVzeNHFaeVSVj7dx99hEK1X48256occwZFsh8Ea.')
ON DUPLICATE KEY UPDATE email = email;

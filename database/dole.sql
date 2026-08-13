-- ============================================================
-- DOLE GIP/SPES System — MySQL Schema (Laravel-equivalent)
-- Reference copy of what the Laravel migrations create.
-- The actual source of truth is the migration files in
-- database/migrations/ — run `php artisan migrate` to apply them.
-- This file is provided for reference, or for direct import via
-- phpMyAdmin/mysql CLI if you ever need to bypass migrations.
-- ============================================================

CREATE DATABASE IF NOT EXISTS dole_gip_spes;
USE dole_gip_spes;

-- ============================================================
-- 1. USERS (Laravel default table + role column)
-- ============================================================

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  role ENUM('student', 'staff') NOT NULL DEFAULT 'student',
  email_verified_at TIMESTAMP NULL,
  password VARCHAR(255) NOT NULL,
  remember_token VARCHAR(100) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- ============================================================
-- 2. PASSWORD RESET TOKENS (Laravel default)
-- ============================================================

CREATE TABLE password_reset_tokens (
  email VARCHAR(255) NOT NULL PRIMARY KEY,
  token VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- ============================================================
-- 3. SESSIONS (Laravel default, used for auth session storage)
-- ============================================================

CREATE TABLE sessions (
  id VARCHAR(255) NOT NULL PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent TEXT NULL,
  payload LONGTEXT NOT NULL,
  last_activity INT NOT NULL,
  INDEX sessions_user_id_index (user_id),
  INDEX sessions_last_activity_index (last_activity)
) ENGINE=InnoDB;

-- ============================================================
-- 4. PERSONAL ACCESS TOKENS (Sanctum, for API auth)
-- ============================================================

CREATE TABLE personal_access_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tokenable_type VARCHAR(255) NOT NULL,
  tokenable_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  abilities TEXT NULL,
  last_used_at TIMESTAMP NULL,
  expires_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX personal_access_tokens_tokenable_index (tokenable_type, tokenable_id)
) ENGINE=InnoDB;

-- ============================================================
-- 5. STUDENT DETAILS
-- ============================================================

CREATE TABLE student_details (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  school_name VARCHAR(255) NOT NULL,
  course VARCHAR(255) NOT NULL,
  year_level TINYINT UNSIGNED NULL,
  gwa DECIMAL(3,2) NULL,
  is_indigent BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 6. PROGRAMS
-- ============================================================

CREATE TABLE programs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  program_type ENUM('GIP', 'SPES') NOT NULL,
  description TEXT NULL,
  total_slots INT UNSIGNED NOT NULL DEFAULT 0,
  application_start DATE NOT NULL,
  application_deadline DATE NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 7. HOST AGENCIES
-- ============================================================

CREATE TABLE host_agencies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  address VARCHAR(255) NULL,
  contact_person VARCHAR(255) NULL,
  contact_number VARCHAR(255) NULL,
  slots_available INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- ============================================================
-- 8. APPLICATIONS
-- ============================================================

CREATE TABLE applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  applicant_id BIGINT UNSIGNED NOT NULL,
  program_id BIGINT UNSIGNED NOT NULL,
  status ENUM(
    'draft', 'submitted', 'under_review', 'approved',
    'rejected', 'deployed', 'completed', 'withdrawn'
  ) NOT NULL DEFAULT 'draft',
  remarks TEXT NULL,
  submitted_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY applications_applicant_program_unique (applicant_id, program_id),
  FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 9. APPLICATION STATUS HISTORY (audit trail)
-- ============================================================

CREATE TABLE application_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  status ENUM(
    'draft', 'submitted', 'under_review', 'approved',
    'rejected', 'deployed', 'completed', 'withdrawn'
  ) NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  remarks TEXT NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 10. DOCUMENTS
-- ============================================================

CREATE TABLE documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL,
  doc_type ENUM(
    'cor', 'valid_id', 'grades', 'barangay_clearance', 'parents_consent', 'other'
  ) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  is_verified BOOLEAN NOT NULL DEFAULT FALSE,
  verified_by BIGINT UNSIGNED NULL,
  verified_at TIMESTAMP NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 11. ASSIGNMENTS
-- ============================================================

CREATE TABLE assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id BIGINT UNSIGNED NOT NULL UNIQUE,
  host_agency_id BIGINT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (host_agency_id) REFERENCES host_agencies(id)
) ENGINE=InnoDB;

-- ============================================================
-- 12. INDEXES (extra, beyond what FKs already create)
-- ============================================================

CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_documents_application ON documents(application_id);
CREATE INDEX idx_status_history_application ON application_status_history(application_id);

-- ============================================================
-- END OF SCHEMA
-- ============================================================
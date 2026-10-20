-- DARPO LAGUNA e-Records: Document Management and Tracking System
-- MySQL 8+ / MariaDB 10.4+   |   Import: mysql -u root -p < schema.sql
CREATE DATABASE IF NOT EXISTS dar_erecords CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dar_erecords;
SET FOREIGN_KEY_CHECKS=0;

-- 16. System configuration -------------------------------------------------
CREATE TABLE divisions(
  id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(10) NOT NULL UNIQUE, name VARCHAR(120) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE sections(
  id INT AUTO_INCREMENT PRIMARY KEY, division_id INT NOT NULL, name VARCHAR(120) NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY(division_id) REFERENCES divisions(id)
) ENGINE=InnoDB;
CREATE TABLE categories(
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, prefix VARCHAR(10) NOT NULL,
  default_access ENUM('open','restricted') NOT NULL DEFAULT 'restricted', naming_convention VARCHAR(150) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE settings(skey VARCHAR(60) PRIMARY KEY, svalue VARCHAR(500) NOT NULL) ENGINE=InnoDB;
CREATE TABLE counters(name VARCHAR(40) NOT NULL, yr SMALLINT NOT NULL, val INT NOT NULL DEFAULT 0, PRIMARY KEY(name,yr)) ENGINE=InnoDB;

-- 12. Users and roles ------------------------------------------------------
CREATE TABLE users(
  id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, full_name VARCHAR(120) NOT NULL, email VARCHAR(150) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','records_officer','division_staff','verifier','viewer') NOT NULL DEFAULT 'viewer',
  division_id INT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  failed_attempts INT NOT NULL DEFAULT 0, locked_until DATETIME NULL, last_login DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(division_id) REFERENCES divisions(id)
) ENGINE=InnoDB;

-- 11. Retention schedule (replace sample rows with the approved DAR schedule / NAP authority)
CREATE TABLE retention_schedules(
  id INT AUTO_INCREMENT PRIMARY KEY, category_id INT NOT NULL, class_name VARCHAR(100) NOT NULL,
  active_years INT NOT NULL, inactive_years INT NOT NULL,
  disposition ENUM('permanent','dispose','transfer_nap') NOT NULL, authority_ref VARCHAR(200) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY(category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- 2. Physical records inventory --------------------------------------------
CREATE TABLE records(
  id INT AUTO_INCREMENT PRIMARY KEY,
  inventory_no VARCHAR(40) NOT NULL UNIQUE, qr_token CHAR(16) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL, ref_no VARCHAR(80) NULL,
  division_id INT NOT NULL, section_id INT NULL, category_id INT NOT NULL, doc_year SMALLINT NOT NULL, volume VARCHAR(30) NULL,
  cabinet VARCHAR(30) NULL, shelf VARCHAR(30) NULL, box VARCHAR(30) NULL, folder VARCHAR(30) NULL,
  custodian_id INT NULL,
  status ENUM('inventoried','for_scanning','scanning','for_verification','digitized','archived') NOT NULL DEFAULT 'inventoried',
  condition_flag ENUM('ok','missing','damaged','unlocated') NOT NULL DEFAULT 'ok',
  access_level ENUM('open','restricted') NOT NULL DEFAULT 'restricted', restriction_basis VARCHAR(255) NULL,
  retention_schedule_id INT NULL, retention_start DATE NULL,
  archived_at DATETIME NULL, archive_location VARCHAR(150) NULL, preservation_notes VARCHAR(255) NULL,
  disposition_status ENUM('none','pending','approved','executed') NOT NULL DEFAULT 'none',
  label_printed_at DATETIME NULL, created_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY(status), KEY(division_id,doc_year), KEY(access_level), KEY(ref_no),
  FOREIGN KEY(division_id) REFERENCES divisions(id), FOREIGN KEY(section_id) REFERENCES sections(id),
  FOREIGN KEY(category_id) REFERENCES categories(id), FOREIGN KEY(custodian_id) REFERENCES users(id),
  FOREIGN KEY(retention_schedule_id) REFERENCES retention_schedules(id)
) ENGINE=InnoDB;

-- 3. Tracking history
CREATE TABLE record_history(
  id BIGINT AUTO_INCREMENT PRIMARY KEY, record_id INT NOT NULL, action VARCHAR(40) NOT NULL,
  from_status VARCHAR(30) NULL, to_status VARCHAR(30) NULL, remarks VARCHAR(500) NULL, user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY(record_id),
  FOREIGN KEY(record_id) REFERENCES records(id), FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- 4. Classification decisions (change needs a request + independent review)
CREATE TABLE class_requests(
  id INT AUTO_INCREMENT PRIMARY KEY, record_id INT NOT NULL, old_level ENUM('open','restricted') NOT NULL, new_level ENUM('open','restricted') NOT NULL,
  basis VARCHAR(255) NOT NULL, requested_by INT NOT NULL, requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', reviewed_by INT NULL, reviewed_at DATETIME NULL, review_note VARCHAR(255) NULL,
  KEY(status), FOREIGN KEY(record_id) REFERENCES records(id), FOREIGN KEY(requested_by) REFERENCES users(id), FOREIGN KEY(reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 5 & 6. Scanning and repository
CREATE TABLE scan_batches(
  id INT AUTO_INCREMENT PRIMARY KEY, batch_no VARCHAR(30) NOT NULL UNIQUE, division_id INT NULL, operator_id INT NOT NULL, notes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(operator_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE digital_files(
  id BIGINT AUTO_INCREMENT PRIMARY KEY, record_id INT NOT NULL, batch_id INT NULL, version INT NOT NULL DEFAULT 1, is_current TINYINT(1) NOT NULL DEFAULT 1,
  original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(80) NOT NULL UNIQUE, mime VARCHAR(80) NOT NULL, size_bytes BIGINT NOT NULL,
  page_count INT NOT NULL DEFAULT 1, sha256 CHAR(64) NOT NULL,
  scan_flag ENUM('none','unreadable','incomplete','duplicate') NOT NULL DEFAULT 'none', replaced_reason VARCHAR(255) NULL, ocr_text MEDIUMTEXT NULL,
  uploaded_by INT NOT NULL, uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY(record_id), KEY(sha256),
  FOREIGN KEY(record_id) REFERENCES records(id), FOREIGN KEY(batch_id) REFERENCES scan_batches(id), FOREIGN KEY(uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 9. Custody and routing
CREATE TABLE custody(
  id INT AUTO_INCREMENT PRIMARY KEY, slip_no VARCHAR(30) NOT NULL UNIQUE, record_id INT NOT NULL, borrower_id INT NOT NULL, purpose VARCHAR(255) NOT NULL,
  released_by INT NOT NULL, released_at DATETIME NOT NULL, due_date DATE NOT NULL, received_by INT NULL, returned_at DATETIME NULL,
  status ENUM('out','overdue','returned') NOT NULL DEFAULT 'out', overdue_notified TINYINT(1) NOT NULL DEFAULT 0, KEY(status),
  FOREIGN KEY(record_id) REFERENCES records(id), FOREIGN KEY(borrower_id) REFERENCES users(id),
  FOREIGN KEY(released_by) REFERENCES users(id), FOREIGN KEY(received_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 10. Verification / QA
CREATE TABLE verifications(
  id INT AUTO_INCREMENT PRIMARY KEY, record_id INT NOT NULL, file_id BIGINT NOT NULL, verifier_id INT NOT NULL,
  physical_pages INT NOT NULL, digital_pages INT NOT NULL, readable_ok TINYINT(1) NOT NULL, orientation_ok TINYINT(1) NOT NULL,
  result ENUM('pass','fail') NOT NULL, remarks VARCHAR(500) NULL, verified_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(record_id) REFERENCES records(id), FOREIGN KEY(file_id) REFERENCES digital_files(id), FOREIGN KEY(verifier_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- 11. Disposition requests (never automatic)
CREATE TABLE disposition_requests(
  id INT AUTO_INCREMENT PRIMARY KEY, record_id INT NOT NULL, action ENUM('transfer_storage','transfer_nap','dispose') NOT NULL, justification VARCHAR(255) NOT NULL,
  requested_by INT NOT NULL, requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending','approved','rejected','executed') NOT NULL DEFAULT 'pending', reviewed_by INT NULL, reviewed_at DATETIME NULL,
  FOREIGN KEY(record_id) REFERENCES records(id), FOREIGN KEY(requested_by) REFERENCES users(id), FOREIGN KEY(reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- 15. Tasks and notifications
CREATE TABLE tasks(
  id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL, details VARCHAR(500) NULL, record_id INT NULL, assigned_to INT NOT NULL, division_id INT NULL,
  due_date DATE NULL, status ENUM('open','done') NOT NULL DEFAULT 'open', created_by INT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL, KEY(assigned_to,status),
  FOREIGN KEY(assigned_to) REFERENCES users(id), FOREIGN KEY(created_by) REFERENCES users(id), FOREIGN KEY(record_id) REFERENCES records(id)
) ENGINE=InnoDB;
CREATE TABLE notifications(
  id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, message VARCHAR(300) NOT NULL, link VARCHAR(200) NULL, is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY(user_id,is_read), FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- 13. Audit trail (append-only: triggers block UPDATE and DELETE)
CREATE TABLE audit_logs(
  id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, username VARCHAR(50) NULL, action VARCHAR(60) NOT NULL,
  entity VARCHAR(40) NULL, entity_id VARCHAR(40) NULL, details VARCHAR(500) NULL, ip VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY(action), KEY(user_id), KEY(created_at)
) ENGINE=InnoDB;
DELIMITER //
CREATE TRIGGER audit_no_update BEFORE UPDATE ON audit_logs FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit logs are read-only'; END//
CREATE TRIGGER audit_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit logs cannot be deleted'; END//
DELIMITER ;

-- 17. Backup log
CREATE TABLE backups(
  id INT AUTO_INCREMENT PRIMARY KEY, filename VARCHAR(120) NOT NULL, kind ENUM('database','files') NOT NULL, size_bytes BIGINT NOT NULL DEFAULT 0,
  sha256 CHAR(64) NULL, status ENUM('ok','failed') NOT NULL, note VARCHAR(255) NULL, created_by INT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_verified_at DATETIME NULL, verify_result VARCHAR(20) NULL
) ENGINE=InnoDB;
SET FOREIGN_KEY_CHECKS=1;

-- Seed data ---------------------------------------------------------------
INSERT INTO divisions(code,name) VALUES('RS','Records Section'),('LD','Legal Division'),('FD','Finance Division'),('PD','Planning Division'),('OD','Operations Division');
INSERT INTO sections(division_id,name) VALUES(1,'Receiving and Releasing'),(1,'Archives'),(2,'Case Management'),(3,'Accounting'),(3,'Budget'),(4,'Policy and Planning'),(5,'Field Operations');
INSERT INTO categories(name,prefix,default_access,naming_convention) VALUES
('Memorandum','MEMO','open','DIV-MEMO-YYYY-NNN'),('Land Title','LT','restricted','LT-PROVINCE-NNNN'),('Contract','CON','restricted','CON-YYYY-NNN'),
('Voucher','VCH','restricted','VCH-YYYY-NNNN'),('Case Folder','CASE','restricted','CASE-YYYY-NNNN'),('Report','RPT','open','RPT-DIV-YYYY-NN');
-- SAMPLE schedule only. Replace with the approved DAR Records Disposition Schedule and NAP authority numbers.
INSERT INTO retention_schedules(category_id,class_name,active_years,inactive_years,disposition,authority_ref) VALUES
(1,'Memoranda (SAMPLE)',3,2,'dispose','SAMPLE - replace with approved DAR RDS item'),
(2,'Land Titles (SAMPLE)',10,0,'permanent','SAMPLE - replace with approved DAR RDS item'),
(3,'Contracts (SAMPLE)',5,5,'transfer_nap','SAMPLE - replace with approved DAR RDS item'),
(4,'Vouchers (SAMPLE)',3,7,'dispose','SAMPLE - replace with approved DAR RDS item'),
(5,'Case Folders (SAMPLE)',5,5,'transfer_nap','SAMPLE - replace with approved DAR RDS item'),
(6,'Reports (SAMPLE)',3,2,'transfer_nap','SAMPLE - replace with approved DAR RDS item');
INSERT INTO settings(skey,svalue) VALUES('allowed_ext','pdf,png,jpg,jpeg,tif,tiff'),('max_upload_mb','50'),('loan_days','7'),
('backup_frequency','daily'),('retention_review_days','90'),('ref_format','DAR-{DIV}-{YEAR}-{SEQ}');
-- Default admin: username admin / password Admin@DAR2026 (must be changed on first login)
INSERT INTO users(username,full_name,email,password_hash,role,division_id,must_change_password) VALUES('admin','System Administrator','admin@example.gov.ph','$2y$10$HvRwDGiVz8TYcrzjQ.I35OwAPth2c8Fhksa023YFa60kB587s2SV.','admin',1,1);

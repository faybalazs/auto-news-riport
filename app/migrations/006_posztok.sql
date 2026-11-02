-- 9. lépés: LinkedIn-posztok generálása, szerkesztése és jóváhagyása.

ALTER TABLE posts
  ADD COLUMN source_kind ENUM('','cikkek','cikk','otlet') NOT NULL DEFAULT '' AFTER status,
  ADD COLUMN idea_id INT UNSIGNED NULL AFTER source_kind,
  ADD COLUMN model VARCHAR(60) NOT NULL DEFAULT '' AFTER image_data,
  ADD COLUMN change_request TEXT NULL AFTER model,
  ADD COLUMN status_at DATETIME NULL AFTER change_request;

ALTER TABLE post_ideas
  ADD COLUMN created_by INT UNSIGNED NULL AFTER note;

-- A poszt előzményei (ki, mit, mikor).
CREATE TABLE post_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  post_code VARCHAR(20) NOT NULL,
  user_id INT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  details VARCHAR(1000) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  KEY ix_pe (client_id, post_code, id),
  CONSTRAINT fk_pe_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- Generálási kérések: gombra azonnal futnak; ha nem sikerül, az óránkénti futás pótolja (legfeljebb 3 próba).
CREATE TABLE post_jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  kind ENUM('cikkek','cikk','otlet','ujra') NOT NULL,
  ref VARCHAR(40) NOT NULL,
  requested_by INT UNSIGNED NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(1000) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_job (client_id, kind, ref),
  CONSTRAINT fk_job_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

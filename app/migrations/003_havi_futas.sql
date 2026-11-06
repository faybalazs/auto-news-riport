-- 5. lépés: havi összefoglaló és az értékelés modellje.

CREATE TABLE month_reports (
  client_id INT UNSIGNED NOT NULL,
  month CHAR(4) NOT NULL,
  summary_rows TEXT NOT NULL,
  details TEXT NOT NULL,
  stats TEXT NOT NULL,
  model VARCHAR(60) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (client_id, month),
  CONSTRAINT fk_rep_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

ALTER TABLE articles ADD COLUMN evaluated_by VARCHAR(60) NOT NULL DEFAULT '' AFTER reason;

-- 3. lépés: ügyfelenkénti Google Sheet, és az ügyféladatok táblái (adatátvételhez és a későbbi lépésekhez).

ALTER TABLE clients ADD COLUMN spreadsheet_id VARCHAR(100) NOT NULL DEFAULT '' AFTER website;

CREATE TABLE sources (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL DEFAULT '',
  type VARCHAR(100) NOT NULL DEFAULT '',
  url VARCHAR(500) NOT NULL,
  url_hash CHAR(40) NOT NULL,
  topics VARCHAR(500) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  is_new TINYINT(1) NOT NULL DEFAULT 0,
  note VARCHAR(500) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_sources (client_id, url_hash),
  CONSTRAINT fk_src_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

CREATE TABLE keywords (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  keyword VARCHAR(190) NOT NULL,
  topic VARCHAR(190) NOT NULL DEFAULT '',
  is_exclude TINYINT(1) NOT NULL DEFAULT 0,
  language VARCHAR(5) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_keywords (client_id, keyword, language),
  CONSTRAINT fk_kw_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

CREATE TABLE articles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  month CHAR(4) NOT NULL,
  no SMALLINT UNSIGNED NULL,
  source_name VARCHAR(190) NOT NULL DEFAULT '',
  published_on DATE NULL,
  title VARCHAR(500) NOT NULL DEFAULT '',
  description TEXT NULL,
  url VARCHAR(1000) NOT NULL,
  url_hash CHAR(40) NOT NULL,
  recommendation VARCHAR(40) NOT NULL DEFAULT '',
  reason TEXT NULL,
  decision ENUM('','mehet','linkedin','nemkell') NOT NULL DEFAULT '',
  uploaded TINYINT(1) NOT NULL DEFAULT 0,
  upload_status VARCHAR(255) NOT NULL DEFAULT '',
  url_hu VARCHAR(1000) NOT NULL DEFAULT '',
  url_en VARCHAR(1000) NOT NULL DEFAULT '',
  post_code VARCHAR(20) NOT NULL DEFAULT '',
  from_new_source TINYINT(1) NOT NULL DEFAULT 0,
  imported TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_articles (client_id, url_hash),
  KEY ix_articles_month (client_id, month),
  CONSTRAINT fk_art_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

CREATE TABLE posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  code VARCHAR(20) NOT NULL,
  month CHAR(4) NOT NULL DEFAULT '',
  status ENUM('tervezet','ugyfelnel','jovahagyva','megjelent') NOT NULL DEFAULT 'tervezet',
  topic VARCHAR(255) NOT NULL DEFAULT '',
  source_articles TEXT NULL,
  opening VARCHAR(500) NOT NULL DEFAULT '',
  alt_opening_1 VARCHAR(500) NOT NULL DEFAULT '',
  alt_opening_2 VARCHAR(500) NOT NULL DEFAULT '',
  context TEXT NULL,
  highlights TEXT NULL,
  source_link VARCHAR(1000) NOT NULL DEFAULT '',
  xperion_view TEXT NULL,
  closing TEXT NULL,
  hashtags VARCHAR(500) NOT NULL DEFAULT '',
  image_ref VARCHAR(1000) NOT NULL DEFAULT '',
  note TEXT NULL,
  image_data TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_posts (client_id, code),
  CONSTRAINT fk_post_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

CREATE TABLE post_ideas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  code VARCHAR(20) NOT NULL,
  submitter VARCHAR(150) NOT NULL DEFAULT '',
  topic VARCHAR(255) NOT NULL DEFAULT '',
  message TEXT NULL,
  link VARCHAR(1000) NOT NULL DEFAULT '',
  type VARCHAR(40) NOT NULL DEFAULT '',
  wanted_week VARCHAR(60) NOT NULL DEFAULT '',
  status ENUM('uj','elkeszult','jovahagyva','utemezve','elvetve') NOT NULL DEFAULT 'uj',
  post_code VARCHAR(20) NOT NULL DEFAULT '',
  note TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_ideas (client_id, code),
  CONSTRAINT fk_idea_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

CREATE TABLE post_rules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  sort SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  name VARCHAR(190) NOT NULL,
  value TEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY ix_rules_client (client_id, sort),
  CONSTRAINT fk_rule_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

CREATE TABLE client_settings (
  client_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  value TEXT NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (client_id, name),
  CONSTRAINT fk_set_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

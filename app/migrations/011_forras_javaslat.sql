-- Új-forrás-figyelő: a Claude webkereséssel talált, próbagyűjtéssel ellenőrzött forrásjavaslatok.
-- A javaslat csak a felhasználó jóváhagyásával („Felvétel”) lesz figyelt forrás; az elvetett címet a figyelő többé nem javasolja.

CREATE TABLE source_suggestions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  url VARCHAR(500) NOT NULL,
  url_hash CHAR(40) NOT NULL,
  type VARCHAR(100) NOT NULL DEFAULT '',
  topics VARCHAR(500) NOT NULL DEFAULT '',
  reason TEXT NULL,
  test_method VARCHAR(100) NOT NULL DEFAULT '',
  test_count INT NOT NULL DEFAULT 0,
  test_error VARCHAR(500) NOT NULL DEFAULT '',
  sample TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'javasolt',
  created_at DATETIME NOT NULL,
  decided_at DATETIME NULL,
  decided_by INT UNSIGNED NULL,
  UNIQUE KEY uq_sugg (client_id, url_hash),
  KEY ix_sugg_status (client_id, status),
  CONSTRAINT fk_sugg_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

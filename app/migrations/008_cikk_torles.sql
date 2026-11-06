-- Cikk törlése visszaállíthatóan: a törölt cikk teljes sora ide kerül (JSON), és innen állítható vissza ugyanazzal az azonosítóval.

CREATE TABLE deleted_articles (
  id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  month CHAR(4) NOT NULL,
  data LONGTEXT NOT NULL,
  deleted_by INT UNSIGNED NULL,
  deleted_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_deleted_client (client_id, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

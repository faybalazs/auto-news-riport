-- 8. lépés: élő feltöltés az ügyfél WordPress-oldalára (Xperion OAuth Bridge, deploy_pair).
-- A cikk mellé kerül a pár kulcsa, a fordítás, a WordPress-azonosítók és a feltöltés állapota.

ALTER TABLE articles
  ADD COLUMN press_key VARCHAR(40) NOT NULL DEFAULT '' AFTER post_code,
  ADD COLUMN wp_state ENUM('','kint','duplikatum','hiba','zarolt','reszben') NOT NULL DEFAULT '' AFTER press_key,
  ADD COLUMN hu_title VARCHAR(500) NOT NULL DEFAULT '' AFTER wp_state,
  ADD COLUMN hu_summary TEXT NULL AFTER hu_title,
  ADD COLUMN en_title VARCHAR(500) NOT NULL DEFAULT '' AFTER hu_summary,
  ADD COLUMN en_summary TEXT NULL AFTER en_title,
  ADD COLUMN wp_hu_id INT UNSIGNED NULL AFTER en_summary,
  ADD COLUMN wp_en_id INT UNSIGNED NULL AFTER wp_hu_id,
  ADD COLUMN published_at DATETIME NULL AFTER wp_en_id,
  ADD COLUMN upload_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER published_at,
  ADD COLUMN upload_error VARCHAR(1000) NOT NULL DEFAULT '' AFTER upload_attempts,
  ADD KEY ix_articles_upload (client_id, decision, uploaded);

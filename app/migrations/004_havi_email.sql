-- 7. lépés: havi e-mail az ügyfélnek. Ügyfelenként és hónaponként egy levél;
-- elküldés után nem módosul. Piszkozat nem keletkezik sehol (a levél csak itt él, jóváhagyásig).

CREATE TABLE month_emails (
  client_id INT UNSIGNED NOT NULL,
  month CHAR(4) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  status ENUM('jovahagyasra_var','kuldes','elkuldve','hiba') NOT NULL DEFAULT 'jovahagyasra_var',
  model VARCHAR(60) NOT NULL DEFAULT '',
  generated_at DATETIME NOT NULL,
  edited_by INT UNSIGNED NULL,
  edited_at DATETIME NULL,
  sent_by INT UNSIGNED NULL,
  sent_at DATETIME NULL,
  sent_to VARCHAR(1000) NOT NULL DEFAULT '',
  error VARCHAR(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (client_id, month),
  CONSTRAINT fk_mail_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

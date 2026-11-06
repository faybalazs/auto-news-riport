-- Posztgenerálás: a felhasználó külön utasítása a szöveghez és a képhez (generáláskor és újrageneráláskor), a poszthoz és a függő kéréshez mentve.

ALTER TABLE posts ADD COLUMN instr_text TEXT NULL AFTER change_request, ADD COLUMN instr_image TEXT NULL AFTER instr_text;
ALTER TABLE post_jobs ADD COLUMN instr_text TEXT NULL AFTER ref_hash, ADD COLUMN instr_image TEXT NULL AFTER instr_text;

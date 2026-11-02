-- 9. lépés kiegészítése: a Claude által tervezett, posztonként egyedi kép (SVG), és a kijelölt cikkek listája a kérésben.

ALTER TABLE posts ADD COLUMN image_svg MEDIUMTEXT NULL AFTER image_data;
ALTER TABLE post_jobs ADD COLUMN ref_hash CHAR(40) NOT NULL DEFAULT '' AFTER ref;
UPDATE post_jobs SET ref_hash = SHA1(ref);
ALTER TABLE post_jobs ADD UNIQUE KEY uq_job_hash (client_id, kind, ref_hash);
ALTER TABLE post_jobs DROP INDEX uq_job;
ALTER TABLE post_jobs MODIFY ref VARCHAR(500) NOT NULL;

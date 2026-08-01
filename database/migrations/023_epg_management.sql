ALTER TABLE epg_sources ADD COLUMN last_status ENUM('never','queued','running','completed','failed') NOT NULL DEFAULT 'never' AFTER last_import_at,
 ADD COLUMN last_error VARCHAR(500) NULL AFTER last_status,ADD COLUMN last_job_id CHAR(32) NULL AFTER last_error,
 ADD FOREIGN KEY(last_job_id) REFERENCES jobs(id) ON DELETE SET NULL;

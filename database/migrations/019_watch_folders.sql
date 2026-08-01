ALTER TABLE media_remote_runs MODIFY requested_by BIGINT UNSIGNED NULL;
ALTER TABLE storage_libraries DROP INDEX mount_path,ADD INDEX idx_library_mount(mount_path(255));
CREATE TABLE IF NOT EXISTS watch_folders(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,library_id BIGINT UNSIGNED NOT NULL,content_type ENUM('movie','series') NOT NULL,
 interval_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10,active BOOLEAN NOT NULL DEFAULT TRUE,last_dispatched_at DATETIME NULL,
 last_run_id BIGINT UNSIGNED NULL,last_job_id CHAR(32) NULL,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(library_id) REFERENCES storage_libraries(id) ON DELETE CASCADE,FOREIGN KEY(last_run_id) REFERENCES media_remote_runs(id) ON DELETE SET NULL,
 FOREIGN KEY(last_job_id) REFERENCES jobs(id) ON DELETE SET NULL,FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE(library_id),INDEX(active,last_dispatched_at)
) ENGINE=InnoDB;

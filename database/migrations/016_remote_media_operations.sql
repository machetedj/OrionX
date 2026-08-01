CREATE TABLE IF NOT EXISTS media_remote_runs(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,server_id BIGINT UNSIGNED NOT NULL,library_id BIGINT UNSIGNED NOT NULL,
 operation ENUM('inventory','scan','validate','apply_links') NOT NULL,status ENUM('queued','running','completed','failed') NOT NULL DEFAULT 'queued',
 job_id CHAR(32) NULL,summary JSON NULL,error_message TEXT NULL,requested_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,started_at DATETIME NULL,completed_at DATETIME NULL,
 FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE,FOREIGN KEY(library_id) REFERENCES storage_libraries(id) ON DELETE CASCADE,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL,FOREIGN KEY(requested_by) REFERENCES users(id),INDEX(server_id,status)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS media_symlink_inventory(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,run_id BIGINT UNSIGNED NOT NULL,library_id BIGINT UNSIGNED NOT NULL,
 link_path VARCHAR(2048) NOT NULL,target_path VARCHAR(2048) NULL,valid BOOLEAN NOT NULL DEFAULT FALSE,error_message VARCHAR(500) NULL,
 applied_server_id BIGINT UNSIGNED NULL,applied_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(run_id) REFERENCES media_remote_runs(id) ON DELETE CASCADE,FOREIGN KEY(library_id) REFERENCES storage_libraries(id) ON DELETE CASCADE,
 FOREIGN KEY(applied_server_id) REFERENCES servers(id) ON DELETE SET NULL,UNIQUE(run_id,link_path),INDEX(valid,applied_at)
) ENGINE=InnoDB;

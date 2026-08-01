CREATE TABLE IF NOT EXISTS node_media_tasks(
 id CHAR(32) PRIMARY KEY,server_id BIGINT UNSIGNED NOT NULL,run_id BIGINT UNSIGNED NOT NULL,
 payload JSON NOT NULL,status ENUM('pending','claimed','completed','failed') NOT NULL DEFAULT 'pending',
 attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,error_message TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 claimed_at DATETIME NULL,completed_at DATETIME NULL,
 FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE,FOREIGN KEY(run_id) REFERENCES media_remote_runs(id) ON DELETE CASCADE,
 INDEX(server_id,status,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS xui_sql_uploads(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,connection_id BIGINT UNSIGNED NULL,original_name VARCHAR(255) NOT NULL,
 stored_path VARCHAR(500) NOT NULL,size_bytes BIGINT UNSIGNED NOT NULL,sha256 CHAR(64) NOT NULL,
 status ENUM('uploaded','queued','restoring','importing','completed','failed') NOT NULL DEFAULT 'uploaded',
 job_id CHAR(32) NULL,error_message TEXT NULL,created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,started_at DATETIME NULL,completed_at DATETIME NULL,
 FOREIGN KEY(connection_id) REFERENCES xui_source_connections(id) ON DELETE SET NULL,
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL,FOREIGN KEY(created_by) REFERENCES users(id),INDEX(status,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rtmp_ingest_configs(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,server_id BIGINT UNSIGNED NOT NULL UNIQUE,enabled BOOLEAN NOT NULL DEFAULT FALSE,port SMALLINT UNSIGNED NOT NULL DEFAULT 1935,
 application_name VARCHAR(80) NOT NULL,publish_key_ciphertext TEXT NOT NULL,desired_version INT UNSIGNED NOT NULL DEFAULT 1,applied_version INT UNSIGNED NOT NULL DEFAULT 0,
 status ENUM('pending','applying','active','failed','disabled') NOT NULL DEFAULT 'pending',last_error TEXT NULL,updated_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE,FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS rtmp_ingest_ips(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,config_id BIGINT UNSIGNED NOT NULL,ip VARCHAR(45) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(config_id) REFERENCES rtmp_ingest_configs(id) ON DELETE CASCADE,UNIQUE(config_id,ip)
) ENGINE=InnoDB;
INSERT IGNORE INTO permissions(name,slug) VALUES('Gestionar ingest RTMP','rtmp.manage');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 1,id FROM permissions WHERE slug='rtmp.manage';
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 2,id FROM permissions WHERE slug='rtmp.manage';

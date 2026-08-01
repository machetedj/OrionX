CREATE TABLE IF NOT EXISTS server_ssh_credentials(
 server_id BIGINT UNSIGNED PRIMARY KEY,ssh_host VARCHAR(255) NOT NULL,ssh_port SMALLINT UNSIGNED NOT NULL DEFAULT 22,
 ssh_user VARCHAR(64) NOT NULL,host_fingerprint VARCHAR(100) NOT NULL,private_key_ciphertext MEDIUMTEXT NULL,password_ciphertext TEXT NULL,
 created_by BIGINT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE,FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS server_deployments(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,server_id BIGINT UNSIGNED NOT NULL,type ENUM('install','sync') NOT NULL,
 status ENUM('queued','running','completed','failed') NOT NULL DEFAULT 'queued',version VARCHAR(50) NOT NULL,
 job_id CHAR(32) NULL,requested_by BIGINT UNSIGNED NOT NULL,output TEXT NULL,error_message TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,started_at TIMESTAMP NULL,completed_at TIMESTAMP NULL,
 FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE,FOREIGN KEY(requested_by) REFERENCES users(id),
 FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL,INDEX(server_id,created_at),INDEX(status)
) ENGINE=InnoDB;
INSERT IGNORE INTO permissions(name,slug) VALUES('Instalar balanceadores','servers.deploy');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 1,id FROM permissions WHERE slug='servers.deploy';
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 2,id FROM permissions WHERE slug='servers.deploy';

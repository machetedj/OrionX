CREATE TABLE IF NOT EXISTS database_ip_allowlist(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,server_id BIGINT UNSIGNED NOT NULL,ip VARCHAR(45) NOT NULL,ip_kind ENUM('public','private') NOT NULL,
 active BOOLEAN NOT NULL DEFAULT TRUE,created_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE,FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,UNIQUE(ip),INDEX(active)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS database_firewall_runs(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,status ENUM('queued','applying','completed','failed') NOT NULL DEFAULT 'queued',requested_by BIGINT UNSIGNED NULL,
 rules_count INT UNSIGNED NOT NULL DEFAULT 0,output TEXT NULL,error_message TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,started_at TIMESTAMP NULL,completed_at TIMESTAMP NULL,
 FOREIGN KEY(requested_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO permissions(name,slug) VALUES('Gestionar firewall de base de datos','database.firewall.manage');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 1,id FROM permissions WHERE slug='database.firewall.manage';

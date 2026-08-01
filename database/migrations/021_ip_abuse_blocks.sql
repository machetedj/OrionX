CREATE TABLE IF NOT EXISTS blocked_ips(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ip VARCHAR(45) NOT NULL,reason VARCHAR(255) NOT NULL,source ENUM('manual','automatic','imported') NOT NULL DEFAULT 'manual',
 event_count INT UNSIGNED NOT NULL DEFAULT 0,active BOOLEAN NOT NULL DEFAULT TRUE,expires_at DATETIME NULL,created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,UNIQUE(ip),INDEX(active,expires_at)
) ENGINE=InnoDB;
INSERT IGNORE INTO permissions(name,slug) VALUES('Gestionar bloqueo de IP','security.manage');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 1,id FROM permissions WHERE slug='security.manage';
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 2,id FROM permissions WHERE slug='security.manage';

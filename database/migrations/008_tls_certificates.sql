CREATE TABLE IF NOT EXISTS certificate_requests(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,target_type ENUM('main','balancer') NOT NULL,server_id BIGINT UNSIGNED NULL,target_key BIGINT UNSIGNED AS (IFNULL(server_id,0)) STORED,
 domain VARCHAR(253) NOT NULL,email VARCHAR(190) NOT NULL,status ENUM('pending','queued','processing','waiting_node','issued','failed','renewal_due','revoked') NOT NULL DEFAULT 'pending',
 certificate_path VARCHAR(1024) NULL,expires_at DATETIME NULL,last_error TEXT NULL,requested_by BIGINT UNSIGNED NOT NULL,
 requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,started_at TIMESTAMP NULL,completed_at TIMESTAMP NULL,last_renewal_at TIMESTAMP NULL,
 FOREIGN KEY(server_id) REFERENCES servers(id),FOREIGN KEY(requested_by) REFERENCES users(id),
 INDEX(target_type,status),INDEX(server_id,status),UNIQUE(target_type,target_key,domain)
) ENGINE=InnoDB;

INSERT IGNORE INTO permissions(name,slug) VALUES('Gestionar certificados TLS','certificates.manage');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 1,id FROM permissions WHERE slug='certificates.manage';
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 2,id FROM permissions WHERE slug='certificates.manage';

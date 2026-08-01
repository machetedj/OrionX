CREATE TABLE IF NOT EXISTS mag_devices(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 account_id BIGINT UNSIGNED NOT NULL,
 mac_hash CHAR(64) NOT NULL UNIQUE,
 mac_ciphertext TEXT NOT NULL,
 status ENUM('active','blocked') NOT NULL DEFAULT 'active',
 token_hash CHAR(64) NULL,
 token_expires_at DATETIME NULL,
 last_ip VARCHAR(45) NULL,
 last_user_agent VARCHAR(255) NULL,
 last_seen_at DATETIME NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(account_id) REFERENCES end_user_accounts(id) ON DELETE CASCADE,
 FOREIGN KEY(created_by) REFERENCES users(id),
 INDEX(account_id,status),INDEX(token_hash,token_expires_at)
) ENGINE=InnoDB;
INSERT IGNORE INTO permissions(name,slug) VALUES('Gestionar dispositivos MAG','mag.manage');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 1,id FROM permissions WHERE slug='mag.manage';
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 2,id FROM permissions WHERE slug='mag.manage';

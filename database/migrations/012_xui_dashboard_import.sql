CREATE TABLE IF NOT EXISTS xui_source_connections(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,host VARCHAR(45) NOT NULL,port SMALLINT UNSIGNED NOT NULL DEFAULT 3306,
 database_name VARCHAR(64) NOT NULL,username VARCHAR(64) NOT NULL,password_ciphertext TEXT NOT NULL,table_prefix VARCHAR(40) NOT NULL DEFAULT 'xui_legacy__',
 status ENUM('untested','ready','error') NOT NULL DEFAULT 'untested',last_tested_at DATETIME NULL,last_error VARCHAR(1000) NULL,
 created_by BIGINT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id),UNIQUE(name)
) ENGINE=InnoDB;
ALTER TABLE xui_imports ADD COLUMN connection_id BIGINT UNSIGNED NULL AFTER id,ADD COLUMN job_id CHAR(32) NULL AFTER connection_id,
 ADD FOREIGN KEY(connection_id) REFERENCES xui_source_connections(id) ON DELETE SET NULL,ADD FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE SET NULL;
INSERT IGNORE INTO permissions(name,slug) VALUES('Importar XUI One','xui.manage');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 1,id FROM permissions WHERE slug='xui.manage';
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 2,id FROM permissions WHERE slug='xui.manage';

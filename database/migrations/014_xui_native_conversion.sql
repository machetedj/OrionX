CREATE TABLE IF NOT EXISTS xui_conversion_runs(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,import_id BIGINT UNSIGNED NOT NULL,
 status ENUM('running','completed','completed_with_conflicts','failed') NOT NULL DEFAULT 'running',
 entities_created BIGINT UNSIGNED NOT NULL DEFAULT 0,entities_updated BIGINT UNSIGNED NOT NULL DEFAULT 0,
 conflicts_count BIGINT UNSIGNED NOT NULL DEFAULT 0,summary JSON NULL,error_message TEXT NULL,
 started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,completed_at DATETIME NULL,
 FOREIGN KEY(import_id) REFERENCES xui_imports(id) ON DELETE CASCADE,INDEX(import_id,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS xui_native_mappings(
 import_id BIGINT UNSIGNED NOT NULL,entity_type VARCHAR(40) NOT NULL,legacy_id VARCHAR(100) NOT NULL,
 native_id BIGINT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(import_id,entity_type,legacy_id),INDEX(entity_type,native_id),
 FOREIGN KEY(import_id) REFERENCES xui_imports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS xui_conversion_conflicts(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,run_id BIGINT UNSIGNED NOT NULL,entity_type VARCHAR(40) NOT NULL,
 legacy_id VARCHAR(100) NULL,reason VARCHAR(120) NOT NULL,details JSON NULL,
 status ENUM('pending','resolved','ignored') NOT NULL DEFAULT 'pending',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 resolved_at DATETIME NULL,FOREIGN KEY(run_id) REFERENCES xui_conversion_runs(id) ON DELETE CASCADE,
 INDEX(run_id,status),INDEX(entity_type,legacy_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS package_content_items(
 package_id BIGINT UNSIGNED NOT NULL,content_item_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(package_id,content_item_id),FOREIGN KEY(package_id) REFERENCES packages(id) ON DELETE CASCADE,
 FOREIGN KEY(content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE content_files ADD COLUMN server_id BIGINT UNSIGNED NULL AFTER library_id,
 ADD FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE SET NULL;

ALTER TABLE servers ADD COLUMN legacy_origin VARCHAR(30) NULL AFTER agent_status,
 ADD COLUMN cutover_authorized_at DATETIME NULL AFTER legacy_origin;

INSERT IGNORE INTO permissions(name,slug) VALUES('Convertir XUI a OrionX','xui.convert');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 1,id FROM permissions WHERE slug='xui.convert';

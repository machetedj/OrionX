INSERT IGNORE INTO roles(id,name,slug) VALUES
(2,'Administrador','admin'),(3,'Operador','operator'),(4,'Soporte','support'),
(5,'Revendedor','reseller'),(6,'Subrevendedor','subreseller'),(7,'Solo lectura','readonly');

INSERT IGNORE INTO permissions(name,slug) VALUES
('Crear cuentas','accounts.create'),('Editar cuentas','accounts.update'),('Eliminar cuentas','accounts.delete'),
('Ver cuentas','accounts.view'),('Ver credenciales','accounts.secrets.view'),('Desconectar sesiones','sessions.disconnect'),
('Ejecutar acciones masivas','bulk.execute'),('Importar información','data.import'),('Exportar información','data.export'),
('Gestionar paquetes','packages.manage'),('Gestionar streams','streams.manage'),('Gestionar películas','movies.manage'),
('Gestionar series','series.manage'),('Gestionar EPG','epg.manage'),('Gestionar metadatos','metadata.manage'),
('Gestionar almacenamiento','storage.manage'),('Ver logs','logs.view'),('Modificar configuración','settings.manage');

INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 1,id FROM permissions;
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 2,id FROM permissions WHERE slug NOT IN ('settings.manage','accounts.secrets.view');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 3,id FROM permissions WHERE slug IN ('accounts.view','accounts.create','accounts.update','sessions.disconnect','streams.manage','movies.manage','series.manage','epg.manage','metadata.manage');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 4,id FROM permissions WHERE slug IN ('accounts.view','accounts.update','sessions.disconnect','logs.view');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 5,id FROM permissions WHERE slug IN ('accounts.view','accounts.create','accounts.update','sessions.disconnect','data.export');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 6,id FROM permissions WHERE slug IN ('accounts.view','accounts.create','accounts.update');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT 7,id FROM permissions WHERE slug IN ('accounts.view','logs.view');

CREATE TABLE IF NOT EXISTS packages(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(150) NOT NULL,description TEXT NULL,
 duration_days INT UNSIGNED NULL,credit_cost BIGINT UNSIGNED NOT NULL DEFAULT 0,active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE(name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS end_user_accounts(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,reseller_id BIGINT UNSIGNED NULL,username VARCHAR(120) NOT NULL,
 credential_ciphertext TEXT NOT NULL,status ENUM('active','suspended','expired','blocked') NOT NULL DEFAULT 'active',
 expires_at DATETIME NULL,max_connections SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 allowed_ip VARCHAR(45) NULL,allowed_country CHAR(2) NULL,allowed_user_agent VARCHAR(255) NULL,
 notes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 deleted_at TIMESTAMP NULL,UNIQUE(username),FOREIGN KEY(reseller_id) REFERENCES resellers(id),INDEX(status,expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS account_packages(
 account_id BIGINT UNSIGNED NOT NULL,package_id BIGINT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(account_id,package_id),FOREIGN KEY(account_id) REFERENCES end_user_accounts(id) ON DELETE CASCADE,
 FOREIGN KEY(package_id) REFERENCES packages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS account_categories(
 account_id BIGINT UNSIGNED NOT NULL,category_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(account_id,category_id),FOREIGN KEY(account_id) REFERENCES end_user_accounts(id) ON DELETE CASCADE,
 FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS content_items(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,type ENUM('live','movie','series','episode') NOT NULL,
 category_id BIGINT UNSIGNED NULL,title VARCHAR(255) NOT NULL,slug VARCHAR(255) NOT NULL,
 tmdb_id BIGINT UNSIGNED NULL,metadata JSON NULL,status ENUM('draft','active','disabled','missing') NOT NULL DEFAULT 'draft',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(category_id) REFERENCES categories(id),INDEX(type,status),INDEX(tmdb_id),UNIQUE(type,slug)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS series_seasons(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,series_id BIGINT UNSIGNED NOT NULL,season_number INT UNSIGNED NOT NULL,title VARCHAR(255) NULL,
 FOREIGN KEY(series_id) REFERENCES content_items(id) ON DELETE CASCADE,UNIQUE(series_id,season_number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS series_episodes(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,season_id BIGINT UNSIGNED NOT NULL,content_item_id BIGINT UNSIGNED NOT NULL,episode_number INT UNSIGNED NOT NULL,
 FOREIGN KEY(season_id) REFERENCES series_seasons(id) ON DELETE CASCADE,FOREIGN KEY(content_item_id) REFERENCES content_items(id) ON DELETE CASCADE,
 UNIQUE(season_id,episode_number),UNIQUE(content_item_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS account_content_items(
 account_id BIGINT UNSIGNED NOT NULL,content_item_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(account_id,content_item_id),FOREIGN KEY(account_id) REFERENCES end_user_accounts(id) ON DELETE CASCADE,
 FOREIGN KEY(content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS active_sessions(
 id CHAR(36) PRIMARY KEY,account_id BIGINT UNSIGNED NOT NULL,server_id BIGINT UNSIGNED NULL,content_item_id BIGINT UNSIGNED NULL,
 ip VARCHAR(45) NOT NULL,user_agent VARCHAR(255) NULL,country CHAR(2) NULL,started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,disconnected_at TIMESTAMP NULL,disconnect_reason VARCHAR(120) NULL,
 FOREIGN KEY(account_id) REFERENCES end_user_accounts(id),FOREIGN KEY(server_id) REFERENCES servers(id),
 FOREIGN KEY(content_item_id) REFERENCES content_items(id),INDEX(account_id,disconnected_at),INDEX(server_id,last_seen_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS account_history(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_id BIGINT UNSIGNED NOT NULL,actor_user_id BIGINT UNSIGNED NULL,
 action VARCHAR(100) NOT NULL,before_data JSON NULL,after_data JSON NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(account_id) REFERENCES end_user_accounts(id) ON DELETE CASCADE,FOREIGN KEY(actor_user_id) REFERENCES users(id),INDEX(account_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS storage_libraries(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(150) NOT NULL,type ENUM('local','nfs','smb','mounted') NOT NULL,
 mount_path VARCHAR(1024) NOT NULL,content_type ENUM('mixed','movie','series') NOT NULL DEFAULT 'mixed',
 priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,min_free_bytes BIGINT UNSIGNED NOT NULL DEFAULT 10737418240,
 active BOOLEAN NOT NULL DEFAULT TRUE,last_scan_at TIMESTAMP NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE(name),UNIQUE(mount_path)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS content_files(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,content_item_id BIGINT UNSIGNED NOT NULL,library_id BIGINT UNSIGNED NOT NULL,
 relative_path VARCHAR(1536) NOT NULL,size_bytes BIGINT UNSIGNED NULL,checksum_sha256 CHAR(64) NULL,
 probe_data JSON NULL,status ENUM('pending','available','missing','invalid') NOT NULL DEFAULT 'pending',
 last_checked_at TIMESTAMP NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(content_item_id) REFERENCES content_items(id) ON DELETE CASCADE,FOREIGN KEY(library_id) REFERENCES storage_libraries(id),
 UNIQUE(library_id,relative_path),INDEX(content_item_id,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS epg_sources(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(150) NOT NULL,source_url TEXT NOT NULL,timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
 refresh_minutes INT UNSIGNED NOT NULL DEFAULT 360,active BOOLEAN NOT NULL DEFAULT TRUE,last_import_at TIMESTAMP NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS epg_programmes(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,source_id BIGINT UNSIGNED NOT NULL,external_channel_id VARCHAR(255) NOT NULL,
 starts_at DATETIME NOT NULL,ends_at DATETIME NOT NULL,title VARCHAR(255) NOT NULL,description TEXT NULL,metadata JSON NULL,
 FOREIGN KEY(source_id) REFERENCES epg_sources(id) ON DELETE CASCADE,UNIQUE(source_id,external_channel_id,starts_at),INDEX(starts_at,ends_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS server_metrics(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,server_id BIGINT UNSIGNED NOT NULL,cpu DECIMAL(5,2) NOT NULL,ram DECIMAL(5,2) NOT NULL,
 disk DECIMAL(5,2) NOT NULL,network_rx_bps BIGINT UNSIGNED NOT NULL DEFAULT 0,network_tx_bps BIGINT UNSIGNED NOT NULL DEFAULT 0,
 active_sessions INT UNSIGNED NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE,INDEX(server_id,created_at)
) ENGINE=InnoDB;

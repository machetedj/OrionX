CREATE TABLE IF NOT EXISTS xui_imports(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_fingerprint CHAR(64) NOT NULL,
    source_database VARCHAR(190) NOT NULL,
    table_prefix VARCHAR(40) NOT NULL,
    status ENUM('running','completed','failed') NOT NULL DEFAULT 'running',
    tables_total INT UNSIGNED NOT NULL DEFAULT 0,
    tables_copied INT UNSIGNED NOT NULL DEFAULT 0,
    rows_copied BIGINT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX(source_fingerprint,status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS xui_import_tables(
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_id BIGINT UNSIGNED NOT NULL,
    source_table VARCHAR(190) NOT NULL,
    destination_table VARCHAR(190) NOT NULL,
    source_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
    copied_rows BIGINT UNSIGNED NOT NULL DEFAULT 0,
    checksum_sha256 CHAR(64) NULL,
    status ENUM('pending','copying','completed','failed') NOT NULL DEFAULT 'pending',
    error_message TEXT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    UNIQUE(import_id,source_table),
    FOREIGN KEY(import_id) REFERENCES xui_imports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

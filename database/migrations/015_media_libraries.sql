ALTER TABLE storage_libraries ADD COLUMN server_id BIGINT UNSIGNED NULL AFTER content_type,
 ADD FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE SET NULL;

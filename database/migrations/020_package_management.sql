ALTER TABLE packages ADD COLUMN max_connections SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER credit_cost,
 ADD COLUMN reseller_enabled BOOLEAN NOT NULL DEFAULT TRUE AFTER max_connections,
 ADD COLUMN is_trial BOOLEAN NOT NULL DEFAULT FALSE AFTER reseller_enabled;
CREATE TABLE IF NOT EXISTS package_categories(
 package_id BIGINT UNSIGNED NOT NULL,category_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(package_id,category_id),FOREIGN KEY(package_id) REFERENCES packages(id) ON DELETE CASCADE,
 FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

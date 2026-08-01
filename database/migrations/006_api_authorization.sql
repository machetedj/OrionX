CREATE TABLE IF NOT EXISTS package_content_items(
 package_id BIGINT UNSIGNED NOT NULL,content_item_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(package_id,content_item_id),FOREIGN KEY(package_id) REFERENCES packages(id) ON DELETE CASCADE,
 FOREIGN KEY(content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS api_idempotency_keys(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,token_id BIGINT UNSIGNED NOT NULL,idempotency_key VARCHAR(100) NOT NULL,
 request_hash CHAR(64) NOT NULL,status_code SMALLINT UNSIGNED NULL,response_body JSON NULL,expires_at DATETIME NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE(token_id,idempotency_key),FOREIGN KEY(token_id) REFERENCES api_access_tokens(id) ON DELETE CASCADE,
 INDEX(expires_at)
) ENGINE=InnoDB;

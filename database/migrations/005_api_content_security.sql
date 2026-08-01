CREATE TABLE IF NOT EXISTS api_access_tokens(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,token_id CHAR(16) NOT NULL UNIQUE,token_hash CHAR(64) NOT NULL UNIQUE,
 audience ENUM('admin','reseller','device') NOT NULL,user_id BIGINT UNSIGNED NULL,reseller_id BIGINT UNSIGNED NULL,account_id BIGINT UNSIGNED NULL,
 scopes JSON NOT NULL,expires_at DATETIME NOT NULL,last_used_at TIMESTAMP NULL,revoked_at TIMESTAMP NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id),FOREIGN KEY(reseller_id) REFERENCES resellers(id),FOREIGN KEY(account_id) REFERENCES end_user_accounts(id),
 INDEX(audience,expires_at),INDEX(account_id,revoked_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS api_access_logs(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,token_id BIGINT UNSIGNED NULL,audience VARCHAR(20) NULL,method VARCHAR(10) NOT NULL,
 path VARCHAR(500) NOT NULL,status_code SMALLINT UNSIGNED NOT NULL,ip VARCHAR(45) NULL,user_agent VARCHAR(255) NULL,
 correlation_id CHAR(32) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(token_id) REFERENCES api_access_tokens(id) ON DELETE SET NULL,INDEX(created_at),INDEX(correlation_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS revoked_media_tokens(
 token_id CHAR(32) PRIMARY KEY,account_id BIGINT UNSIGNED NOT NULL,reason VARCHAR(255) NOT NULL,expires_at DATETIME NOT NULL,
 revoked_by BIGINT UNSIGNED NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(account_id) REFERENCES end_user_accounts(id),FOREIGN KEY(revoked_by) REFERENCES users(id),INDEX(expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS media_access_logs(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,token_id CHAR(32) NOT NULL,account_id BIGINT UNSIGNED NOT NULL,content_file_id BIGINT UNSIGNED NOT NULL,
 session_id CHAR(36) NOT NULL,ip VARCHAR(45) NULL,user_agent VARCHAR(255) NULL,result ENUM('allowed','denied') NOT NULL,
 reason VARCHAR(100) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(account_id) REFERENCES end_user_accounts(id),FOREIGN KEY(content_file_id) REFERENCES content_files(id),INDEX(account_id,created_at),INDEX(token_id)
) ENGINE=InnoDB;

ALTER TABLE content_files ADD COLUMN public_id CHAR(32) NULL AFTER id;
UPDATE content_files SET public_id=LOWER(HEX(RANDOM_BYTES(16))) WHERE public_id IS NULL;
ALTER TABLE content_files MODIFY public_id CHAR(32) NOT NULL,ADD UNIQUE(public_id);

ALTER TABLE servers ADD COLUMN api_key_id CHAR(16) NULL AFTER name,ADD COLUMN api_secret_ciphertext TEXT NULL AFTER api_key_id,
 ADD COLUMN allowed_ips JSON NULL AFTER api_secret_ciphertext,ADD UNIQUE(api_key_id);

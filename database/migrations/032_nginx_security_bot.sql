CREATE TABLE IF NOT EXISTS nginx_security_events(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ip VARCHAR(45) NOT NULL,event_type ENUM('scanner','flood','auth_abuse','rate_limited') NOT NULL,
 request_count INT UNSIGNED NOT NULL DEFAULT 1,sample_path VARCHAR(512) NULL,action ENUM('observed','blocked') NOT NULL DEFAULT 'observed',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(ip,created_at),INDEX(event_type,created_at),INDEX(action,created_at)
) ENGINE=InnoDB;

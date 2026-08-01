ALTER TABLE servers MODIFY status ENUM('pending','online','degraded','draining','offline','maintenance','quarantined') NOT NULL DEFAULT 'pending',
 ADD COLUMN max_capacity INT UNSIGNED NOT NULL DEFAULT 1000 AFTER region,ADD COLUMN weight SMALLINT UNSIGNED NOT NULL DEFAULT 100 AFTER max_capacity,
 ADD COLUMN priority SMALLINT UNSIGNED NOT NULL DEFAULT 100 AFTER weight,ADD COLUMN installed_version VARCHAR(50) NULL AFTER last_heartbeat_at,
 ADD COLUMN ffmpeg_status ENUM('unknown','ok','error') NOT NULL DEFAULT 'unknown' AFTER installed_version,
 ADD COLUMN nginx_status ENUM('unknown','ok','error') NOT NULL DEFAULT 'unknown' AFTER ffmpeg_status,
 ADD COLUMN redis_status ENUM('unknown','ok','error') NOT NULL DEFAULT 'unknown' AFTER nginx_status,
 ADD COLUMN agent_status ENUM('unknown','ok','error') NOT NULL DEFAULT 'unknown' AFTER redis_status,
 ADD COLUMN drain_started_at TIMESTAMP NULL AFTER agent_status,ADD COLUMN quarantined_reason VARCHAR(255) NULL AFTER drain_started_at;

ALTER TABLE server_metrics ADD COLUMN active_streams INT UNSIGNED NOT NULL DEFAULT 0 AFTER active_sessions,
 ADD COLUMN connected_users INT UNSIGNED NOT NULL DEFAULT 0 AFTER active_streams;

CREATE TABLE IF NOT EXISTS server_availability_events(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,server_id BIGINT UNSIGNED NOT NULL,previous_status VARCHAR(30) NULL,new_status VARCHAR(30) NOT NULL,
 reason VARCHAR(255) NOT NULL,metrics JSON NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE,INDEX(server_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alerts(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,type VARCHAR(80) NOT NULL,severity ENUM('info','warning','critical') NOT NULL,
 entity_type VARCHAR(80) NULL,entity_id BIGINT UNSIGNED NULL,title VARCHAR(255) NOT NULL,message TEXT NOT NULL,
 status ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 acknowledged_at TIMESTAMP NULL,resolved_at TIMESTAMP NULL,INDEX(status,severity,created_at),INDEX(entity_type,entity_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS jobs(
 id CHAR(32) PRIMARY KEY,type VARCHAR(80) NOT NULL,payload JSON NOT NULL,status ENUM('pending','reserved','running','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
 progress TINYINT UNSIGNED NOT NULL DEFAULT 0,attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
 error_message TEXT NULL,worker VARCHAR(120) NULL,priority SMALLINT NOT NULL DEFAULT 100,idempotency_key VARCHAR(190) NULL,
 available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,started_at TIMESTAMP NULL,finished_at TIMESTAMP NULL,
 UNIQUE(type,idempotency_key),INDEX(status,priority,available_at),INDEX(created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS job_events(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,job_id CHAR(32) NOT NULL,event VARCHAR(50) NOT NULL,progress TINYINT UNSIGNED NULL,
 message VARCHAR(500) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(job_id) REFERENCES jobs(id) ON DELETE CASCADE,INDEX(job_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_logs(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,category ENUM('authentication','administration','reseller','api','balancer','stream','ffmpeg','import','tmdb','database','security','job','error') NOT NULL,
 level ENUM('debug','info','notice','warning','error','critical') NOT NULL,message VARCHAR(1000) NOT NULL,context JSON NULL,
 correlation_id CHAR(32) NULL,actor_user_id BIGINT UNSIGNED NULL,ip VARCHAR(45) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL,INDEX(category,level,created_at),INDEX(correlation_id),FULLTEXT(message)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS log_retention_policies(
 category VARCHAR(30) PRIMARY KEY,retention_days SMALLINT UNSIGNED NOT NULL,syslog_enabled BOOLEAN NOT NULL DEFAULT FALSE
) ENGINE=InnoDB;
INSERT IGNORE INTO log_retention_policies(category,retention_days) VALUES
('authentication',180),('administration',365),('reseller',365),('api',90),('balancer',90),('stream',30),('ffmpeg',30),('import',90),('tmdb',30),('database',90),('security',365),('job',90),('error',180);

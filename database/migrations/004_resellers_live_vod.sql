ALTER TABLE resellers ADD COLUMN parent_reseller_id BIGINT UNSIGNED NULL AFTER user_id,
 ADD COLUMN max_accounts INT UNSIGNED NULL AFTER active,ADD COLUMN max_subresellers INT UNSIGNED NOT NULL DEFAULT 0 AFTER max_accounts,
 ADD COLUMN max_duration_days INT UNSIGNED NULL AFTER max_subresellers,ADD COLUMN max_connections SMALLINT UNSIGNED NULL AFTER max_duration_days,
 ADD COLUMN can_create_subresellers BOOLEAN NOT NULL DEFAULT FALSE AFTER max_connections,ADD COLUMN brand_name VARCHAR(150) NULL AFTER can_create_subresellers,
 ADD COLUMN brand_logo_url TEXT NULL AFTER brand_name,ADD FOREIGN KEY(parent_reseller_id) REFERENCES resellers(id);

ALTER TABLE reseller_credit_transactions ADD COLUMN ip VARCHAR(45) NULL AFTER actor_user_id;

CREATE TABLE IF NOT EXISTS reseller_packages(
 reseller_id BIGINT UNSIGNED NOT NULL,package_id BIGINT UNSIGNED NOT NULL,internal_price BIGINT UNSIGNED NOT NULL,
 max_duration_days INT UNSIGNED NULL,max_connections SMALLINT UNSIGNED NULL,active BOOLEAN NOT NULL DEFAULT TRUE,
 PRIMARY KEY(reseller_id,package_id),FOREIGN KEY(reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
 FOREIGN KEY(package_id) REFERENCES packages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS live_channels(
 content_item_id BIGINT UNSIGNED PRIMARY KEY,channel_number INT UNSIGNED NULL,logo_url TEXT NULL,country CHAR(2) NULL,language VARCHAR(12) NULL,
 epg_channel_id VARCHAR(255) NULL,delivery_mode ENUM('on_demand','always_on') NOT NULL DEFAULT 'on_demand',
 transcode_enabled BOOLEAN NOT NULL DEFAULT FALSE,ffmpeg_profile_id BIGINT UNSIGNED NULL,assigned_server_id BIGINT UNSIGNED NULL,preferred_server_id BIGINT UNSIGNED NULL,
 monitoring_enabled BOOLEAN NOT NULL DEFAULT TRUE,last_healthy_at TIMESTAMP NULL,
 FOREIGN KEY(content_item_id) REFERENCES content_items(id) ON DELETE CASCADE,FOREIGN KEY(assigned_server_id) REFERENCES servers(id),
 FOREIGN KEY(preferred_server_id) REFERENCES servers(id),UNIQUE(channel_number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stream_sources(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,channel_id BIGINT UNSIGNED NOT NULL,name VARCHAR(150) NOT NULL,source_url TEXT NOT NULL,
 priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,authorized BOOLEAN NOT NULL DEFAULT FALSE,active BOOLEAN NOT NULL DEFAULT TRUE,
 http_headers JSON NULL,user_agent VARCHAR(255) NULL,referer TEXT NULL,cookies_ciphertext TEXT NULL,timeout_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 15,
 auto_restart BOOLEAN NOT NULL DEFAULT TRUE,status ENUM('unknown','healthy','degraded','failed','disabled') NOT NULL DEFAULT 'unknown',
 consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,last_checked_at TIMESTAMP NULL,last_success_at TIMESTAMP NULL,
 FOREIGN KEY(channel_id) REFERENCES live_channels(content_item_id) ON DELETE CASCADE,INDEX(channel_id,authorized,active,priority)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stream_health_checks(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,source_id BIGINT UNSIGNED NOT NULL,available BOOLEAN NOT NULL,response_ms INT UNSIGNED NULL,
 width SMALLINT UNSIGNED NULL,height SMALLINT UNSIGNED NULL,video_codec VARCHAR(50) NULL,audio_codec VARCHAR(50) NULL,
 bitrate_kbps INT UNSIGNED NULL,fps DECIMAL(7,3) NULL,error_code VARCHAR(80) NULL,error_message VARCHAR(500) NULL,
 checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(source_id) REFERENCES stream_sources(id) ON DELETE CASCADE,INDEX(source_id,checked_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stream_failover_events(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,channel_id BIGINT UNSIGNED NOT NULL,from_source_id BIGINT UNSIGNED NULL,to_source_id BIGINT UNSIGNED NOT NULL,
 reason VARCHAR(255) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(channel_id) REFERENCES live_channels(content_item_id) ON DELETE CASCADE,
 FOREIGN KEY(from_source_id) REFERENCES stream_sources(id),FOREIGN KEY(to_source_id) REFERENCES stream_sources(id),INDEX(channel_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movies(
 content_item_id BIGINT UNSIGNED PRIMARY KEY,original_title VARCHAR(255) NULL,description TEXT NULL,release_year SMALLINT UNSIGNED NULL,
 duration_seconds INT UNSIGNED NULL,genres JSON NULL,cast_members JSON NULL,director VARCHAR(255) NULL,rating VARCHAR(30) NULL,
 language VARCHAR(12) NULL,country CHAR(2) NULL,poster_url TEXT NULL,backdrop_url TEXT NULL,trailer_url TEXT NULL,
 published_at TIMESTAMP NULL,FOREIGN KEY(content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS media_tracks(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,content_file_id BIGINT UNSIGNED NOT NULL,type ENUM('video','audio','subtitle') NOT NULL,
 stream_index SMALLINT UNSIGNED NOT NULL,codec VARCHAR(50) NULL,language VARCHAR(12) NULL,title VARCHAR(255) NULL,is_default BOOLEAN NOT NULL DEFAULT FALSE,
 metadata JSON NULL,FOREIGN KEY(content_file_id) REFERENCES content_files(id) ON DELETE CASCADE,UNIQUE(content_file_id,stream_index)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS media_scan_jobs(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,library_id BIGINT UNSIGNED NOT NULL,type ENUM('movie','series') NOT NULL,
 status ENUM('pending','running','completed','failed','cancelled') NOT NULL DEFAULT 'pending',files_seen INT UNSIGNED NOT NULL DEFAULT 0,
 files_added INT UNSIGNED NOT NULL DEFAULT 0,conflicts INT UNSIGNED NOT NULL DEFAULT 0,error_message TEXT NULL,
 started_at TIMESTAMP NULL,completed_at TIMESTAMP NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(library_id) REFERENCES storage_libraries(id),INDEX(status,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS media_import_conflicts(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,scan_job_id BIGINT UNSIGNED NOT NULL,relative_path VARCHAR(1536) NOT NULL,
 reason VARCHAR(100) NOT NULL,parsed_data JSON NULL,candidates JSON NULL,status ENUM('pending','resolved','ignored') NOT NULL DEFAULT 'pending',
 resolution JSON NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,resolved_at TIMESTAMP NULL,
 FOREIGN KEY(scan_job_id) REFERENCES media_scan_jobs(id) ON DELETE CASCADE,INDEX(status,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tmdb_matches(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,content_item_id BIGINT UNSIGNED NULL,conflict_id BIGINT UNSIGNED NULL,tmdb_id BIGINT UNSIGNED NOT NULL,
 media_type ENUM('movie','tv','episode') NOT NULL,confidence DECIMAL(5,2) NULL,payload JSON NOT NULL,
 status ENUM('candidate','confirmed','rejected') NOT NULL DEFAULT 'candidate',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(content_item_id) REFERENCES content_items(id) ON DELETE CASCADE,FOREIGN KEY(conflict_id) REFERENCES media_import_conflicts(id) ON DELETE CASCADE,
 INDEX(content_item_id,status),INDEX(conflict_id,status)
) ENGINE=InnoDB;

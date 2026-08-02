ALTER TABLE media_remote_runs MODIFY operation ENUM('inventory','scan','validate','apply_links','publish') NOT NULL;

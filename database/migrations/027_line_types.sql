ALTER TABLE end_user_accounts
 ADD COLUMN is_trial BOOLEAN NOT NULL DEFAULT FALSE AFTER max_connections,
 ADD COLUMN is_restreamer BOOLEAN NOT NULL DEFAULT FALSE AFTER is_trial,
 ADD INDEX idx_account_line_type(is_trial,is_restreamer);

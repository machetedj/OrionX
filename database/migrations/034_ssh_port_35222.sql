ALTER TABLE server_ssh_credentials ALTER COLUMN ssh_port SET DEFAULT 35222;
UPDATE server_ssh_credentials SET ssh_port=35222 WHERE ssh_port=22;

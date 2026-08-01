ALTER TABLE server_deployments
 MODIFY type ENUM('install','sync','update') NOT NULL;

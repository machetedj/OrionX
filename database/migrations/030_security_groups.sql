ALTER TABLE roles
 ADD COLUMN is_admin BOOLEAN NOT NULL DEFAULT FALSE AFTER slug,
 ADD COLUMN is_reseller BOOLEAN NOT NULL DEFAULT FALSE AFTER is_admin,
 ADD COLUMN allows_subresellers BOOLEAN NOT NULL DEFAULT FALSE AFTER is_reseller,
 ADD COLUMN system_locked BOOLEAN NOT NULL DEFAULT FALSE AFTER allows_subresellers,
 ADD COLUMN description VARCHAR(255) NULL AFTER system_locked;
UPDATE roles SET is_admin=1 WHERE slug IN ('superadmin','admin','operator','support','readonly');
UPDATE roles SET is_reseller=1 WHERE slug IN ('reseller','subreseller');
UPDATE roles SET allows_subresellers=1 WHERE slug='reseller';
UPDATE roles SET system_locked=1 WHERE slug='superadmin';
INSERT IGNORE INTO permissions(name,slug) VALUES('Gestionar grupos de seguridad','groups.manage');
INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT id,(SELECT id FROM permissions WHERE slug='groups.manage') FROM roles WHERE slug='superadmin';

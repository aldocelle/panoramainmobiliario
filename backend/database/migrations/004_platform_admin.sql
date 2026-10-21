CREATE TABLE platform_admins (id VARCHAR(36) PRIMARY KEY, email VARCHAR(254) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL, password_hash VARCHAR(255) NOT NULL, active INTEGER NOT NULL, created_at VARCHAR(32) NOT NULL);
CREATE TABLE platform_sessions (token_hash VARCHAR(64) PRIMARY KEY, admin_id VARCHAR(36) NOT NULL, csrf_token VARCHAR(80) NOT NULL, expires_at BIGINT NOT NULL, created_at VARCHAR(32) NOT NULL, FOREIGN KEY(admin_id) REFERENCES platform_admins(id));
ALTER TABLE administrative_changes ADD COLUMN actor VARCHAR(160);

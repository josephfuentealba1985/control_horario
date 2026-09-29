<?php
declare(strict_types=1);
return [
"ALTER TABLE ch_users MODIFY role ENUM('admin','analyst','user','auditor') NOT NULL DEFAULT 'user'",
"CREATE TABLE IF NOT EXISTS ch_request_context (audit_id BIGINT UNSIGNED PRIMARY KEY, ip_address VARCHAR(45) NULL, user_agent VARCHAR(1024) NOT NULL DEFAULT '', platform VARCHAR(128) NOT NULL DEFAULT '', mobile VARCHAR(16) NOT NULL DEFAULT '', CONSTRAINT context_audit FOREIGN KEY(audit_id) REFERENCES ch_audit(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"INSERT IGNORE INTO ch_schema_versions(version,applied_at) VALUES(3,UTC_TIMESTAMP())"
];

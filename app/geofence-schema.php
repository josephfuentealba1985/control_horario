<?php
declare(strict_types=1);
return [
"CREATE TABLE IF NOT EXISTS ch_geofences (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, polygon_json TEXT NOT NULL, max_accuracy INT NOT NULL DEFAULT 100, archived TINYINT NOT NULL DEFAULT 0, revision INT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"CREATE TABLE IF NOT EXISTS ch_user_geofences (user_id INT UNSIGNED NOT NULL, fence_id INT UNSIGNED NOT NULL, PRIMARY KEY(user_id,fence_id), CONSTRAINT user_fence_user FOREIGN KEY(user_id) REFERENCES ch_users(id), CONSTRAINT user_fence_fence FOREIGN KEY(fence_id) REFERENCES ch_geofences(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
"INSERT IGNORE INTO ch_schema_versions(version,applied_at) VALUES(4,UTC_TIMESTAMP())"
];

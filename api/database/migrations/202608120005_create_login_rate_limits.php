<?php

declare(strict_types=1);

return static function (PDO $connection): void {
    $connection->exec(
        <<<'SQL'
        CREATE TABLE login_rate_limits (
            key_hash CHAR(64)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            scope VARCHAR(20)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL,

            failure_count SMALLINT UNSIGNED NOT NULL,
            window_started_at DATETIME NOT NULL,
            blocked_until DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (key_hash),
            KEY idx_login_rate_limits_blocked_until (blocked_until),
            KEY idx_login_rate_limits_window (window_started_at),

            CONSTRAINT chk_login_rate_limits_scope
                CHECK (scope IN ('IP', 'CREDENTIAL'))
        ) ENGINE=InnoDB
          DEFAULT CHARACTER SET utf8mb4
          COLLATE utf8mb4_unicode_ci
        SQL
    );
};

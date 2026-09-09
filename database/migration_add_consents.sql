-- =============================================================
--  Migration: add consent tracking (first-time authorize screen +
--  revoke access from the dashboard).
--
--  If you already imported schema.sql once, run just this file
--  against your existing database instead of re-importing everything:
--
--    mysql -u your_db_user -p your_db_name < database/migration_add_consents.sql
--
--  Safe to run even if you're not sure. CREATE TABLE IF NOT EXISTS
--  won't touch anything if the table's already there.
-- =============================================================

CREATE TABLE IF NOT EXISTS user_consents (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    client_id       VARCHAR(64)  NOT NULL,
    granted_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_client (user_id, client_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

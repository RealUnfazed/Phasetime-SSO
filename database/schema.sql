-- =============================================================
--  SSO System: Database Schema
--  Import with: mysql -u root -p < schema.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS phasetime_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE phasetime_db;

-- ---------------------------------------------------------------
-- Users: registered ONCE, shared across every client application
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)  NOT NULL,
    email           VARCHAR(150)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255)  NOT NULL,
    is_admin        TINYINT(1)    NOT NULL DEFAULT 0,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    failed_logins   TINYINT       NOT NULL DEFAULT 0,
    locked_until    DATETIME      NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Clients: every mini-project that wants to use the SSO registers
-- here and receives a client_id / client_secret pair.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id       VARCHAR(64)   NOT NULL UNIQUE,
    client_secret   VARCHAR(128)  NOT NULL,
    name            VARCHAR(150)  NOT NULL,
    redirect_uri    VARCHAR(255)  NOT NULL,
    logout_uri      VARCHAR(255)  NULL,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Auth codes: short-lived, single-use codes issued at /authorize
-- and redeemed at /token by the client's backend.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auth_codes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(128)  NOT NULL UNIQUE,
    user_id         INT UNSIGNED  NOT NULL,
    client_id       VARCHAR(64)   NOT NULL,
    redirect_uri    VARCHAR(255)  NOT NULL,
    expires_at      DATETIME      NOT NULL,
    used            TINYINT(1)    NOT NULL DEFAULT 0,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Access / refresh tokens: issued after a code is redeemed.
-- The client uses the access token as a Bearer token at /userinfo.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS access_tokens (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    access_token        VARCHAR(128) NOT NULL UNIQUE,
    refresh_token       VARCHAR(128) NULL UNIQUE,
    user_id             INT UNSIGNED NOT NULL,
    client_id           VARCHAR(64)  NOT NULL,
    expires_at          DATETIME     NOT NULL,
    refresh_expires_at  DATETIME     NULL,
    revoked             TINYINT(1)   NOT NULL DEFAULT 0,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- SSO sessions: the central "you are logged in" cookie session,
-- scoped to the SSO server's own domain only. This is what makes
-- hopping between mini-projects skip the login form.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sso_sessions (
    id              VARCHAR(128) PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    ip_address      VARCHAR(45)  NULL,
    user_agent      VARCHAR(255) NULL,
    expires_at      DATETIME     NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Consents: records that a user explicitly approved a given client
-- app seeing their profile. Checked on every /authorize visit so a
-- new client always gets a consent screen once, and deleting a row
-- here is what "revoke access" in the dashboard actually does.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_consents (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    client_id       VARCHAR(64)  NOT NULL,
    granted_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_client (user_id, client_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- No seed admin account is created here on purpose. A hardcoded
-- password in a SQL file tends to end up in production. Instead,
-- run `php bin/create_admin.php` once after import to create the
-- first admin account interactively (see README.md).

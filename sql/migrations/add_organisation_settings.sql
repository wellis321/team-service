-- Migration: add organisation_settings table
-- Run on any existing deployment to enable per-org integration settings.

CREATE TABLE IF NOT EXISTS organisation_settings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT UNSIGNED NOT NULL,
    setting_key     VARCHAR(255) NOT NULL,
    setting_value   TEXT         NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_org_setting (organisation_id, setting_key),
    INDEX idx_org (organisation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

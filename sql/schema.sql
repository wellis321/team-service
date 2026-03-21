-- ─────────────────────────────────────────────────────────────────────────────
-- Team Service Database Schema
-- UK English spelling throughout
--
-- Core concept:
--   A TEAM is the fundamental operational unit.
--   Teams contain MEMBERS of two kinds:
--     • staff       — employees from the Staff Service (PMS)
--     • person      — people supported from the People Service
--   Staff can also belong to functional teams (HR, Finance, IT etc.)
--   that contain no people supported.
--
-- Multi-tenancy: every table is scoped to organisation_id.
-- Cross-service references use (member_type, service, external_id) — no
-- foreign keys across service boundaries, just agreed identifiers.
-- ─────────────────────────────────────────────────────────────────────────────

-- ── Shared-auth tables ────────────────────────────────────────────────────────
-- The Team Service has its own copy of these tables so it is fully self-
-- contained (no cross-database joins).  Users log in here independently.
-- organisation_id on the team-specific tables below is a plain indexed INT —
-- no FK to organisations because it refers to this same table and we want
-- to keep the constraint straightforward.

CREATE TABLE IF NOT EXISTS organisations (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(255) NOT NULL,
    domain           VARCHAR(255) NOT NULL UNIQUE,
    seats_allocated  INT          NOT NULL DEFAULT 0,
    seats_used       INT          NOT NULL DEFAULT 0,
    person_singular  VARCHAR(100)          DEFAULT 'person',
    person_plural    VARCHAR(100)          DEFAULT 'people',
    created_at       TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP             DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_domain (domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id                            INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id               INT          NULL,
    email                         VARCHAR(255) NOT NULL,
    password_hash                 VARCHAR(255) NOT NULL,
    first_name                    VARCHAR(100) NOT NULL,
    last_name                     VARCHAR(100) NOT NULL,
    is_active                     BOOLEAN      DEFAULT TRUE,
    email_verified                BOOLEAN      DEFAULT FALSE,
    verification_token            VARCHAR(255) NULL,
    verification_token_expires_at TIMESTAMP    NULL,
    created_at                    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at                    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login                    TIMESTAMP    NULL,
    FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_email (email),
    INDEX idx_organisation      (organisation_id),
    INDEX idx_email             (email),
    INDEX idx_verification_token (verification_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    role_id     INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT NULL,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id)     REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_role (user_id, role_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default roles
INSERT IGNORE INTO roles (name, description) VALUES
('superadmin',        'Super administrator with full system access'),
('organisation_admin','Organisation administrator'),
('staff',             'Standard staff member');

-- ── Note on organisation_id in team tables ────────────────────────────────────
-- organisation_id below is a plain indexed INT referencing organisations.id in
-- this same database.  No explicit FK is declared to keep the schema simple.

-- ── team_types ────────────────────────────────────────────────────────────────
-- Custom categories for teams (e.g. Care Team, Support Team, HR, Finance)
-- Each organisation defines its own types.
CREATE TABLE IF NOT EXISTS team_types (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT          NOT NULL,
    name            VARCHAR(100) NOT NULL,
    description     TEXT         NULL,
    is_staff_only   BOOLEAN      NOT NULL DEFAULT FALSE
                    COMMENT 'TRUE = this type can only contain staff (e.g. HR, Finance)',
    display_order   INT          NOT NULL DEFAULT 0,
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE  KEY unique_org_type_name (organisation_id, name),
    INDEX idx_organisation (organisation_id),
    INDEX idx_active       (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── teams ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS teams (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT          NOT NULL,
    parent_team_id  INT          NULL COMMENT 'Self-reference for hierarchy (e.g. Region > Area > Team)',
    team_type_id    INT          NULL,
    name            VARCHAR(255) NOT NULL,
    description     TEXT         NULL,
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_team_id)  REFERENCES teams(id)          ON DELETE SET NULL,
    FOREIGN KEY (team_type_id)    REFERENCES team_types(id)     ON DELETE SET NULL,
    INDEX idx_organisation (organisation_id),
    INDEX idx_parent       (parent_team_id),
    INDEX idx_type         (team_type_id),
    INDEX idx_active       (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── team_roles ────────────────────────────────────────────────────────────────
-- Per-organisation roles that members can hold within a team.
-- access_level controls what contracts/data the member can reach:
--   'team'         — their own team + child teams only
--   'organisation' — all teams in the organisation
CREATE TABLE IF NOT EXISTS team_roles (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT          NOT NULL,
    name            VARCHAR(100) NOT NULL,
    description     TEXT         NULL,
    applies_to      ENUM('staff','person','both') NOT NULL DEFAULT 'staff'
                    COMMENT 'Which member type this role applies to',
    access_level    ENUM('team','organisation')   NOT NULL DEFAULT 'team',
    display_order   INT          NOT NULL DEFAULT 0,
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE  KEY unique_org_role_name (organisation_id, name),
    INDEX idx_organisation (organisation_id),
    INDEX idx_active       (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── team_members ──────────────────────────────────────────────────────────────
-- Unified membership table — holds both staff and people supported.
--
-- Cross-service identity pattern:
--   member_type = 'staff'  → external_id = PMS people.id
--   member_type = 'person' → external_id = People Service people.id
--   member_type = 'user'   → external_id = shared-auth users.id
--                            (for non-staff system users like external coordinators)
--
-- display_name / display_ref are cached from the source service so the
-- Team Service UI can show names without calling back to PMS on every page.
-- They are refreshed by the source service via the API when a name changes.
CREATE TABLE IF NOT EXISTS team_members (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    team_id         INT          NOT NULL,
    organisation_id INT          NOT NULL,
    member_type     ENUM('staff','person','user') NOT NULL
                    COMMENT 'staff=PMS, person=People Service, user=shared-auth user',
    external_id     INT          NOT NULL
                    COMMENT 'Primary key in the source service',
    display_name    VARCHAR(255) NULL
                    COMMENT 'Cached full name from source service',
    display_ref     VARCHAR(100) NULL
                    COMMENT 'Cached reference/ID from source service (employee ref, CHI, etc.)',
    team_role_id    INT          NULL
                    COMMENT 'Role within this team (NULL = basic member)',
    is_primary_team BOOLEAN      NOT NULL DEFAULT FALSE
                    COMMENT 'Whether this is their primary/base team',
    joined_at       DATE         NULL     COMMENT 'Date they joined this team',
    left_at         DATE         NULL     COMMENT 'Date they left — NULL means still active',
    notes           TEXT         NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id)      REFERENCES teams(id)      ON DELETE CASCADE,
    FOREIGN KEY (team_role_id) REFERENCES team_roles(id) ON DELETE SET NULL,
    -- A member can only appear once per team
    UNIQUE  KEY unique_team_member (team_id, member_type, external_id),
    INDEX idx_team         (team_id),
    INDEX idx_organisation (organisation_id),
    INDEX idx_member       (member_type, external_id),
    INDEX idx_active       (left_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── api_keys ──────────────────────────────────────────────────────────────────
-- Allows PMS, Contracts, and People Service to authenticate against the
-- Team Service REST API.
CREATE TABLE IF NOT EXISTS api_keys (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT          NULL     COMMENT 'NULL = system-wide key (for service-to-service)',
    name            VARCHAR(100) NOT NULL,
    connected_service VARCHAR(100) NULL   COMMENT 'Which service this key belongs to (e.g. Staff Service)',
    key_hash        VARCHAR(255) NOT NULL COMMENT 'SHA-256 hash of the actual key',
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    last_used_at    TIMESTAMP    NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hash         (key_hash),
    INDEX idx_organisation (organisation_id),
    INDEX idx_active       (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Default roles seeded when an organisation is created ─────────────────────
-- (Applied per-org by TeamRole::initializeDefaults($organisationId))
-- Not inserted here as they are org-scoped — see the model method.

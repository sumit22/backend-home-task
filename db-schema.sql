-- Minimal MySQL 8+ schema (vendor-agnostic) for the SCA rule-engine workflow.
-- Uses CHAR(36) UUIDs via UUID(), JSON columns, InnoDB, utf8mb4.
-- This is the minimal set of tables required for the flow you described:
-- Provider, Repository (+ NotificationSetting), RepositoryScan, FilesInScan,
-- Integration (polymorphic external mapping), ScanResult, FileScanResult,
-- Vulnerability (contains package fields), Rule, RuleAction, ActionExecution.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

--------------------------------------------------------------------------------
-- Provider: external provider (e.g., debricked, snyk). Keep provider meta here.
--------------------------------------------------------------------------------
CREATE TABLE provider (
  id CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
  code VARCHAR(128) NOT NULL UNIQUE COMMENT 'short code e.g. sca_debricked',
  name VARCHAR(255) NOT NULL,
  config JSON DEFAULT NULL COMMENT 'provider-specific config (do NOT store plaintext secrets here)',
  created_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6)),
  updated_at DATETIME(6) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='External providers (SCA, license scanners, etc.)';

--------------------------------------------------------------------------------
-- Repository: canonical repository record (user-entered)
--------------------------------------------------------------------------------
CREATE TABLE repository (
  id CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
  name VARCHAR(512) NOT NULL,
  url VARCHAR(2048) DEFAULT NULL,
  default_branch VARCHAR(128) DEFAULT NULL,
  settings JSON DEFAULT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6)),
  updated_at DATETIME(6) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Repository metadata (the source repo to scan)';
CREATE INDEX idx_repository_name ON repository(name);

--------------------------------------------------------------------------------
-- NotificationSetting: per-repo notification destinations (emails/slack/webhooks)
--------------------------------------------------------------------------------
CREATE TABLE notification_setting (
  id CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
  repository_id CHAR(36) NOT NULL,
  emails JSON DEFAULT NULL COMMENT 'array of email addresses',
  slack_channels JSON DEFAULT NULL COMMENT 'array of slack channel identifiers',
  webhooks JSON DEFAULT NULL COMMENT 'array of webhook configs (url + headers)',
  created_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6)),
  updated_at DATETIME(6) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_notification_repo FOREIGN KEY (repository_id) REFERENCES repository(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Notification destinations for a repository';

--------------------------------------------------------------------------------
-- RepositoryScan: scan session (owns files_in_scan, scan_result, vulnerabilities)
--------------------------------------------------------------------------------
CREATE TABLE repository_scan (
  id CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
  repository_id CHAR(36) NOT NULL,
  branch VARCHAR(128) DEFAULT NULL,
  requested_by VARCHAR(256) DEFAULT NULL,
  provider_selection VARCHAR(128) DEFAULT NULL COMMENT 'provider.code chosen for this scan',
  status VARCHAR(64) NOT NULL DEFAULT 'pending' COMMENT 'pending|queued|running|completed|failed',
  scan_type VARCHAR(64) DEFAULT NULL COMMENT 'manual|ci|scheduled',
  scanner_version VARCHAR(128) DEFAULT NULL,
  vulnerability_count INT NOT NULL DEFAULT 0,
  raw_summary JSON DEFAULT NULL COMMENT 'small cached summary for quick rule checks',
  created_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6)),
  started_at DATETIME(6) NULL DEFAULT NULL,
  completed_at DATETIME(6) NULL DEFAULT NULL,
  updated_at DATETIME(6) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_scan_repo FOREIGN KEY (repository_id) REFERENCES repository(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Scan session for a repository (branch + provider selection)';
CREATE INDEX idx_repository_scan_repo ON repository_scan(repository_id);
CREATE INDEX idx_repository_scan_status ON repository_scan(status);
CREATE INDEX idx_repository_scan_provider ON repository_scan(provider_selection);

--------------------------------------------------------------------------------
-- FilesInScan: files uploaded/attached for a specific repository_scan
--------------------------------------------------------------------------------
CREATE TABLE files_in_scan (
  id CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
  repository_scan_id CHAR(36) NOT NULL,
  file_name VARCHAR(1024) NOT NULL,
  file_path VARCHAR(4096) NOT NULL,
  size BIGINT NOT NULL DEFAULT 0,
  mime_type VARCHAR(255) DEFAULT NULL,
  content_hash VARCHAR(128) DEFAULT NULL,
  status VARCHAR(64) NOT NULL DEFAULT 'stored' COMMENT 'stored|uploaded|forwarded|failed',
  created_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6)),
  updated_at DATETIME(6) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_files_scan FOREIGN KEY (repository_scan_id) REFERENCES repository_scan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Files attached to a RepositoryScan (dependency files, SBOMs)';
CREATE INDEX idx_files_in_scan_scan ON files_in_scan(repository_scan_id);
CREATE INDEX idx_files_in_scan_hash ON files_in_scan(content_hash);

--------------------------------------------------------------------------------
-- Integration: generic mapping of canonical entity -> external provider record
-- Polymorphic: linked_entity_type + linked_entity_id
--------------------------------------------------------------------------------
CREATE TABLE integration (
  id CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
  provider_id CHAR(36) NOT NULL,
  external_id VARCHAR(1024) NOT NULL COMMENT 'external id returned by provider',
  type VARCHAR(64) NOT NULL COMMENT 'scan|file|vulnerability|remediation|webhook',
  linked_entity_type VARCHAR(128) DEFAULT NULL COMMENT 'internal entity name, e.g., RepositoryScan',
  linked_entity_id CHAR(36) DEFAULT NULL,
  status VARCHAR(64) DEFAULT NULL,
  raw_payload JSON DEFAULT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6)),
  updated_at DATETIME(6) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_integration_provider FOREIGN KEY (provider_id) REFERENCES provider(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Polymorphic mapping of internal entities to external provider records (no provider-specific columns)';
CREATE INDEX idx_integration_provider ON integration(provider_id);
CREATE INDEX idx_integration_linked ON integration(linked_entity_type, linked_entity_id);
CREATE INDEX idx_integration_type_external ON integration(type, external_id);

--------------------------------------------------------------------------------
-- ScanResult: aggregated/normalized scan result for a RepositoryScan (one-to-one)
--------------------------------------------------------------------------------
CREATE TABLE scan_result (
  id CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
  repository_scan_id CHAR(36) NOT NULL UNIQUE,
  summary_json JSON DEFAULT NULL,
  status VARCHAR(64) NOT NULL DEFAULT 'unknown',
  vulnerability_count INT NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6)),
  updated_at DATETIME(6) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_scanresult_scan FOREIGN KEY (repository_scan_id) REFERENCES repository_scan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Aggregated/normalized scan result for a RepositoryScan';

--------------------------------------------------------------------------------
-- FileScanResult: per-file detailed result linked to files_in_scan and scan_result
--------------------------------------------------------------------------------
CREATE TABLE file_scan_result (
  id CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
  file_id CHAR(36) NOT NULL,
  scan_result_id CHAR(36) NOT NULL,
  raw_payload JSON DEFAULT NULL,
  status VARCHAR(64) NOT NULL DEFAULT 'unknown',
  created_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6)),
  updated_at DATETIME(6) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_fileresult_file FOREIGN KEY (file_id) REFERENCES files_in_scan(id) ON DELETE CASCADE,
  CONSTRAINT fk_fileresult_scanresult FOREIGN KEY (scan_result_id) REFERENCES scan_result(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Per-file scan payloads and status';
CREATE INDEX idx_file_scan_result_file ON file_scan_result(file_id);
CREATE INDEX idx_file_scan_result_scan ON file_scan_result(scan_result_id);

--------------------------------------------------------------------------------
-- Vulnerability: canonical vulnerability records (package fields inline)
--------------------------------------------------------------------------------
CREATE TABLE vulnerability (
  id CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
  scan_id CHAR(36) NOT NULL,
  title VARCHAR(1024) NOT NULL,
  cve VARCHAR(128) DEFAULT NULL,
  severity VARCHAR(64) DEFAULT NULL COMMENT 'normalized: info|low|medium|high|critical',
  score DECIMAL(5,2) DEFAULT NULL,
  fixed_in VARCHAR(256) DEFAULT NULL,
  references JSON DEFAULT NULL,
  ignored BOOLEAN NOT NULL DEFAULT FALSE,
  package_name VARCHAR(1024) DEFAULT NULL,
  package_version VARCHAR(256) DEFAULT NULL,
  ecosystem VARCHAR(128) DEFAULT NULL,
  package_metadata JSON DEFAULT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6)),
  updated_at DATETIME(6) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_vuln_scan FOREIGN KEY (scan_id) REFERENCES repository_scan(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Canonical vulnerability entries (package fields stored directly for simplicity)';
CREATE INDEX idx_vulnerability_scan ON vulnerability(scan_id);
CREATE INDEX idx_vulnerability_cve ON vulnerability(cve);
CREATE INDEX idx_vulnerability_package ON vulnerability(package_name, package_version);

--------------------------------------------------------------------------------
-- Rule: rule definitions (scoped or global)
--------------------------------------------------------------------------------
CREATE TABLE rule (
  id CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
  name VARCHAR(512) NOT NULL,
  enabled BOOLEAN NOT NULL DEFAULT TRUE,
  trigger_type VARCHAR(128) NOT NULL COMMENT 'scan_completed|vulnerability_created|scheduled',
  trigger_payload JSON DEFAULT NULL,
  scope VARCHAR(128) DEFAULT NULL COMMENT 'e.g., global or repository:<uuid>',
  auto_remediation BOOLEAN NOT NULL DEFAULT FALSE,
  remediation_config JSON DEFAULT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6)),
  updated_at DATETIME(6) NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Rule definitions for evaluating scan results';

--------------------------------------------------------------------------------
-- RuleAction: actions executed when a rule matches
--------------------------------------------------------------------------------
CREATE TABLE rule_action (
  id CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
  rule_id CHAR(36) NOT NULL,
  action_type VARCHAR(64) NOT NULL COMMENT 'email|slack|webhook|create_pr|post_event',
  action_payload JSON DEFAULT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6)),
  CONSTRAINT fk_ruleaction_rule FOREIGN KEY (rule_id) REFERENCES rule(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Actions tied to rules';
CREATE INDEX idx_rule_action_rule ON rule_action(rule_id);

--------------------------------------------------------------------------------
-- ActionExecution: lightweight audit log for each executed RuleAction
--------------------------------------------------------------------------------
CREATE TABLE action_execution (
  id CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
  rule_id CHAR(36) DEFAULT NULL,
  rule_action_id CHAR(36) DEFAULT NULL,
  scan_id CHAR(36) DEFAULT NULL,
  vulnerability_id CHAR(36) DEFAULT NULL,
  status VARCHAR(64) NOT NULL DEFAULT 'pending' COMMENT 'pending|sent|failed|skipped',
  result_payload JSON DEFAULT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6)),
  finished_at DATETIME(6) NULL DEFAULT NULL,
  CONSTRAINT fk_actionexecution_rule FOREIGN KEY (rule_id) REFERENCES rule(id) ON DELETE SET NULL,
  CONSTRAINT fk_actionexecution_ruleaction FOREIGN KEY (rule_action_id) REFERENCES rule_action(id) ON DELETE SET NULL,
  CONSTRAINT fk_actionexecution_scan FOREIGN KEY (scan_id) REFERENCES repository_scan(id) ON DELETE SET NULL,
  CONSTRAINT fk_actionexecution_vuln FOREIGN KEY (vulnerability_id) REFERENCES vulnerability(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Execution records for rule actions';
CREATE INDEX idx_action_execution_scan ON action_execution(scan_id);
CREATE INDEX idx_action_execution_rule ON action_execution(rule_id);

--------------------------------------------------------------------------------
-- ApiCredential: store provider credential references (prefer vault refs)
-- Minimal form: pointer or encrypted payload. If you prefer to keep credentials
-- outside DB, you can omit this table and use provider.config to store ref.
--------------------------------------------------------------------------------
CREATE TABLE api_credential (
  id CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
  provider_id CHAR(36) NOT NULL,
  credential_data JSON NOT NULL COMMENT 'encrypted credential or secret-manager reference',
  last_rotated_at DATETIME(6) DEFAULT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT (CURRENT_TIMESTAMP(6)),
  CONSTRAINT fk_apicred_provider FOREIGN KEY (provider_id) REFERENCES provider(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='API credentials (store pointers or encrypted values, prefer secret manager)';
CREATE INDEX idx_api_credential_provider ON api_credential(provider_id);

--------------------------------------------------------------------------------
-- Finish
--------------------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 1;

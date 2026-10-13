CREATE TABLE prospects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prospect VARCHAR(190) NOT NULL,
  category VARCHAR(120) NOT NULL DEFAULT '',
  opportunity_type VARCHAR(40) NOT NULL DEFAULT 'Strategic Collab',
  website VARCHAR(500) NOT NULL DEFAULT '',
  social_links TEXT NULL,
  why_kikku_fits TEXT NULL,
  partnership_idea TEXT NULL,
  commercial_potential TEXT NULL,
  strategic_value TEXT NULL,
  score TINYINT UNSIGNED NULL,
  contact_person VARCHAR(190) NOT NULL DEFAULT '',
  contact_role VARCHAR(190) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  social_contact VARCHAR(500) NOT NULL DEFAULT '',
  best_channel VARCHAR(80) NOT NULL DEFAULT '',
  status VARCHAR(40) NOT NULL DEFAULT 'Researching',
  last_contact DATE NULL,
  follow_up_date DATE NULL,
  reply_summary TEXT NULL,
  recommended_next_step TEXT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_prospect_status (status),
  INDEX idx_prospect_follow_up (follow_up_date),
  INDEX idx_prospect_type (opportunity_type),
  INDEX idx_prospect_score (score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prospect_id INT UNSIGNED NOT NULL,
  activity_type VARCHAR(80) NOT NULL,
  note TEXT NULL,
  activity_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_prospect FOREIGN KEY (prospect_id) REFERENCES prospects(id) ON DELETE CASCADE,
  INDEX idx_activity_prospect_date (prospect_id, activity_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE outreach_drafts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prospect_id INT UNSIGNED NOT NULL,
  recipient VARCHAR(190) NOT NULL DEFAULT '',
  subject VARCHAR(255) NOT NULL DEFAULT '',
  channel VARCHAR(80) NOT NULL DEFAULT 'Email',
  body TEXT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'Draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_draft_prospect FOREIGN KEY (prospect_id) REFERENCES prospects(id) ON DELETE CASCADE,
  INDEX idx_draft_prospect (prospect_id),
  INDEX idx_draft_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

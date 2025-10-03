-- Add endpoint_hash to push_subscriptions and remove unique user constraint
ALTER TABLE push_subscriptions
  ADD COLUMN endpoint_hash VARCHAR(255) NULL AFTER endpoint;

-- If a UNIQUE index exists on user_id, drop it (manual step might be required depending on MySQL version)
-- CREATE TABLE to queue notification jobs
CREATE TABLE IF NOT EXISTS notification_jobs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    target_type ENUM('user','area') NOT NULL,
    target_value VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    payload JSON NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    status ENUM('queued','processing','failed','sent') NOT NULL DEFAULT 'queued',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notification_jobs_status (status),
    KEY idx_notification_jobs_target (target_type, target_value)
);

-- Table to log send attempts and errors
CREATE TABLE IF NOT EXISTS notification_send_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NULL,
    subscription_id INT UNSIGNED NULL,
    success BOOLEAN NOT NULL DEFAULT 0,
    response_text TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_send_logs_job (job_id),
    KEY idx_send_logs_sub (subscription_id)
);

-- Add evidence_image column to collection_history table for storing missed collection evidence
ALTER TABLE collection_history 
ADD COLUMN evidence_image VARCHAR(255) NULL AFTER notes;

-- Add index for faster queries
CREATE INDEX idx_evidence_image ON collection_history(evidence_image);

-- Migration: allow multiple push subscriptions per user
-- Drops unique constraint on push_subscriptions.user_id if it exists

ALTER TABLE push_subscriptions
  DROP INDEX IF EXISTS unique_user_subscription;

-- If your MySQL version doesn't support DROP INDEX IF EXISTS, run this instead:
-- ALTER TABLE push_subscriptions DROP INDEX unique_user_subscription;

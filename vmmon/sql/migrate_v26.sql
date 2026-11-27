-- Egen kortordning för kompakt vy (per användare)
ALTER TABLE users ADD COLUMN card_order_compact TEXT NULL;

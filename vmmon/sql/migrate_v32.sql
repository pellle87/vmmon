-- v32: komihåg enhet (2FA), bekräftelse av ny e-postadress
CREATE TABLE IF NOT EXISTS trusted_devices(id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash CHAR(64) NOT NULL, expires DATETIME NOT NULL, created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(user_id), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
ALTER TABLE users ADD COLUMN pending_email VARCHAR(120) NULL, ADD COLUMN email_token CHAR(64) NULL, ADD COLUMN email_exp DATETIME NULL;

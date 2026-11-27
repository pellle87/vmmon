-- Grafkort på dashboarden (per användare)
CREATE TABLE dash_graphs(id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, w TINYINT NOT NULL DEFAULT 1, graphs TEXT NOT NULL, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);

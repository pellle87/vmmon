-- Svarstidshistorik (ms) per kontroll, 5-minutersfack
CREATE TABLE check_ms_hist(check_id INT NOT NULL, bk DATETIME NOT NULL, n INT NOT NULL DEFAULT 0, ms_sum DOUBLE NOT NULL DEFAULT 0, ms_max DOUBLE NOT NULL DEFAULT 0, PRIMARY KEY(check_id,bk), FOREIGN KEY(check_id) REFERENCES server_checks(id) ON DELETE CASCADE);

-- Diskanvändning över tid (5-minutersfack) för dashboardens musövergrafer
CREATE TABLE disk_hist(server_id INT NOT NULL, dk VARCHAR(190) NOT NULL, bk DATETIME NOT NULL, n INT NOT NULL DEFAULT 0, used_sum DOUBLE NOT NULL DEFAULT 0, used_max DOUBLE NOT NULL DEFAULT 0, PRIMARY KEY(server_id,dk,bk), FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE);

USE vmmon;
CREATE TABLE load_hist(server_id INT NOT NULL, bk DATETIME NOT NULL, cpu_sum FLOAT DEFAULT 0, cpu_n INT DEFAULT 0, cpu_max FLOAT NULL, mem_sum FLOAT DEFAULT 0, mem_n INT DEFAULT 0, mem_max FLOAT NULL, PRIMARY KEY(server_id,bk), FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE);

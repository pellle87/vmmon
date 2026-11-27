-- TCP/URL-kontroller: välj upp/ner och/eller svarstid. Befintliga kontroller får upp/ner på och svarstid av.
ALTER TABLE server_checks ADD COLUMN mon_up TINYINT NOT NULL DEFAULT 1, ADD COLUMN mon_ms TINYINT NOT NULL DEFAULT 0;

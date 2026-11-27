-- Grafkort per vy: 'both' (båda), 'normal' eller 'compact'
ALTER TABLE dash_graphs ADD COLUMN v VARCHAR(8) NOT NULL DEFAULT 'both';

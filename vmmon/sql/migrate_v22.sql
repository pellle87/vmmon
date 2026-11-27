-- Dashboardens sortering och vy sparas per användare
ALTER TABLE users ADD COLUMN dash_sort VARCHAR(10) NOT NULL DEFAULT 'layout', ADD COLUMN dash_view VARCHAR(10) NOT NULL DEFAULT 'normal';

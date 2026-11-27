-- v35: interface som visas på dashboardkorten (standard: inga)
ALTER TABLE servers ADD COLUMN if_show TEXT NULL;

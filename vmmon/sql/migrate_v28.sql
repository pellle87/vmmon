-- Historik knyts till server-id i stället för namn (ett omdöpt namn tappar inte historiken).
ALTER TABLE alert_log ADD COLUMN server_id INT NULL, ADD INDEX(server_id);
ALTER TABLE check_events ADD COLUMN server_id INT NULL, ADD INDEX(server_id);
-- Fyll i id för befintliga rader där namnet matchar en server (endast entydiga namn)
UPDATE alert_log a JOIN (SELECT MIN(id) id,name FROM servers WHERE is_services=0 GROUP BY name HAVING COUNT(*)=1) s ON s.name=a.server_name SET a.server_id=s.id WHERE a.server_id IS NULL;
UPDATE alert_log a JOIN (SELECT id FROM servers WHERE is_services=1 LIMIT 1) s ON a.server_name IN ('Tjänster','Services') SET a.server_id=s.id WHERE a.server_id IS NULL;
UPDATE check_events e JOIN (SELECT MIN(id) id,name FROM servers WHERE is_services=0 GROUP BY name HAVING COUNT(*)=1) s ON s.name=e.server_name SET e.server_id=s.id WHERE e.server_id IS NULL AND e.svc=0;
UPDATE check_events e JOIN server_checks c ON c.id=e.check_id SET e.server_id=c.server_id WHERE e.server_id IS NULL;

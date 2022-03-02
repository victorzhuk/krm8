run:
	flock -n /tmp/krm8.lock -c "PDO_DSN='pgsql:host=localhost;port=5432;dbname=postgres;user=postgres;password=secret' php runner.php"

db-start:
	docker run --name krm8 -d --rm -it -p 5432:5432 -e POSTGRES_PASSWORD=secret -v $$PWD/init.sql:/docker-entrypoint-initdb.d/init.sql postgres:14-alpine

db-stop:
	docker stop krm8

.PHONY: run db-start db-stop
.SILENT:
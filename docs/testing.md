# Backend verification

Start Docker Desktop. From repository root:

```powershell
docker compose -p atk-verification -f compose.test.yaml up --build --abort-on-container-exit --exit-code-from api-test
docker compose -p atk-verification -f compose.test.yaml down
```

This standalone Compose project has a dedicated network and RAM-backed MySQL database,
no published database port and no development volume. Credentials in this file are
disposable fixtures only. The runner installs the lockfile and runs SQLite in-memory,
then MySQL, Pint and platform checks. Test storage and vendor are separate tmpfs mounts.
Do not merge this Compose file with the development Compose file.
Never use the development MySQL service for PHPUnit RefreshDatabase.

Fast SQLite check while development API is running:

```powershell
docker compose exec -T -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= -e CACHE_STORE=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync api php vendor/bin/phpunit
docker compose exec -T api php vendor/bin/pint --test
docker compose exec -T api composer audit --locked
```

Full regression results and limitations are recorded in the milestone PR/roadmap.
A green sequential suite does not establish concurrent correctness or production readiness.

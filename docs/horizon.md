# Horizon on Redis

The starter ships Laravel Horizon. It is off until you choose Redis: without `QUEUE_CONNECTION=redis` the starter keeps
the database queue, `composer dev` keeps its `queue:listen`, and `/horizon` still answers (it shows nothing useful).

## Turn it on

```dotenv
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=...          # when the Redis server asks for one
# HORIZON_PREFIX=           # leave empty, see below
```

Run `php artisan horizon` (a service in production: restart it with `php artisan horizon:terminate` on every deploy; in
development run it next to `composer dev`). Check it with `php artisan starter:doctor`: with Redis it checks the
supervisors for the environment, the prefix, the extension and that Redis answers.

## What the starter sets

| Subject | Setting | Where |
| --- | --- | --- |
| Supervisor | One, `default`: connection `redis`, queues `agents`, `webhooks`, `default`, timeout 200 s. An app adds its own supervisors next to it and keeps the rules below. | `config/horizon.php` |
| Prefix | `HORIZON_PREFIX` for Horizon's own data and `REDIS_PREFIX` for the queue keys. Both empty: on a local machine (`APP_ENV=local`) `horizon-<hash of the path>:` and `<app name>-database-<same hash>-`, so every worktree has its own Horizon and its own queue on a shared Redis (the hash is the same in both); elsewhere `<app name>-horizon:` and `<app name>-database-`, which do not change with a release directory. An empty `REDIS_PREFIX` counts as not set. | `config/horizon.php`, `config/database.php` |
| Failed jobs | Stay in the database (`failed_jobs`, driver `database-uuids`). Horizon keeps only a copy to show: 1 day recent, 3 days in the list. | `config/queue.php`, `trim` |
| Long wait | `waits`: 120 s on `agents` and `webhooks`, 60 s on `default`. A `LongWaitDetected` is logged as a warning ("Horizon: long wait on the queue"); with `HORIZON_NOTIFICATION_MAIL` Horizon also mails it. | `config/horizon.php`, `HorizonServiceProvider` |
| Dashboard | `/horizon`, only for a role with an allow policy for `horizon.view` (scope Action, like the other host abilities). The gate also applies on a local machine. | `app/Support/HorizonAccess.php`, `HorizonServiceProvider`, `StarterGovernanceGate::SCOPES` |
| Scheduler | `horizon:snapshot` every five minutes, for the throughput and wait-time graphs, only while `QUEUE_CONNECTION=redis` (on another queue it would fail every time). Needs the scheduler (`schedule:run` every minute). | `routes/console.php` |

### Who may open it

The seeder gives the role `owner` the ability `horizon.view`; the migration `2026_11_13_100000_grant_horizon_access` does
the same for an `owner` role that exists from before Horizon. Every other role gets it on `/governance/roles`. No
e-mail list, no local bypass.

## Timeouts: three numbers that must stay in order

```
timeout of the job  <  timeout of its supervisor  <  retry_after of its connection
```

A job that runs longer than its supervisor's timeout is killed. A supervisor that runs longer than `retry_after`
(`config/queue.php`, 300 s for `redis` and `database`) has its running job handed to a second worker, so the job runs
twice. The starter has 150 s (agent runs), 200 s (supervisor `default`), 300 s (`retry_after`).

`tests/Feature/HorizonConfigTest.php` checks it for every job:

- each job in `app/Jobs` must be listed in `queuedJobs()` in that test, or the coverage test fails;
- the framework jobs on the queues `agents` and `webhooks` are checked too, and the test fails when the framework starts
  using a queue no supervisor reads;
- the test also proves that it fails: a probe job with a timeout of 250 s (over the 200 s of the supervisor), one at 200 s,
  a supervisor at `retry_after`, and a job on a queue nobody reads.

A job with a longer running time needs its own supervisor with a higher `timeout`, and a connection with a higher
`retry_after` (a second Redis connection in `config/queue.php`, for example `redis-long`), and a line in `waits`.
Add the job to `queuedJobs()` and run `composer test`.

## Code

| Part | File |
| --- | --- |
| Config | `config/horizon.php` |
| Provider and gate | `app/Providers/HorizonServiceProvider.php`, `app/Support/HorizonAccess.php` |
| Ability | `app/Access/StarterGovernanceGate.php` (`SCOPES`), `database/seeders/DatabaseSeeder.php`, `database/migrations/2026_11_13_100000_grant_horizon_access.php` |
| Tests | `tests/Feature/HorizonConfigTest.php`, `tests/Feature/HorizonAccessTest.php`, `tests/Feature/DoctorHorizonTest.php`, `tests/Support/QueueTimeouts.php` |

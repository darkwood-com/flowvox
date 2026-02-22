<p align="center">
  <a href="https://github.com/darkwood-com/flowvox">
    <img src="public/logo.png" width="auto" height="128px" alt="Flow">
  </a>
</p>

# Flowvox

Symfony skeleton with Flow and Messenger-only voice worker MVP.

## Voice worker MVP – multi-terminal test

Use **3 terminals** to verify session listing, targeted START/STOP, and broadcast.

**Terminal A – worker alpha**

```bash
php bin/console voice:worker --session=alpha
```

**Terminal C – worker beta**

```bash
php bin/console voice:worker --session=beta
```

**Terminal B – control**

```bash
# List active sessions (alpha + beta)
php bin/console voice:worker-list

# Send START only to alpha (only alpha’s terminal should log receipt)
php bin/console voice:start --session=alpha

# Send STOP only to beta (only beta’s terminal should log receipt)
php bin/console voice:stop --session=beta

# Broadcast START to all active sessions (both alpha and beta should log)
php bin/console voice:start
```

Optional: remove stale sessions (heartbeat older than 30s) before listing:

```bash
php bin/console voice:worker-list --clean-stale
```

**Expected:** Each worker prints when it receives START/STOP and that InputProviderFlow produced an output (type + timestamps + session_id).

## Setup

```bash
composer install
php bin/console doctrine:migrations:migrate --no-interaction
```

Database: SQLite by default (`var/data.db`). Set `DATABASE_URL` in `.env` to use another driver.

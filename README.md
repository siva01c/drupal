# Drupal 11 & PHP 8.4 & MySQL 8.4

Drupal & Docker Compose starter pack. You need only `git` and Docker with the Compose plugin.

## Start the stack

```
git clone https://github.com/siva01c/drupal.git drupal
cd drupal
cp .env.default .env   # Copy file .env.default to .env
docker compose up -d
docker compose exec drupal composer install
```

The first `docker compose up -d` builds the PHP and nginx images, which takes several minutes.

The PHP container runs as `DAO_DRUPAL` from `.env` (`uid:gid`, default `1000:1000`), so that
composer and Drupal can write into the project directory. If `id -u` prints something else on
your machine, set `DAO_DRUPAL` to your `uid:gid` in `.env` before `docker compose up -d`.

Open url in your browser: http://localhost:1577 - port is defined in .env file as DAO_PORT_NGINX

## Install Drupal

Either way works; the values below are the defaults from `.env.default`.

### In the browser

http://localhost:1577 redirects to the installer. Choose the language and the installation
profile, then fill in the database form:

| Field | Value |
|---|---|
| Database name | `drupal` |
| Database username | `drupal` |
| Database password | `password` |
| Advanced options → Host | `mysql` |

The host is the name of the database service in `docker-compose.yml`, not `localhost`.

### From the command line

```
docker compose exec drupal drush site:install standard \
  --db-url=mysql://drupal:password@mysql/drupal \
  --site-name="Drupal demo" --account-name=admin --account-pass=change-me -y
```

This replaces an existing installation, database included. Use your own `--account-pass`;
`change-me` is only a placeholder.

## Good to know

- `docker compose exec drupal bash` opens a shell in the PHP container; `drush` and `composer`
  are on the path.
- The database values come from `.env`; if you change them there, use the new ones in the
  installer and in `--db-url`.

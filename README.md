# Drupal 11 & PHP 8.3 & MySQL 8.4

```
git clone git@github.com:siva01c/drupal.git drupal 
cd drupal 
cp .env.default .env   # Copy file .env.default to .env 
docker-compose up -d 
docker-compose exec drupal bash
composer install
```

Open url in your browser: http://localhost:1577 - port is defined in .env file as DAO_PORT_NGINX

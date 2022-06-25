# drupal-9
Drupal 9 installation pack

git clone https://github.com/siva01c/drupal-9.git  drupal9 \
cd drupal9 \
docker-compose up -d \
docker-compose exec drupal bash \
composer install

Open url in your broswer:http://localhost:1577  - port is defined in .env file as DAO_PORT_NGINX

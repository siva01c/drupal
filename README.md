# drupal 11
Drupal 11 installation pack

git clone git@github.com:siva01c/drupal.git  drupal \
cd drupal \
cp .env.default .env   # Copy file .env.default to .env 
docker-compose up -d \
docker-compose exec drupal bash \
composer install

Open url in your bro0wser:http://localhost:1577  - port is defined in .env file as DAO_PORT_NGINX

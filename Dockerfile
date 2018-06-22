FROM php:7.2-cli-stretch

WORKDIR /app
COPY . .

CMD [ "php", "/app/cheat.php" ]

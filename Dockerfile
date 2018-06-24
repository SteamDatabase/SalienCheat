FROM php:7.2-cli-stretch

WORKDIR /app
COPY . .

ENTRYPOINT [ "php", "/app/cheat.php" ]
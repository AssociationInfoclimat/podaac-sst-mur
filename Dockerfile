FROM ghcr.io/osgeo/gdal:ubuntu-full-3.11.0

WORKDIR /app

RUN apt-get update && \
    apt-get install -y \
        curl \
        php \
        php-cli \
        php-common \
        php-curl \
        php-mysql

COPY ./src /app

CMD ["php", "/app/main.php"]

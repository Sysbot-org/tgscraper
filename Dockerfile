FROM composer:latest AS tgscraper

MAINTAINER Sys <sys@sys001.ml>

WORKDIR /app
COPY . .
RUN composer install
WORKDIR /artifacts
VOLUME /artifacts

ENTRYPOINT ["php", "/app/bin/tgscraper"]
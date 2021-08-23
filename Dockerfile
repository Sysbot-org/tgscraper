FROM composer:latest AS tgscraper

MAINTAINER Sys <sys@sys001.ml>

WORKDIR /app
RUN composer require sysbot/tgscraper sysbot/tgscraper-cache --no-progress --no-interaction --no-ansi --prefer-stable --optimize-autoloader
WORKDIR /out
VOLUME /out

ENTRYPOINT ["php", "/app/vendor/bin/tgscraper"]
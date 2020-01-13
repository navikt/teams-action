FROM php:7.3-cli-alpine AS build
WORKDIR /app
COPY src ./src
COPY composer.json composer.lock action.php ./
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === 'baf1608c33254d00611ac1705c1d9958c817a1a33bce370c0595974b342601bd80b92a3f46067da89e3b06bff421f182') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    apk update && \
    apk add zip && \
    addgroup -S teams && \
    adduser -S teams -G teams && \
    chown -R teams /app
USER teams
RUN php composer.phar install -o --no-dev --no-plugins --no-scripts --no-ansi && \
    rm composer.phar

FROM php:7.3-cli-alpine
LABEL "repository"="https://github.com/navikt/teams-action"
LABEL "maintainer"="@navikt/aura"
COPY --from=build /app /app
ENTRYPOINT ["php", "/app/action.php"]

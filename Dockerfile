FROM php:7.3-cli-alpine AS build
WORKDIR /app
COPY src ./src
COPY composer.json composer.lock action.php ./
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === 'a5c698ffe4b8e849a443b120cd5ba38043260d5c4023dbf93e1558871f1f07f58274fc6f4c93bcfd858c6bd0775cd8d1') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
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

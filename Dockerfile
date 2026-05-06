# ── Stage 1: Composer dependencies ──
FROM composer:2 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

# ── Stage 2: Frontend build (needs PHP for Wayfinder) ──
FROM serversideup/php:8.5-cli AS frontend-build

USER root
RUN install-php-extensions intl bcmath

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy app code + vendor (needed for artisan wayfinder:generate)
COPY . .
COPY --from=composer-deps /app/vendor ./vendor

# Install npm deps and build (Wayfinder plugin will call php artisan)
RUN php artisan wayfinder:generate --with-form \
    && npm ci \
    && npm run build

# ── Stage 3: Production image ──
FROM serversideup/php:8.5-fpm-nginx

USER root

# Install PHP extensions
RUN install-php-extensions intl bcmath gd exif pcntl redis

# Install Node.js and Chromium dependencies for Browsershot
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
        libx11-xcb1 libxcomposite1 libasound2t64 libatk1.0-0 libatk-bridge2.0-0 \
        libcairo2 libcups2 libdbus-1-3 libexpat1 libfontconfig1 libgbm1 libgcc1 \
        libglib2.0-0 libgtk-3-0 libnspr4 libnss3 libpango-1.0-0 libpangocairo-1.0-0 \
        libstdc++6 libx11-6 libxcb1 libxcursor1 libxdamage1 libxext6 libxfixes3 \
        libxi6 libxrandr2 libxrender1 libxss1 libxtst6 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Puppeteer with bundled Chromium into a shared cache
ENV PUPPETEER_CACHE_DIR=/opt/puppeteer
RUN npm install -g puppeteer \
    && chmod -R o+rx /opt/puppeteer

# Copy startup script
COPY --chmod=755 ./entrypoint.d/ /etc/entrypoint.d/

# Drop back to unprivileged user
USER www-data

# Copy application code
COPY --chown=www-data:www-data . /var/www/html

# Copy Composer dependencies from stage 1
COPY --chown=www-data:www-data --from=composer-deps /app/vendor /var/www/html/vendor

# Copy built frontend assets from stage 2
COPY --chown=www-data:www-data --from=frontend-build /app/public/build /var/www/html/public/build

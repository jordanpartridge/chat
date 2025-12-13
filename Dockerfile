FROM php:8.4-cli

LABEL maintainer="Chat App"

ARG WWWGROUP=1000
ARG NODE_VERSION=22

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Install system dependencies
RUN apt-get update && apt-get install -y \
    gnupg \
    gosu \
    curl \
    ca-certificates \
    zip \
    unzip \
    git \
    supervisor \
    sqlite3 \
    libcap2-bin \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libsqlite3-dev \
    libnss3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libdrm2 \
    libxkbcommon0 \
    libxcomposite1 \
    libxdamage1 \
    libxfixes3 \
    libxrandr2 \
    libgbm1 \
    libasound2 \
    libpango-1.0-0 \
    libcairo2 \
    libatspi2.0-0 \
    libgtk-3-0 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm

# Create sail user
RUN groupadd --force -g $WWWGROUP sail \
    && useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail

# Copy application
COPY . /var/www/html

# Install dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader
RUN npm ci

# Install Playwright browsers
RUN npx playwright install chromium --with-deps

# Build frontend
RUN npm run build

# Setup permissions
RUN chown -R sail:sail /var/www/html \
    && chmod -R 755 /var/www/html/storage

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80 5173

ENTRYPOINT ["docker-entrypoint.sh"]

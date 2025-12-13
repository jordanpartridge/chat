FROM mcr.microsoft.com/playwright:v1.49.0-jammy

WORKDIR /var/www/html

# Install PHP 8.4
RUN apt-get update && apt-get install -y \
    software-properties-common \
    && add-apt-repository ppa:ondrej/php \
    && apt-get update \
    && apt-get install -y \
        php8.4-cli php8.4-pgsql php8.4-redis php8.4-mbstring \
        php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath \
        composer \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy application
COPY . /var/www/html

# Install PHP dependencies
RUN if [ ! -d "vendor" ]; then composer install --no-interaction; fi

# Install Node dependencies
RUN npm ci

# Build frontend
RUN npm run build

CMD ["php", "artisan", "test", "--filter=Browser"]

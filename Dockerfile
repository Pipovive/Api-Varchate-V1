FROM dunglas/frankenphp:php8.2.30-bookworm

# Instalar composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalar dependencias del sistema y extensiones PHP
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY . /app
WORKDIR /app

RUN composer install --optimize-autoloader --no-scripts --no-interaction

CMD ["frankenphp", "run", "--config", "/Caddyfile"]

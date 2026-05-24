# 🚀 StayTime — Deployment Guide

Повний покроковий гайд для розгортання Shopware 6 магазину StayTime на сервері за допомогою Docker.

---

## 📋 Огляд проєкту

| Параметр | Значення |
|---|---|
| **Shopware** | 6.7.8.2 |
| **PHP** | 8.3 |
| **Database** | MariaDB 10.11 / MySQL 8.0 |
| **Node.js** | 24.x (для збірки storefront) |
| **Git** | `git@gitlab.com:khrystyianyn/stay-time.git` |
| **Кастомний плагін** | `MeineMinecraft` |
| **Платіжний плагін** | `SwagPayPal 10.6.0` |

---

## 📦 Що потрібно передати

### 1. Git-репозиторій (основний код)
Репозиторій вже запушений на GitLab. Інша людина клонує його:
```bash
git clone git@gitlab.com:khrystyianyn/stay-time.git
```

### 2. Дамп бази даних
Базу потрібно експортувати окремо (вона НЕ в Git):
```bash
mysqldump -u shopware -p shopware > shopware_dump.sql
```

### 3. Медіа-файли
Папка `public/media/` (~2.7MB) та `public/theme/` — не в Git. Заархівувати:
```bash
tar -czf media_files.tar.gz public/media/ public/theme/ public/thumbnail/
```

### 4. Docker-конфігурація
Файли нижче потрібно створити і додати до репозиторію.

---

## 🐳 Docker-конфігурація

### Структура файлів
```
stay-time/
├── docker/
│   ├── nginx.conf          # Конфігурація Nginx
│   └── php.ini             # PHP налаштування
├── Dockerfile
├── docker-compose.yml
├── .dockerignore
├── .env                    # (НЕ комітити — кожен сервер свій)
└── ...
```

---

### Файл: `Dockerfile`

```dockerfile
FROM php:8.3-fpm

# Системні залежності
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libxslt-dev \
    libcurl4-openssl-dev \
    unzip \
    git \
    nginx \
    supervisor \
    curl \
    && rm -rf /var/lib/apt/lists/*

# PHP-розширення для Shopware 6
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        intl \
        pdo_mysql \
        gd \
        zip \
        opcache \
        xsl \
        curl \
        dom \
        xml

# Composer
COPY --from=composer:2 /usr/local/bin/composer /usr/local/bin/composer

# Node.js (для збірки storefront)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Робоча директорія
WORKDIR /var/www/html

# Копіюємо composer-файли окремо для кешування шарів
COPY composer.json composer.lock ./

# Встановлюємо залежності
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Копіюємо весь проєкт
COPY . .

# Запускаємо composer scripts після копіювання файлів
RUN composer run-script post-install-cmd --no-interaction || true

# PHP конфігурація
COPY docker/php.ini /usr/local/etc/php/conf.d/shopware.ini

# Nginx конфігурація
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Supervisor для запуску Nginx + PHP-FPM разом
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Права доступу
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/var \
    && chmod -R 775 /var/www/html/public \
    && chmod -R 775 /var/www/html/config \
    && chmod -R 775 /var/www/html/files

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

---

### Файл: `docker-compose.yml`

```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: staytime-app
    restart: unless-stopped
    ports:
      - "8000:80"
    volumes:
      - media_data:/var/www/html/public/media
      - theme_data:/var/www/html/public/theme
      - thumbnail_data:/var/www/html/public/thumbnail
      - files_data:/var/www/html/files
    environment:
      APP_ENV: prod
      APP_URL: ${APP_URL:-http://localhost:8000}
      APP_SECRET: ${APP_SECRET:-CHANGE_ME_TO_RANDOM_SECRET}
      DATABASE_URL: mysql://shopware:shopware@db:3306/shopware
      MAILER_DSN: ${MAILER_DSN:-null://null}
      SHOPWARE_HTTP_CACHE_ENABLED: 1
      SHOPWARE_HTTP_DEFAULT_TTL: 7200
      BLUE_GREEN_DEPLOYMENT: 0
      OPENSEARCH_URL: http://localhost:9200
      SHOPWARE_ES_ENABLED: 0
      LOCK_DSN: flock
    depends_on:
      db:
        condition: service_healthy

  db:
    image: mariadb:10.11
    container_name: staytime-db
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:-rootpassword}
      MYSQL_DATABASE: shopware
      MYSQL_USER: shopware
      MYSQL_PASSWORD: shopware
    ports:
      - "3307:3306"
    volumes:
      - db_data:/var/lib/mysql
      - ./shopware_dump.sql:/docker-entrypoint-initdb.d/dump.sql:ro
    healthcheck:
      test: ["CMD", "mariadb-admin", "ping", "-h", "localhost", "-u", "root", "-p${MYSQL_ROOT_PASSWORD:-rootpassword}"]
      interval: 5s
      timeout: 5s
      retries: 10

volumes:
  db_data:
  media_data:
  theme_data:
  thumbnail_data:
  files_data:
```

---

### Файл: `docker/nginx.conf`

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 128M;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        fastcgi_read_timeout 300;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location ~ /\. {
        deny all;
    }
}
```

---

### Файл: `docker/php.ini`

```ini
memory_limit = 512M
upload_max_filesize = 128M
post_max_size = 128M
max_execution_time = 300
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
realpath_cache_size = 4096K
realpath_cache_ttl = 600
```

---

### Файл: `docker/supervisord.conf`

```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisord.log

[program:php-fpm]
command=php-fpm --nodaemonize
autostart=true
autorestart=true

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
```

---

### Файл: `.dockerignore`

```
.git
.idea
.env.local
.env.*.local
var/cache/*
var/log/*
node_modules
vendor
```

---

## 📝 Покрокова інструкція для деплою

### Крок 1: Підготовка на ВАШІЙ машині (розробник)

```bash
# 1. Експорт бази даних
mysqldump -u shopware -pshopware shopware > shopware_dump.sql

# 2. Архів медіа-файлів
tar -czf media_files.tar.gz public/media/ public/theme/ public/thumbnail/

# 3. Створити Docker-файли (як описано вище) і закомітити
git add Dockerfile docker-compose.yml docker/ .dockerignore
git commit -m "Add Docker deployment config"
git push
```

### Крок 2: На СЕРВЕРІ (людина, яка деплоїть)

```bash
# 1. Клонувати репозиторій
git clone git@gitlab.com:khrystyianyn/stay-time.git
cd stay-time

# 2. Покласти дамп БД у корінь проєкту
# (отримати shopware_dump.sql від розробника)
cp /path/to/shopware_dump.sql ./shopware_dump.sql

# 3. Створити .env файл для продакшну
cat > .env << 'EOF'
APP_ENV=prod
APP_URL=https://your-domain.com
APP_SECRET=ЗГЕНЕРУЙТЕ_НОВИЙ_СЕКРЕТ_ТУТ
MYSQL_ROOT_PASSWORD=strong_root_password_here
MAILER_DSN=smtp://user:pass@smtp.example.com:587
EOF

# 4. Розпакувати медіа (якщо передані окремо)
tar -xzf /path/to/media_files.tar.gz

# 5. Запустити Docker
docker compose up -d --build

# 6. Дочекатись поки БД ініціалізується (перший раз ~1-2 хвилини)
docker compose logs -f db  # Ctrl+C коли побачите "ready for connections"

# 7. Виконати міграції та збірку всередині контейнера
docker compose exec app bash -c "
    bin/console system:install --basic-setup --force || true
    bin/console database:migrate --all
    bin/console plugin:refresh
    bin/console plugin:install --activate MeineMinecraft
    bin/console plugin:install --activate SwagPayPal || true
    bin/console theme:compile
    bin/console assets:install
    bin/console cache:clear
"

# 8. Копіювати медіа-файли в Docker-том
docker compose cp public/media/. staytime-app:/var/www/html/public/media/
docker compose cp public/theme/. staytime-app:/var/www/html/public/theme/
docker compose cp public/thumbnail/. staytime-app:/var/www/html/public/thumbnail/

# 9. Перевірити
curl http://localhost:8000
```

### Крок 3: Налаштувати SSL (HTTPS)

> [!IMPORTANT]
> Для продакшну обов'язково потрібен HTTPS. Найпростіший варіант — Caddy як reverse proxy.

Додайте до `docker-compose.yml`:

```yaml
  caddy:
    image: caddy:2
    container_name: staytime-caddy
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      - app
```

І створіть `Caddyfile`:
```
your-domain.com {
    reverse_proxy app:80
}
```

Змініть порт `app` з `"8000:80"` на `"8080:80"` (або приберіть зовсім, Caddy буде проксувати).

---

## 🔄 Оновлення після змін

Коли ви вносите зміни в код і пушите:

```bash
# На сервері:
cd stay-time
git pull

# Перезібрати контейнер
docker compose up -d --build

# Виконати міграції та очистити кеш
docker compose exec app bash -c "
    bin/console database:migrate --all
    bin/console theme:compile
    bin/console cache:clear
"
```

---

## ⚠️ Важливі нотатки

> [!WARNING]
> **НЕ комітьте `.env` файл у Git!** Він містить паролі та секрети. Кожен сервер повинен мати свій `.env`.

> [!IMPORTANT]
> **APP_URL** повинен точно відповідати домену сервера. Якщо домен `https://staytime.shop`, то `APP_URL=https://staytime.shop`.

> [!TIP]
> Для генерації `APP_SECRET` використовуйте:
> ```bash
> openssl rand -hex 32
> ```

> [!CAUTION]
> **База даних** містить усі товари, категорії, замовлення, налаштування плагінів та теми. Без дампу БД сайт буде порожнім!

---

## 🔧 Усунення проблем

| Проблема | Рішення |
|---|---|
| Білий екран | `docker compose exec app bin/console cache:clear` |
| 500 помилка | `docker compose logs app` — дивитися PHP помилки |
| Стилі не працюють | `docker compose exec app bash -c "bin/build-storefront.sh && bin/console theme:compile"` |
| БД не підключається | Перевірте `DATABASE_URL` та чи запущений контейнер `db` |
| Медіа не відображаються | Перевірте що `public/media/` скопійовано та має права `www-data` |
| Плагін не працює | `docker compose exec app bin/console plugin:refresh && bin/console plugin:install --activate MeineMinecraft` |

---

## 📁 Чеклист для передачі

- [ ] Git-репозиторій з Docker-файлами
- [ ] `shopware_dump.sql` — дамп бази даних
- [ ] `media_files.tar.gz` — медіа файли
- [ ] Цей гайд
- [ ] Доступ до GitLab (SSH ключ або токен)
- [ ] Дані SMTP для пошти (якщо потрібно)
- [ ] Дані PayPal (API credentials, якщо використовується)

## в поточній папці запускаємо
composer create-project shopware/production .
чи створювати докер-контейнер - y

Ти вже маєш:

✔ код Shopware
✔ composer dependencies
✔ структуру проєкту
❗ але ще НЕ встановлена база і система

## DATABASE
Встанови MariaDB сервер
```
sudo apt update
sudo apt install mariadb-server -y
```
можливо це потрібно було тільки перший раз


Запусти сервіс. Після встановлення:
sudo systemctl start mariadb
systemctl status mariadb

##### Якщо все запустилось — наступний крок

Після цього:
```
sudo mariadb
```
І далі створюємо базу для Shopware 6
```
CREATE DATABASE shopware;
CREATE USER 'shopware'@'localhost' IDENTIFIED BY 'shopware';
GRANT ALL PRIVILEGES ON shopware.* TO 'shopware'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```
.env
DATABASE_URL="mysql://shopware:shopware@127.0.0.1:3306/shopware"
## Потім запускаємо Shopware
bin/console system:install --basic-setup

## запуск
php -S localhost:8000 -t public

ЛОГІН В АДМІНКУ
👤 Username:
admin
🔑 Password:
shopware

або якщо винукнуть проблеми із білим екраном то спробувати наступні кроки (KOREKT-WHITE.md)
php -S localhost:8000 -t public public/router.php

## для бази даних MySQL
Встановити MySQL:
```
sudo apt update
sudo apt install mysql-server
```
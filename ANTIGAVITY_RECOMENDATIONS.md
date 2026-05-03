# ANTIGRAVITY RECOMENDATIONS: Setup Shopware 6 correctly

This guide provides a professional and robust way to set up a new Shopware 6 project, avoiding the common "White Screen" and "Missing Styles" issues.

## 1. Preparation
Before starting, ensure you have the required PHP extensions and Node.js.
```bash
# Example for Ubuntu/Debian
sudo apt install php-curl php-gd php-intl php-mbstring php-xml php-zip php-mysql php-opcache -y
```

## 2. Project Creation
Run the composer command in an empty directory:
```bash
composer create-project shopware/production .
```

## 3. Environment Setup (`.env`)
**IMPORTANT**: Set your `APP_URL` correctly before installation. If you are working locally on port 8000, use:
```env
APP_URL=http://localhost:8000
```
Also, configure your `DATABASE_URL` as mentioned in your guide.

## 4. System Installation
Use the CLI installer for a clean setup:
```bash
bin/console system:install --basic-setup
```

## 5. Web Server Setup (The "No White Screen" Secret)
Avoid using a simple `php -S localhost:8000 -t public`. It will break routing and styles.

### Option A: Symfony CLI (Recommended)
This is the easiest and most stable way to run Shopware locally:
1. Install it: `curl -sS https://get.symfony.com/cli/installer | bash`
2. Move it to bin: `sudo mv ~/.symfony5/bin/symfony /usr/local/bin/symfony`
3. Run: `symfony server:start -d`

### Option B: PHP Built-in Server with Router
If you MUST use `php -S`, always use the `router.php` we created:
1. Ensure `public/router.php` exists.
2. Run: 
```bash
php -S localhost:8000 -t public public/router.php
```

## 6. Pro-Tips for Stability
*   **Permissions**: Ensure `var`, `public/theme`, and `public/media` are writable.
*   **Cache**: If something looks wrong, run `php bin/console cache:clear`.
*   **Domain Alignment**: If you change your URL later, remember to update the database:
    ```bash
    php bin/console sales-channel:update:domain http://new-domain:port
    ```
*   **Assets**: If the Admin looks old or missing icons, run:
    ```bash
    php bin/console assets:install
    ```
+
+## 7. How to switch to Symfony CLI (Right Now)
+
+If you want to move from your current `php -S` setup to Symfony CLI:
+
+1.  **Stop the current server**: Go to the terminal where `php -S` is running and press `Ctrl + C`.
+2.  **Install Symfony CLI**:
+    ```bash
+    curl -sS https://get.symfony.com/cli/installer | bash
+    ```
+3.  **Make it available everywhere**:
+    ```bash
+    # Add to your current session
+    export PATH="$HOME/.symfony5/bin:$PATH"
+    
+    # Add to your profile (so it works after restart)
+    echo 'export PATH="$HOME/.symfony5/bin:$PATH"' >> ~/.bashrc
+    ```
+4.  **Verify installation**:
+    ```bash
+    symfony version
+    ```
+5.  **Start the server**:
+    ```bash
+    symfony server:start -d
+    ```
+    *Note: You don't need `router.php` or `-t public`. Symfony CLI is smart enough to find them.*
+6.  **Access your shop**: Open `http://localhost:8000` in your browser.
+
+---
+*Pro-Tip: Use `symfony server:log` to see what's happening behind the scenes.*

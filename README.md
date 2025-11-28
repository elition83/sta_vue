
# Lighttpd + laravel (API, без Blade) + VUE (Vite)

## Настройка

### Ставим нужный софт
```bash
sudo apt update
sudo apt install lighttpd php php-fpm php-mbstring php-xml php-curl php-zip php-sqlite3 php-mysql composer 
sudo apt install curl git unzip
```
#node 22
```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

### папка под Проект
```bash
mkdir sta
```
### install Backend (laravel)
```bash
cd ~/sta
composer create-project laravel/laravel backend
cd backend
composer require laravel/sanctum
```

### install Frontend (VUE + Element+)
```bash
cd ~/sta
npm create vue@latest frontend
cd frontend
npm install
npm install element-plus
```


### Install Lighttpd
```bash
sudo apt update
sudo apt install lighttpd lighttpd-mod-proxy
sudo lighttpd-enable-mod proxy
sudo service lighttpd restart
```
### Конфиг Lighttpd
```bash
# /etc/lighttpd/lighttpd.conf
# server.modules += ( "mod_proxy" )
server.modules = (
        "mod_indexfile",
        "mod_access",
        "mod_alias",
        "mod_redirect",
        "mod_proxy",
)
...
# В конце добавляем 
#$HTTP["host"] == "127.0.0.1" {
	$HTTP["url"] =~ "^/api" {
		proxy.server = (
			"" => ( ( "host" => "127.0.0.1", "port" => 8000 ) )
		)
	}

	$HTTP["url"] !~ "^/api" {
		proxy.server = (
			"" => ( ( "host" => "127.0.0.1", "port" => 5173 ) )
		)
	}
#}
```

### Проверяем, запускаем
```bash
#front
cd ~sta/frontend
npm run dev
#или для прода 
npm run build

#back
cd ~sta/backend/
php artisan serve --host=0.0.0.0 --port=8000

#web server
sudo lighttpd -tt -f /etc/lighttpd/lighttpd.conf
sudo service lighttpd restart
```

### Пишем небольшой скрипт для запуска 
```bash
cd sta
touch run.sh
chmod +x run.sh
```
nano run.sh
```bash

#!/bin/bash

FRONT_PID_FILE=/tmp/sta_front.pid
BACK_PID_FILE=/tmp/sta_back.pid
BASE_DIR=$(dirname "$(realpath "$0")")

start_dev() {
    echo ">>> DEV: vite + artisan serve + lighttpd"

    # FRONTEND
    echo ">>> Starting Vite..."
    cd "$BASE_DIR/frontend"
    npm run dev -- --host --port=5173 >/tmp/sta_front.log 2>&1 &
    FRONT_PID=$!
    echo $FRONT_PID > "$FRONT_PID_FILE"
    cd "$BASE_DIR"

    # BACKEND
    echo ">>> Starting Laravel..."
    cd "$BASE_DIR/backend"
    php artisan serve --host=0.0.0.0 --port=8000 >/tmp/sta_back.log 2>&1 &
    BACK_PID=$!
    echo $BACK_PID > "$BACK_PID_FILE"
    cd "$BASE_DIR"

    # LIGHTTPD
    echo ">>> Reloading lighttpd..."
    sudo service lighttpd reload || sudo service lighttpd restart

    echo "-----------------------------------------"
    echo "Frontend (Vite) PID:   $FRONT_PID"
    echo "Backend (Laravel) PID: $BACK_PID"
    echo "-----------------------------------------"
    echo "Dev-линии:"
    echo "  sta.com        → proxy на Vite (5173)"
    echo "  sta.com/api    → proxy на Laravel (8000)"
    echo "Локально:"
    echo "  http://localhost:5173 (Vite)"
    echo "  http://localhost:8000 (API)"
    echo "  http://localhost (STA)"
    echo "-----------------------------------------"
    echo "DEV started (in background)"
}

start_prod() {
    echo ">>> PROD: build + lighttpd"

    echo ">>> Building frontend..."
    cd "$BASE_DIR/frontend"
    npm run build || { echo 'Build failed'; exit 1; }
    cd "$BASE_DIR"

    echo ">>> Reloading lighttpd..."
    sudo service lighttpd reload || sudo service lighttpd restart

    echo "-----------------------------------------"
    echo "PROD ready at:"
    echo "  http://sta.com"
    echo "-----------------------------------------"
}

stop_all() {
    echo ">>> STOP"

    if [ -f "$FRONT_PID_FILE" ]; then
        PID=$(cat "$FRONT_PID_FILE")
        kill $PID 2>/dev/null
        rm -f "$FRONT_PID_FILE"
        echo "Stopped Vite (PID $PID)"
    else
        echo "Vite: not running"
    fi

    if [ -f "$BACK_PID_FILE" ]; then
        PID=$(cat "$BACK_PID_FILE")
        kill $PID 2>/dev/null
        rm -f "$BACK_PID_FILE"
        echo "Stopped Laravel (PID $PID)"
    else
        echo "Laravel: not running"
    fi

    echo "STOP complete."
}

restart_all() {
    stop_all
    echo
    start_dev
}

statu_all() {
    echo ">>> STATUS"

    if [ -f "$FRONT_PID_FILE" ] && ps -p $(cat "$FRONT_PID_FILE") >/dev/null; then
        echo "Frontend: RUNNING (PID $(cat $FRONT_PID_FILE))"
    else
        echo "Frontend: STOPPED"
    fi

    if [ -f "$BACK_PID_FILE" ] && ps -p $(cat "$BACK_PID_FILE") >/dev/null; then
        echo "Backend: RUNNING (PID $(cat $BACK_PID_FILE))"
    else
        echo "Backend: STOPPED"
    fi
}

case "$1" in
    dev)
        start_dev
        ;;
    prod)
        start_prod
        ;;
    stop)
        stop_all
        ;;
    restart)
        restart_all
        ;;
    statu)
        statu_all
        ;;
    *)
        echo "Usage: $0 {dev|prod|stop|restart|statu}"
        exit 1
        ;;
esac

```


## Настройка GIT
```bash
nano ~/sta/.gitignore
```
```bash
# Laravel
backend/vendor/
backend/storage/*.key
backend/storage/logs/
backend/storage/framework/sessions/
backend/storage/framework/cache/
backend/storage/framework/views/
backend/.env

# Node/Vue
frontend/node_modules/
frontend/dist/
frontend/.vite

# PIDs
/tmp/sta_front.pid
/tmp/sta_back.pid

# Logs
*.log

# OS
.DS_Store

# IDE
.vscode/
.idea/

```

```bash
git add backend frontend run.sh .gitignore
git commit -m "Initial STA project (Laravel API + Vue + Lighttpd)"
```


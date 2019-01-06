# Настройка dev-окружаения под windows.

Исходим из того, что:
* у вас 64-х битная Windows 7,
* корневая папка для окружения **d:/gamedevby** (может быть любая другая).

## 1. Установка php

1.1. Качаем от [сюда](https://windows.php.net/download/) последню версию **PHP 7.3.X VC15 x64 Non Thread Safe** в виде zip-файла.
Распаковываем содержимое архива в папку **d:/gamedevby/php**.

1.2. Переименовываем файл **d:/gamedevby/php/php.ini-development** в **d:/gamedevby/php/php.ini** (далее просто файл **php.ini**).

1.3. Включаем нужные расширения, для этого в файле **php.ini** убираем точку с запятой перед следующими строками:
```
;extension_dir = "ext"

;extension=curl
;extension=gd2
;extension=intl
;extension=mbstring
;extension=openssl
``` 

1.4. Скачиваем от [сюда](https://curl.haxx.se/docs/caextract.html) последнюю версию cacert.pem и сохраняем его по пути **d:/gamedevby/cacert.pem**.
В файле **php.ini** изменяем следующие строки:
```
curl.cainfo ="d:/gamedevby/cacert.pem"

openssl.cafile="d:/gamedevby/cacert.pem"
``` 

1.5. Настраиваем дебаг. Скачиваем от [сюда](https://xdebug.org/download.php) последнюю версию **Xdebug PHP 7.3 VC15 (64 bit)**.
Скачаный файл переименовываем и сохраняем по пути **d:/gamedevby/php/ext/php_xdebug.dll**.
В конец файла **php.ini** добавляем следующие строки:
```
zend_extension=php_xdebug.dll
xdebug.remote_enable=1
xdebug.remote_handler=dbgp
xdebug.remote_mode=req
xdebug.remote_host=127.0.0.1
xdebug.remote_port=9000
xdebug.max_nesting_level=1000
;xdebug.idekey=<idekey>
; You may also want this - to always start a remote debugging connection.
;xdebug.remote_autostart=1
```

1.6. Выполяем из cmd-консоли следующие команды:
```
cd d:/gamedevby/php
php -i
```
Убеждаемся что последняя команда отработала коректно и в ее выводе нет никаких сообщений об ошибках.

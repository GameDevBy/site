# Настройка dev-окружаения под windows.

Минимальные требования:

* Windows 7 SP1+ / Windows Server 2008+
* [PowerShell 3+](https://www.microsoft.com/en-us/download/details.aspx?id=34595)
* [.NET Framework 4.5+](https://www.microsoft.com/net/download)

Скачиваете проект:

```
git clone https://github.com/GameDevBy/site.git gamedevby
```

Запускаете в корне проекта файл .\windows_init.bat

Он скачивает все необходимое и настраивает проект.

Поитогу в корне проекта будет сгенерирован файл .\init_env.bat
Данный файл необходимо запускать предварительно перед работой с приложением, он настраивает все пути.

пример
```
открыли консоль через вызов "cmd" или любым другим способом

cd <путь к проекту>
init_env.bat (после набора "in" можно нажать tab и остальное наберется через автоподстановку)

composer drupal:check
```

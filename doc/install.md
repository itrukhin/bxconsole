# Установка
```bash
composer require itrukhin/bxconsole:dev-master
```
Предполагается, что у вас Битрикс уже умеет работать с автозагрузкой composer

# Настройка
BxConsole использует [symfony/dotenv](https://github.com/symfony/dotenv) и ищет файл **.env** в папке,
где лежит папка **vendor**, или на уровень выше. 
Если файл найден - он будет подключен. Это самый удобный способ настроек.

Основная настройка - это правильно указать путь к корневой директории **$DOCUMENT_ROOT**, где лежит папка **bitrix**

Это можно сделать несколькими способами.

1. BxConsole по умолчанию использует в качестве **$DOCUMENT_ROOT** папку, в которой находится папка **vendor**. 
2. Если это так, то можно дополнительно ничего не настраивать.
3. в файле .env указать через параметр **APP_DOCUMENT_ROOT**
4. Указать в файле **composer.json** в секции **extra**
```json
{
	"extra": {
              "document-root": "/home/bitrix/ext_www/site.ru"
    }
}
```
При правильной установке и вызове консоли с параметром -vv - вы увидите версию ядра битрикс.
![Первый запуск](https://raw.githubusercontent.com/itrukhin/bxconsole/master/doc/img/first_run.png)

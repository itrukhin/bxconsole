# Создание команд

Написание собственных команд отличается от
[написания команд для Symfony Console](http://symfony.com/doc/current/components/console/introduction.html) только
классом-родителем. Для работы с BxConsole классы команд нужно наследовать от класса
```php
\App\BxConsole\BxCommand
```

По аналогии с [Console Jedi](https://github.com/notamedia/console-jedi),
свои консольные команды нужно размещать в модулях «Битрикса». 
Для этого в файле `vendor.module/.cli.php` должны быть описаны консольные команды модуля:

```php
<?php

return [
    'commands' => [
         new \Vendor\Module\Command\FirstCommand()
    ]
];
```

Во время запуска BxConsole автоматически загрузит команды всех установленных модулей.

---
[к содержанию](README.md)
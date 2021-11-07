# Выполнение команд по расписанию

Чтобы настроить выполнение команд по расписанию - необходимо через cron обеспечить запуск команды менеджера.
Строка crontab будет выглядеть примерно так
```bash
* * * * * /home/bitrix/vendor/bin/bxconsole system:cron > /var/www/log/bx_cron.log 2>&1
```

Далее нужно задать расписание выполнения для каждой команды. Это делается с помощью аннотаций. С помощью аннотаций 
также можно задавать основные параметры команды.

```php
/**
 * @Command(
 *     name="export:google-merchant",
 *     description="Google Merchant export"
 * )
 * @Agent(
 *     period=10800
 * )
 */
class Merchant extends BxCommand {

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /*
         * Если используете https://github.com/itrukhin/bxmonolog
         */
        $this->setLogger(new \App\Log('export/google'));

        //TODO: build and write merchant XML
    }
}
```
[код примера](../examples/merchant.php)

Аннотация App\BxConsole\Annotations\Agent принимает только один параметр - интервал запуска в секундах.
Аннотация App\BxConsole\Annotations\Command позволяет указать имя, описание и подсказку для команды.

---
[к содержанию](README.md)
# Консольные команды и выполнение команд по расписанию для 1С-Битрикс

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/itrukhin/bxconsole/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/itrukhin/bxconsole/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/itrukhin/bxconsole/badges/build.png?b=master)](https://scrutinizer-ci.com/g/itrukhin/bxconsole/build-status/master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/itrukhin/bxconsole/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)

Расширение предоставляет возможность подключения ядра 1С-Битрикс при выполнении консольных команд.
Также реализовано выполнение команд по расписанию, через вызов менеджера команд кроном.
За основу взят компонент [symfony/console](https://github.com/symfony/console). Чтобы удобно задавать необходимые
параметры выполенния по расписанию и собственно параметры консольных команд - используются аннотации
и парсер [doctrine/annotations](https://github.com/doctrine/annotations). Для предотращения множественных запусков
одной команды используется компонент [symfony/lock](https://github.com/symfony/lock)

Хочу поблагодарить разрабочиков [Console Jedi](https://github.com/notamedia/console-jedi) за хороший код и документацию,
которые очень помогли в создании этого расширения. Мне нужно было в первую очередь реализовать множество агентов,
но в отличии от Console Jedi, я не стал нагружать Битрикс дополнительными агентами, пусть и через команды,
а сделал независимый планировщик

## [Документация](doc/README.md)

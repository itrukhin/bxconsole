<?php
namespace App\BxConsole;

use Psr\Log\LoggerInterface;

class EnvHelper {

    const CRON_TAB_FILE = '/bitrix/tmp/bx_crontab.json';

    const SWITCH_STATE_ON = 'on';
    const SWITCH_STATE_OFF = 'off';

    const BX_CRONTAB_SINGLE_MODE = false;
    const BX_CRONTAB_TIMEOUT = 600;
    const BX_CRONTAB_PERIOD = 60;
    const BX_CRONTAB_TIMEZONE = 'Europe/Moscow';

    public static function loadEnv() {

        $envFile = realpath(__DIR__ . '/../../../../.env');
        if(!is_file($envFile)) {
            $envFile = realpath(__DIR__ . '/../../../../../.env');
        }
        if(is_file($envFile)) {
            try {
                $env = new \Symfony\Component\Dotenv\Dotenv();
                $env->load($envFile);
            } catch (\Exception $e) {

            }
        }
    }

    /**
     * @return string
     */
    public static function getDocumentRoot() {

        if(isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT'])) {
            return $_SERVER['DOCUMENT_ROOT'];
        }

        if(isset($_ENV['APP_DOCUMENT_ROOT']) && is_dir($_ENV['APP_DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = $_ENV['APP_DOCUMENT_ROOT'];
            return $_SERVER['DOCUMENT_ROOT'];
        }

        $composerFile = realpath(__DIR__ . '/../../../../composer.json');
        if(is_file($composerFile)) {
            $composerConfig = json_decode(file_get_contents($composerFile), true);
            if(isset($composerConfig['extra']['document-root']) && is_dir($composerConfig['extra']['document-root'])) {
                $_SERVER['DOCUMENT_ROOT'] = $composerConfig['extra']['document-root'];
                return $_SERVER['DOCUMENT_ROOT'];
            }
        }

        $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../../../');

        return (string) $_SERVER['DOCUMENT_ROOT'];
    }

    /**
     * @param $channel
     * @return false|LoggerInterface
     */
    public static function getLogger($channel) {

        if(isset($_ENV['APP_LOG_CLASS']) && class_exists($_ENV['APP_LOG_CLASS'])) {
            $logClass = $_ENV['APP_LOG_CLASS'];
            $log = new $logClass($channel);
            if($log instanceof LoggerInterface) {
                return $log;
            }
        }

        return false;
    }

    /**
     * @return mixed|string
     */
    public static function getCrontabFile() {

        if(isset($_ENV['BX_CRONTAB_FOLDER']) && $_ENV['BX_CRONTAB_FOLDER']) {
            return rtrim($_ENV['BX_CRONTAB_FOLDER'], "/") . '/bx_crontab.json';
        }

        return self::getDocumentRoot() . self::CRON_TAB_FILE;
    }

    public static function getCrontabTimeout() {

        if(isset($_ENV['BX_CRONTAB_FOLDER']) && is_numeric($_ENV['BX_CRONTAB_FOLDER'])) {
            return (int) $_ENV['BX_CRONTAB_FOLDER'];
        }

        return self::BX_CRONTAB_TIMEOUT;
    }

    public static function getBxCrontabPeriod() {

        if(isset($_ENV['BX_CRONTAB_PERIOD']) && is_numeric($_ENV['BX_CRONTAB_PERIOD'])) {
            return (int) $_ENV['BX_CRONTAB_PERIOD'];
        }

        return self::BX_CRONTAB_PERIOD;
    }

    public static function getSwitch($name, $state) {

        if(isset($_ENV[$name])) {

            $val = strtolower(trim($_ENV[$name]));
            if($state == self::SWITCH_STATE_ON) {
                if($val === self::SWITCH_STATE_ON || $val === '1' || $val === 'true') {
                    return true;
                }
            } else if($state == self::SWITCH_STATE_OFF) {
                if($val === self::SWITCH_STATE_OFF || $val === '0' || $val === 'false') {
                    return true;
                }
            }
        }

        return false;
    }

    public static function timeZoneSet() {

        $timeZone = self::BX_CRONTAB_TIMEZONE;

        if(isset($_ENV['BX_CRONTAB_TIMEZONE']) && $_ENV['BX_CRONTAB_TIMEZONE']) {
            $timeZone = trim($_ENV['BX_CRONTAB_TIMEZONE']);
        }

        date_default_timezone_set($timeZone);
    }

    public static function checkSleepInterval() {

        if(isset($_ENV['BX_CRONTAB_SLEEP_TIME']) && $_ENV['BX_CRONTAB_SLEEP_TIME']) {
            $intervals = explode(',', $_ENV['BX_CRONTAB_SLEEP_TIME']);
            foreach($intervals as $interval) {
                $times = explode('-', $interval);
                if(count($times) != 2) {
                    continue;
                }
                $minTime = Time24::validateTimeString($times[0]);
                $maxTime = Time24::validateTimeString($times[1]);
                if($minTime && $maxTime) {
                    if(Time24::inInterval($minTime, $maxTime)) {
                        return $interval;
                    }
                }
            }
        }

        return false;
    }
}
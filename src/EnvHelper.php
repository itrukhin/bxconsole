<?php
namespace App\BxConsole;

use Psr\Log\LoggerInterface;

class EnvHelper {

    const CRON_TAB_FILE = '/bitrix/tmp/bx_crontab.json';

    const SWITCH_STATE_ON = 'on';
    const SWITCH_STATE_OFF = 'off';

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
     * @return false|string
     */
    public static function getDocumentRoot() {

        $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../../../');

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

        return $_SERVER['DOCUMENT_ROOT'];
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
}
<?php
namespace App\BxConsole;

use Psr\Log\LoggerInterface;

class EnvHelper {

    const CRON_TAB_FILE = '/bitrix/tmp/.bx_crontab.php';

    /**
     *
     */
    public static function loadEnv() {

        if(class_exists('\Symfony\Component\Dotenv\Dotenv')) {
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
    }

    /**
     * @return false|mixed|string
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

    public static function getBinPath() {

        if(isset($_ENV['BX_CONSOLE_BIN']) && is_dir($_ENV['BX_CONSOLE_BIN'])) {
            return $_ENV['BX_CONSOLE_BIN'];
        }

        return realpath(__DIR__ . '/../../../bin');
    }

    /**
     * @param $name
     * @return LoggerInterface|false
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

        if(isset($_ENV['BX_CONSOLE_CRONTAB']) && $_ENV['BX_CONSOLE_CRONTAB']) {
            return $_ENV['BX_CONSOLE_CRONTAB'];
        }

        return self::getDocumentRoot() . self::CRON_TAB_FILE;
    }
}
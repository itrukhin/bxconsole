<?php
namespace App\BxConsole\Bitrix;

use Bitrix\Main\ModuleManager;

class Loader {

    /**
     * @return bool
     */
    public function initializeBitrix() {

        if($this->checkBitrix()) {

            if (defined('B_PROLOG_INCLUDED') && B_PROLOG_INCLUDED === true) {
                return true;
            }

            /**
             * Declare global legacy variables
             *
             * Including kernel here makes them local by default but some modules depend on them in installation class
             */
            global
            /** @noinspection PhpUnusedLocalVariableInspection */
            $DB, $DBType, $DBHost, $DBLogin, $DBPassword, $DBName, $DBDebug, $DBDebugToFile, $APPLICATION, $USER, $DBSQLServerType;

            define('NO_KEEP_STATISTIC', 'Y');
            define('NO_AGENT_STATISTIC', 'Y');
            define('NO_AGENT_CHECK', true);
            define('NOT_CHECK_PERMISSIONS', true);
            require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

            if (defined('B_PROLOG_INCLUDED') && B_PROLOG_INCLUDED === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if Bitrix kernel exists
     * @return bool
     */
    public function checkBitrix() {

        return is_file($_SERVER['DOCUMENT_ROOT'] . '/bitrix/.settings.php');
    }

    /**
     * Load commands from specific cli.php
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    public function getModulesCommands()
    {
        $commands = [];

        foreach (ModuleManager::getInstalledModules() as $module) {

            $cliFile = getLocalPath('modules/' . $module['ID'] . '/cli.php');

            if(!$cliFile) {
                continue;
            }

            \Bitrix\Main\Loader::includeModule($module['ID']);
            $config = include $_SERVER['DOCUMENT_ROOT'] . $cliFile;

            if (is_array($config['commands']) && count($config['commands']) > 0) {
                $commands = array_merge($commands, $config['commands']);
            }
        }

        return $commands;
    }
}
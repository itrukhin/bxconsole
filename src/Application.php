<?php
namespace App\BxConsole;

use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends \Symfony\Component\Console\Application {

    const VERSION = '1.0.0';

    private $isBitrixLoaded;

    public function __construct($name = 'BxConsole', $version = self::VERSION)
    {
        parent::__construct($name, $version);

        $this->getDocumentRoot();
        $this->isBitrixLoaded = $this->initializeBitrix();

        foreach($this->getModulesCommands() as $command) {
            $this->add($command);
        }
    }

    public function doRun(InputInterface $input, OutputInterface $output) {

        $exitCode = parent::doRun($input, $output);

        if($this->isBitrixLoaded) {
            if ($this->getCommandName($input) === null) {
                $output->writeln(PHP_EOL . sprintf('Using Bitrix <info>kernel v%s</info>.</info>', SM_VERSION),
                    OutputInterface::VERBOSITY_VERY_VERBOSE);
            }

        } else {
            $output->writeln(PHP_EOL . sprintf('<error>No Bitrix kernel found in %s.</error> ' .
                    'Please set DOCUMENT_ROOT value in composer.json <info>{"extra":{"document-root":DOCUMENT_ROOT}}</info>', $this->getDocumentRoot()));
        }

        return $exitCode;
    }

    /**
     * @return bool
     */
    protected function initializeBitrix() {

        if($this->checkBitrix()) {

            /**
             * Declare global legacy variables
             *
             * Including kernel here makes them local by default but some modules depend on them in installation class
             */
            global
            /** @noinspection PhpUnusedLocalVariableInspection */
            $DB, $DBType, $DBHost, $DBLogin, $DBPassword, $DBName, $DBDebug, $DBDebugToFile, $APPLICATION, $USER, $DBSQLServerType;

            /*
             * Clean $_ENV for run \Symfony\Component\Dotenv\Dotenv()
             */
            $env = [];
            if(is_array($_ENV)) {
                $env = $_ENV;
                unset($_ENV);
            }

            require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

            // restore $_ENV variables
            if(is_array($_ENV)) {
                $_ENV = array_merge($_ENV, $env);
            } else if(!empty($env)) {
                $_ENV = $env;
            }

            if (defined('B_PROLOG_INCLUDED') && B_PROLOG_INCLUDED === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return false|mixed|string
     */
    protected function getDocumentRoot() {

        $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../../../');
        if(!$this->checkBitrix()) {
            $composerFile = realpath(__DIR__ . '/../../../../composer.json');
            if(is_file($composerFile)) {
                $composerConfig = json_decode(file_get_contents($composerFile), true);
                if(isset($composerConfig['extra']['document-root'])) {
                    $_SERVER['DOCUMENT_ROOT'] = $composerConfig['extra']['document-root'];
                }
            }
        }
        $DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];

        return $DOCUMENT_ROOT;
    }

    /**
     * Check if Bitrix kernel exists
     * @return bool
     */
    protected function checkBitrix() {

        return is_file($_SERVER['DOCUMENT_ROOT'] . '/bitrix/.settings.php');
    }

    /**
     * Load commands from specific cli.php
     * @return array
     * @throws \Bitrix\Main\LoaderException
     */
    protected function getModulesCommands()
    {
        $commands = [];

        foreach (ModuleManager::getInstalledModules() as $module) {

            $cliFile = getLocalPath('modules/' . $module['ID'] . '/cli.php');

            if(!$cliFile) {
                continue;
            }

            $config = include_once $this->getDocumentRoot() . $cliFile;

            if (is_array($config['commands']) && count($config['commands']) > 0) {
                Loader::includeModule($module['ID']);
                $commands = array_merge($commands, $config['commands']);
            }
        }

        return $commands;
    }
}
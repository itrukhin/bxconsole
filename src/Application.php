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

        $this->loadEnv();

        $this->getDocumentRoot();

        $loader = new \App\BxConsole\Bitrix\Loader();

        $this->isBitrixLoaded = $loader->initializeBitrix();

        if($this->isBitrixLoaded) {
            foreach($loader->getModulesCommands() as $command) {
                $this->add($command);
            }
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

    protected function loadEnv() {

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
    protected function getDocumentRoot() {

        $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../../../');

        if(isset($_ENV['APP_DOCUMENT_ROOT']) && is_dir($_ENV['APP_DOCUMENT_ROOT'])) {
            $DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'] = $_ENV['APP_DOCUMENT_ROOT'];
            return $DOCUMENT_ROOT;
        }

        $composerFile = realpath(__DIR__ . '/../../../../composer.json');
        if(is_file($composerFile)) {
            $composerConfig = json_decode(file_get_contents($composerFile), true);
            if(isset($composerConfig['extra']['document-root']) && is_dir($composerConfig['extra']['document-root'])) {
                $DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'] = $composerConfig['extra']['document-root'];
                return $DOCUMENT_ROOT;
            }
        }

        $DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];

        return $DOCUMENT_ROOT;
    }
}
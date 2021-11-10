<?php
namespace App\BxConsole;

use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends \Symfony\Component\Console\Application {

    const VERSION = '1.0.0';

    private $isBitrixLoaded;
    
    private $bitrixCommands = [];

    public function __construct($name = 'BxConsole', $version = self::VERSION)
    {
        parent::__construct($name, $version);

        EnvHelper::loadEnv();
        EnvHelper::getDocumentRoot();

        $loader = new \App\BxConsole\Bitrix\Loader();

        $this->isBitrixLoaded = $loader->initializeBitrix();

        if($this->isBitrixLoaded) {
            foreach($loader->getModulesCommands() as $command) {
                $this->add($command);
            }
        }

        $this->add(new Cron());
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
    public function isBitrixLoaded(): bool
    {
        return $this->isBitrixLoaded;
    }

    /**
     * @return string
     */
    public function getDocumentRoot() {

        return (EnvHelper::getDocumentRoot() ?: '');
    }
}
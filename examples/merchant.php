<?php
namespace Alns\Main\Cli\Export\Google;

use App\BxConsole\Annotations\Agent;
use App\BxConsole\Annotations\Command;
use App\BxConsole\BxCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
<?php
namespace App;

use Symfony\Component\Console\Command\Command;

class BxCommand extends Command {

    /**
     * @return BxConsoleApp
     */
    public function getApplication()
    {
        return parent::getApplication();
    }
}
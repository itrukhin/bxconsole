<?php
namespace App\BxConsole;

class Command extends \Symfony\Component\Console\Command\Command {

    /**
     * @return Application
     */
    public function getApplication()
    {
        return parent::getApplication();
    }

    public function getDocumentRoot() {

        return $this->getApplication()->getDocumentRoot();
    }
}
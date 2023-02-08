<?php
namespace App\BxConsole\Format;

use Psr\Log\LoggerInterface;

trait XlsCommand {

    public function getXlsFile() {

        return $this->getDocumentRoot() . static::FINAL_FOLDER . static::XLS_FILE;
    }

    protected function getTmpXlsFile() {

        return $this->getDocumentRoot() . static::TMP_FOLDER . static::XLS_FILE;
    }

    public function writeXls($html) {

        $htmlFile = str_replace('.xls', '.html', static::XLS_FILE);
        $htmlRes = file_put_contents($this->getDocumentRoot() . static::TMP_FOLDER . $htmlFile, $html);

        if($htmlRes) {

            //TODO: check size !!!
            chdir($this->getDocumentRoot() . static::TMP_FOLDER);
            $command = sprintf("libreoffice --calc --convert-to xls %s", $htmlFile);
            exec($command);
            if(file_exists($this->getTmpXlsFile())) {
                unlink($this->getDocumentRoot() . static::TMP_FOLDER . $htmlFile);
                $command = sprintf("mv %s %s", $this->getTmpXlsFile(), $this->getXlsFile());
                exec($command);
                return filesize($this->getXlsFile());
            }

        } else {
            /** @var LoggerInterface $logger */
            $logger = $this->getLogger();
            if($logger) {
                $logger->error($this->getName() . ' ERROR: unable write file!');
            }
        }

        return 0;
    }
}

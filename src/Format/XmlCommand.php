<?php
namespace App\BxConsole\Format;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait XmlCommand {

    public function getXmlFile($archive = true) {

        $path = $this->getDocumentRoot() . static::FINAL_FOLDER . static::XML_FILE;
        if(static::GZIP === true && $archive) {
            $path .= '.gz';
        }
        return $path;
    }

    protected function getTmpXmlFile($archive = true) {

        $path = $this->getDocumentRoot() . static::TMP_FOLDER . static::XML_FILE;
        if(static::GZIP === true && $archive) {
            $path .= '.gz';
        }
        return $path;
    }

    public function writeXml($xml) {

        @unlink($this->getTmpXmlFile(false));
        $res = file_put_contents($this->getTmpXmlFile(false), $xml);
        if(static::GZIP) {
            unlink($this->getTmpXmlFile());
            chdir(pathinfo($this->getTmpXmlFile(), PATHINFO_DIRNAME));
            exec("gzip " . self::XML_FILE);
        }

        if($res) {

            //TODO: check size !!!
            $command = sprintf("mv %s %s", $this->getTmpXmlFile(), $this->getXmlFile());
            exec($command);

        } else {
            /** @var LoggerInterface $logger */
            $logger = $this->getLogger();
            if($logger) {
                $logger->error($this->getName() . ' ERROR: unable write file!');
            }
        }

        return $res;
    }
}

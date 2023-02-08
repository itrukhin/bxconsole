<?php
namespace App\BxConsole\Format;

interface XmlCommandInterface {

    function getXmlFile();

    function writeXml($xml);
}

<?php
namespace App\BxConsole\Format;

interface XlsCommandInterface {

    function getXlsFile();

    function writeXls($xls);
}

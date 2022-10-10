<?php
namespace App\BxConsole;

use Symfony\Component\Console\Command\Command;

trait AsyncTrait {

    public static function asyncRun($logFile = '') {

        $binPath = EnvHelper::getBinPath();
        /** @var Command $command */
        $command = new self();

        if(empty($logFile)) {
            $logFile = '/dev/null';
        } else {
            $logFile = $_ENV['APP_CMD_LOG_PATH'] . $logFile;
        }

        $cmd = sprintf("nohup %s %s > %s 2>&1 &", $binPath, $command->getName(), $logFile);

        exec($cmd);
    }
}
<?php
namespace App\BxConsole;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\BxConsole\Annotations\Agent;

class Cron extends BxCommand {

    use LockableTrait;

    const EXEC_STATUS_SUCCESS = 'SUCCESS';
    const EXEC_STATUS_ERROR = 'ERROR';
    const EXEC_STATUS_WORK = 'WORK';

    const SORT_NAME = 'name';
    const SORT_TIME = 'time';

    private $minAgentPeriod;

    protected function configure() {

        $this->setName('system:cron')
            ->setDescription('Job sheduler for application comands')
            ->addOption('status', 's', InputOption::VALUE_NONE, 'Show BX_CRONTAB status table')
            ->addOption('bytime', 't', InputOption::VALUE_NONE, 'Sort status table by exec time desc')
            ->addOption('clean', 'c', InputOption::VALUE_REQUIRED, 'Command to be clean crontab data (status, last exec)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Command to be clean all crontab data (status, last exec)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        set_time_limit(EnvHelper::getCrontabTimeout());

        $logger = EnvHelper::getLogger('bx_cron');
        if($logger) {
            $this->setLogger($logger);
        }

        $showStatus = $input->getOption('status');
        $byTime = $input->getOption('bytime');
        if($showStatus) {
            $sort = ($byTime ? self::SORT_TIME : self::SORT_NAME);
            $this->showStatus($output, $sort);
            return 0;
        }

        if(EnvHelper::getSwitch('BX_CRONTAB_RUN', EnvHelper::SWITCH_STATE_OFF)) {
            if($this->logger) {
                $this->logger->alert('BxCron switch off');
            }
            return 0;
        }

        if(!$this->lock()) {
            $msg = 'The command is already running in another process.';
            $output->writeln($msg);
            if($this->logger) {
                $this->logger->warning($msg);
            }
            return 0;
        }

        if($sleepInterval = EnvHelper::checkSleepInterval()) {
            $msg = sprintf("Sleep in interval %s", $sleepInterval);
            $output->writeln($msg);
            if($this->logger) {
                $this->logger->warning($msg);
            }
            return 0;
        }

        $clean = $input->getOption('clean');
        if($clean) {
            $command = $this->getApplication()->find($clean);
            $this->cleanJob($command->getName());
            $output->writeln($command->getName() . " will be executed now");
            return 0;
        }

        $cleanAll = $input->getOption('all');
        if($cleanAll) {
            $this->cleanJob();
            $output->writeln("All commands will be executed now");
            return 0;
        }

        $this->executeJobs($output);

        $this->release();

        return 0;
    }

    protected function showStatus(OutputInterface $output, $sort) {

        $table = new Table($output);
        $table->setStyle('box-double');

        $isSwitchOff = EnvHelper::getSwitch('BX_CRONTAB_RUN', EnvHelper::SWITCH_STATE_OFF);

        $jobs = $this->getCronJobs();
        $this->sortCronTab($jobs, $sort);
        $lastExec = 0;
        $hasError = false;

        foreach($jobs as $cmd => $job) {
            $execTime = $job['last_exec'];
            if($execTime > $lastExec) $lastExec = $execTime;
            if(!empty($job['error'])) {
                $hasError = true;
            }
        }

        $headStr = sprintf(
            "BX_CRONTAB_RUN: %s;  LAST_EXEC: %s;  AGENTS_COUNT: %d",
            ($isSwitchOff ? 'OFF' : 'ON'),
            ($lastExec ? date("d.m.Y H:i:s", $lastExec) : 'NONE'),
            count($jobs),
        );

        $header = [
            'Command',
            'Period',
            'Last Exec',
            'Status',
        ];

        if($hasError) {
            $header[] = 'Error';
        }

        $table->setHeaders([
            [new TableCell($headStr, ['colspan' => ($hasError ? 5 : 4)])],
            $header,
        ]);

        $cnt = 1;
        foreach($jobs as $cmd => $job) {
            if($cnt > 1) $table->addRow(new TableSeparator());
            $row = [
                $cmd,
                $job['period'],
                ($job['last_exec'] ? date("d.m.Y H:i:s", $job['last_exec']) : 'NONE'),
                $job['status'],
            ];
            if($hasError) {
                $row[] = $job['error'];
            }
            $table->addRow($row);
            $cnt++;
        }

        $table->render();
    }

    protected function cleanJob($command = false) {

        $crontab = [];

        if($command) {
            $crontab = $this->getCronTab();
            if($crontab === false) {
                return false;
            }
            unset($crontab[$command]);
        }

        $this->setCronTab($crontab);
    }

    protected function executeJobs(OutputInterface $output) {

        $jobs = $this->getCronJobs();

        if(is_array($jobs) && !empty($jobs)) {

            /*
             * Минимально допустимый период выполнения одной задачи
             * при котором гарантируется выполнение всех задач
             */
            $this->minAgentPeriod = (count($jobs) + 1) * EnvHelper::getBxCrontabPeriod();

            foreach($jobs as $cmd => $job) {

                if($this->isActualJob($job)) {

                    $job['status'] = self::EXEC_STATUS_WORK;
                    $this->updaateJob($cmd, $job);

                    $command = $this->getApplication()->find($cmd);
                    $cmdInput = new ArrayInput(['command' => $cmd]);
                    try {

                        $timeStart = microtime(true);
                        $returnCode = $command->run($cmdInput, $output);

                        if(!$returnCode) {

                            $job['status'] = self::EXEC_STATUS_SUCCESS;

                            $msg = sprintf("%s: SUCCESS [%.2f s]", $cmd, microtime(true) - $timeStart);
                            if($this->logger) {
                                $this->logger->alert($msg);
                            }
                            $output->writeln(PHP_EOL . $msg);

                        } else {

                            $job['status'] = self::EXEC_STATUS_ERROR;
                            $job['error_code'] = $returnCode;

                            $msg = sprintf("%s: ERROR [%.2f s]", $cmd, microtime(true) - $timeStart);
                            if($this->logger) {
                                $this->logger->alert($msg);
                            }
                            $output->writeln(PHP_EOL . $msg);
                        }

                    } catch (\Exception $e) {

                        $job['status'] = self::EXEC_STATUS_ERROR;
                        $job['error'] = $e->getMessage();


                        if($this->logger) {
                            $this->logger->error($e, ['command' => $cmd]);
                        }
                        $output->writeln(PHP_EOL . 'ERROR: ' . $e->getMessage());

                    } finally {

                        $job['last_exec'] = time();
                    }

                    $this->updaateJob($cmd, $job);
                    /*
                     * Let's do just one task
                     */
                    break;
                }
            } // foreach($jobs as $cmd => $job)
        } // if(!empty($jobs))
    }

    protected function isActualJob(&$job) {

        if($job['status'] == self::EXEC_STATUS_WORK) {
            return false;
        }

        $period = intval($job['period']);

        if($period > 0) {
            if($period < $this->minAgentPeriod) {
                $job['orig_period'] = $period;
                $period = $job['period'] = $this->minAgentPeriod;
            }
            if(time() - $job['last_exec'] >= $period) {
                return true;
            }
        } else if(!empty($job['times'])) {
            //TODO:
        }

        return false;
    }

    public function getCronJobs() {

        $crontab = $this->getCronTab();
        if($crontab === false) {
            return false;
        }

        /** @var Application $app */
        $app = $this->getApplication();

        $commands = $app->all();

        $selfCommands = [];
        foreach($commands as $command) {
            /** @var BxCommand $command */
            if($command instanceof BxCommand) {
                $name = $command->getName();
                $selfCommands[$name] = [
                    'object' => $command,
                ];
            }
        }

        $agents = [];
        $reader = new AnnotationReader();
        foreach($selfCommands as $cmd => $selfCommand) {
            $reflectionClass = new \ReflectionClass($selfCommand['object']);
            $annotations = $reader->getClassAnnotations($reflectionClass);

            foreach($annotations as $annotation) {
                if($annotation instanceof Agent) {
                    $agents[$cmd] = $annotation->toArray();
                }
            }
        }

        foreach($crontab as $cmd => $job) {
            if(is_array($job) && isset($agents[$cmd])) {
                $agents[$cmd] = array_merge($job, $agents[$cmd]);
            }
        }

        $this->setCronTab($agents);

        return $agents;
    }

    protected function updaateJob($cmd, $job) {

        return $this->updateCronTab([$cmd => $job]);
    }

    protected function updateCronTab(array $changedAgents) {

        $crontab = $this->getCronTab();

        if($crontab === false) {
            return false;
        } else {
            $crontab = array_merge($crontab, $changedAgents);
            return $this->setCronTab($crontab);
        }
    }

    protected function setCronTab(array $agents) {

        $isSuccess = true;
        $this->sortCronTab($agents);

        $filename = EnvHelper::getCrontabFile();

        $fh = fopen($filename, 'c');
        if (flock($fh, LOCK_EX)) {
            ftruncate($fh, 0);
            if(!fwrite($fh, json_encode($agents, JSON_PRETTY_PRINT))) {
                throw new \Exception('Unable to write BX_CRONTAB : ' . $filename);
            }
        } else {
            $isSuccess = false;
        }
        flock($fh, LOCK_UN);
        fclose($fh);

        return $isSuccess;
    }

    /**
     * @return array|false|mixed
     */
    protected function getCronTab() {

        $filename = EnvHelper::getCrontabFile();

        $fh = fopen($filename, 'r');
        if(!$fh) {
            return false;
        }
        if(flock($fh, LOCK_SH)) {
            $cronTab = [];
            $filesize = (int) filesize($filename);
            if($filesize > 0 && $data = fread($fh, $filesize)) {
                $decoded = json_decode($data, true);
                if(is_array($decoded)) {
                    $cronTab = $decoded;
                }
            }
        } else {
            $cronTab = false;
        }
        flock($fh, LOCK_UN);
        fclose($fh);

        return $cronTab;
    }

    protected function sortCronTab(array &$crontab, $sort = self::SORT_NAME) {

        if($sort == self::SORT_TIME) {
            $sorting = [];
            foreach($crontab as $cmd => $data) {
                $sorting[$cmd] = $data['last_exec'];
            }
            arsort($sorting, SORT_NUMERIC);
            $sorted = [];
            foreach($sorting as $cmd => $time) {
                $sorted[$cmd] = $crontab[$cmd];
            }
            $crontab = $sorted;
        } else {
            ksort($crontab, SORT_STRING);
        }
    }
}
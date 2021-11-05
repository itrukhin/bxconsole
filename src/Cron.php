<?php
namespace App\BxConsole;

use Bitrix\Main\Type\DateTime;
use Doctrine\Common\Annotations\AnnotationReader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\BxConsole\Annotations\Agent;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Process\Process;

class Cron extends BxCommand {

    const EXEC_STATUS_SUCCESS = 'SUCCESS';
    const EXEC_STATUS_ERROR = 'ERROR';
    /**
     * Глобальный таймаут запуска процесса.
     * Устанавливает TTL блокировки
     */
    const EXEC_TIMEOUT = 600;

    /**
     * Период запуска задач кроном
     */
    const BX_CRON_PERIOD = 60;

    /** @var LoggerInterface $log */
    private $log;

    private $minAgentPeriod = self::BX_CRON_PERIOD;

    protected function configure() {

        $this->setName('system:cron')
            ->setDescription('Job sheduler for application comands');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->log = EnvHelper::getLogger('bx_cron');

        $jobs = $this->getCronJobs();

        /*
         * Минимально допустимый период выполнения одной задачи
         * при котором гарантируется выполнение всех задач
         */
        $this->minAgentPeriod = (count($jobs) + 1) * self::BX_CRON_PERIOD;

        $workedJobs = [];

        if(!empty($jobs)) {

            $lockStore = new FlockStore(pathinfo(EnvHelper::getCrontabFile(), PATHINFO_DIRNAME));
            $lockFactory = new LockFactory($lockStore);
            if($this->log) {
                $lockFactory->setLogger($this->log);
            }

            foreach($jobs as $cmd => $job) {

                if($this->isActualJob($job)) {

                    $lock = $lockFactory->createLock($this->getLockName($cmd), self::EXEC_TIMEOUT);
                    if($lock->acquire()) {

                        $workedJobs[$cmd] = $job;

                        $command = $this->getApplication()->find($cmd);
                        $cmdInput = new ArrayInput(['command' => $cmd]);
                        try {

                            $timeStart = microtime(true);
                            $returnCode = $command->run($cmdInput, $output);

                            if(!$returnCode) {

                                $workedJobs[$cmd]['status'] = self::EXEC_STATUS_SUCCESS;
                                $msg = sprintf("%s: SUCCESS [%.2f s]", $cmd, microtime(true) - $timeStart);
                                if($this->log) {
                                    $this->log->alert($msg);
                                }
                                $output->writeln(PHP_EOL . $msg);

                            } else {

                                $workedJobs[$cmd]['status'] = self::EXEC_STATUS_ERROR;
                                $workedJobs[$cmd]['error_code'] = $returnCode;
                                $msg = sprintf("%s: ERROR [%.2f s]", $cmd, microtime(true) - $timeStart);
                                if($this->log) {
                                    $this->log->alert($msg);
                                }
                                $output->writeln(PHP_EOL . $msg);
                            }

                        } catch (\Exception $e) {

                            $workedJobs[$cmd]['status'] = self::EXEC_STATUS_ERROR;
                            $workedJobs[$cmd]['error'] = $e->getMessage();
                            if($this->log) {
                                $this->log->error($e, ['command' => $cmd]);
                            }
                            $output->writeln(PHP_EOL . 'ERROR: ' . $e->getMessage());

                        } finally {

                            $workedJobs[$cmd]['last_exec'] = time();
                            $humanDate = new DateTime();
                            $workedJobs[$cmd]['last_date_time'] = $humanDate->toString();
                            $lock->release();
                        }

                        /*
                         * Выполняем только одну задачу
                         */
                        break;

                    } else {
                        if($this->log) {
                            $this->log->warning($cmd . " is locked");
                        }
                    }
                }
            }
        }

        $this->updateCronTab($workedJobs);
    }

    protected function getLockName($cmd) {

        return preg_replace('/[^a-z\d ]/i', '_', $cmd);
    }

    protected function isActualJob(&$job) {

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

    protected function getCronJobs() {

        /** @var Application $app */
        $app = $this->getApplication();

        $commands = $app->all();

        $selfCommands = [];
        foreach($commands as $command) {
            /** @var Command $command */
            if($command instanceof Command) {
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

        $crontab = $this->getCronTab();

        if(is_array($crontab)) {
            foreach($crontab as $cmd => $job) {
                if(is_array($job) && isset($agents[$cmd])) {
                    $agents[$cmd] = array_merge($job, $agents[$cmd]);
                }
            }
        } else {
            $this->setCronTab($agents);
        }

        return $agents;
    }

    protected function updateCronTab($changedAgents) {

        $crontab = $this->getCronTab();

        $crontab = array_merge($crontab, $changedAgents);

        $this->setCronTab($crontab);
    }


    protected function setCronTab(array $agents) {

        $filename = EnvHelper::getCrontabFile();

        $fh = fopen($filename, 'c');
        if (flock($fh, LOCK_EX)) {
            ftruncate($fh, 0);
            if(!fwrite($fh, json_encode($agents, JSON_PRETTY_PRINT))) {
                throw new \Exception('Unable to write BX_CRONTAB : ' . $filename);
            }
        }
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    /**
     * @return mixed|null
     */
    protected function getCronTab() {

        $cronTab = null;

        $filename = EnvHelper::getCrontabFile();

        $fh = fopen($filename, 'r');
        if(flock($fh, LOCK_SH)) {
            $data = @fread($fh, filesize($filename));
            $cronTab = json_decode($data, true);
        }
        flock($fh, LOCK_UN);
        fclose($fh);

        return $cronTab;
    }
}
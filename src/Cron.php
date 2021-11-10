<?php
namespace App\BxConsole;

use Bitrix\Main\Type\DateTime;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\BxConsole\Annotations\Agent;

class Cron extends BxCommand {

    use LockableTrait;

    const EXEC_STATUS_SUCCESS = 'SUCCESS';
    const EXEC_STATUS_ERROR = 'ERROR';
    const EXEC_STATUS_WORK = 'WORK';

    private $minAgentPeriod;

    protected function configure() {

        $this->setName('system:cron')
            ->setDescription('Job sheduler for application comands');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = EnvHelper::getLogger('bx_cron');
        if($logger) {
            $this->setLogger($logger);
        }

        if(EnvHelper::getSwitch('BX_CRONTAB_RUN', EnvHelper::SWITCH_STATE_OFF)) {
            if($this->logger) {
                $this->logger->alert('BxCron switch off');
            }
            return 0;
        }

        if(!$this->lock()) {
            $output->writeln("The command is already running in another process.");
            if($this->logger) {
                $this->logger->warning("The command is already running in another process.");
            }
            return 0;
        }

        $jobs = $this->getCronJobs();

        /*
         * Минимально допустимый период выполнения одной задачи
         * при котором гарантируется выполнение всех задач
         */
        $this->minAgentPeriod = (count($jobs) + 1) * EnvHelper::getBxCrontabPeriod();

        if(!empty($jobs)) {

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
                        $humanDate = new DateTime();
                        $job['last_date_time'] = $humanDate->toString();
                    }

                    $this->updaateJob($cmd, $job);
                    /*
                     * Let's do just one task
                     */
                    break;
                }
            } // foreach($jobs as $cmd => $job)
        } // if(!empty($jobs))

        $this->release();
    }

    protected function isActualJob(&$job) {

        if(isset($job['status']) && $job['status'] !== self::EXEC_STATUS_SUCCESS) {
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

    protected function getCronJobs() {

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

        $crontab = $this->getCronTab();

        if(is_array($crontab)) {
            foreach($crontab as $cmd => $job) {
                if(is_array($job) && isset($agents[$cmd])) {
                    $agents[$cmd] = array_merge($job, $agents[$cmd]);
                }
            }
        }

        $this->setCronTab($agents);

        return $agents;
    }

    protected function updaateJob($cmd, $job) {

        $this->updateCronTab([$cmd => $job]);
    }

    protected function updateCronTab(array $changedAgents) {

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
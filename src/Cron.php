<?php
namespace App\BxConsole;

use Doctrine\Common\Annotations\AnnotationReader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\BxConsole\Annotations\Agent;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Process\Process;

class Cron extends Command {

    const EXEC_STATUS_SUCCESS = 'SUCCESS';
    const EXEC_STATUS_ERROR = 'ERROR';

    /** @var LoggerInterface $log */
    private $log;

    protected function configure() {

        $this->setName('system:cron')
            ->setDescription('Job sheduler for application comands');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->log = EnvHelper::getLogger('bx_cron');
        
        $jobs = $this->getCronJobs();

        if(!empty($jobs)) {

            $lockStore = new FlockStore(pathinfo(EnvHelper::getCrontabFile(), PATHINFO_DIRNAME));
            $lockFactory = new LockFactory($lockStore);
            if($this->log) {
                $lockFactory->setLogger($this->log);
            }

            foreach($jobs as $cmd => $job) {

                if($this->isActualJob($job)) {

                    $lock = $lockFactory->createLock($this->getLockName($cmd));
                    if($lock->acquire()) {

                        $command = $this->getApplication()->find($cmd);
                        $cmdInput = new ArrayInput(['command' => $cmd]);
                        $returnCode = $command->run($cmdInput, $output);
                        if(!$returnCode) {
                            $jobs[$cmd]['status'] = self::EXEC_STATUS_SUCCESS;
                        } else {
                            $jobs[$cmd]['status'] = self::EXEC_STATUS_ERROR;
                            $jobs[$cmd]['error'] = $returnCode;
                        }
                        $jobs[$cmd]['last_exec'] = time();
                        $lock->release();

                    } else {
                        if($this->log) {
                            $this->log->warning($cmd . " is locked");
                        }
                    }
                }
            }
        }

        $this->setCronTab($jobs);

        $output->writeln(PHP_EOL . "Job sheduler for application comands");
    }

    protected function getLockName($cmd) {

        return preg_replace('/[^a-z\d ]/i', '', $cmd);
    }

    protected function isActualJob($job) {

        if($job['period']) {
            if(time() - $job['last_exec'] >= intval($job['period'])) {
                return true;
            }
        } else if(!empty($job['time'])) {
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
        foreach($crontab as $cmd => $job) {
            if(is_array($job) && isset($agents[$cmd])) {
                $agents[$cmd] = array_merge($job, $agents[$cmd]);
            }
        }

        return $agents;
    }

    /**
     * @param $agents
     * @throws \Exception
     */
    protected function setCronTab($agents) {

        $file = EnvHelper::getCrontabFile();

        if(!file_put_contents($file, '<?php return '.var_export($agents, true ).";\n")) {
            throw new \Exception('Unable to write ' . $file);
        }
    }

    /**
     * @return array
     */
    protected function getCronTab() {

        $cronTab = [];

        $file = EnvHelper::getCrontabFile();

        if(file_exists($file)) {
            $cronTab = include $file;
        }

        return (is_array($cronTab) ? $cronTab : []);
    }


}
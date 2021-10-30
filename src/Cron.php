<?php
namespace App\BxConsole;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\BxConsole\Annotations\Agent;

class Cron extends Command {

    const CRON_TAB_FILE = '/bitrix/tmp/.bx_crontab.php';

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

        $processes = [];

        if(!empty($jobs)) {
            foreach($jobs as $cmd => $job) {
                if($this->isActualJob($job)) {

                }
            }
        }

        $this->setCronTab($jobs);

        $output->writeln(PHP_EOL . "Job sheduler for application comands");
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

        $file = $this->getCrontabFile();

        if(!file_put_contents($file, '<?php return '.var_export($agents, true ).";\n")) {
            throw new \Exception('Unable to write ' . $file);
        }
    }

    /**
     * @return array
     */
    protected function getCronTab() {

        $cronTab = [];

        $file = $this->getCrontabFile();

        if(file_exists($file)) {
            $cronTab = include $file;
        }

        return (is_array($cronTab) ? $cronTab : []);
    }

    /**
     * @return mixed|string
     */
    protected function getCrontabFile() {

        if(isset($_ENV['BX_CONSOLE_CRONTAB']) && $_ENV['BX_CONSOLE_CRONTAB']) {
            return $_ENV['BX_CONSOLE_CRONTAB'];
        }

        return EnvHelper::getDocumentRoot() . self::CRON_TAB_FILE;
    }
}
<?php
namespace App\BxConsole;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\BxConsole\Annotations\Agent;

class Cron extends Command {

    const CRON_TAB_FILE = '/bitrix/tmp/.bx_crontab.php';
    const CRON_TAB_FILE_TIMEOUT = 5;

    protected function configure() {

        $this->setName('system:cron')
            ->setDescription('Job sheduler for application comands');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getCronJobs();

        $output->writeln(PHP_EOL . "Job sheduler for application comands");
    }

    protected function getCronJobs() {

        /** @var Application $app */
        $app = $this->getApplication();

        $commands = $app->all();

        $selfCommands = [];
        foreach($commands as $command) {
            if($command instanceof Command) {
                $class = get_class($command);
                $selfCommands[$class] = [
                    'object' => $command
                ];
            }
        }

        $reader = new AnnotationReader();
        foreach($selfCommands as $class => $selfCommand) {
            $reflectionClass = new \ReflectionClass($selfCommand['object']);
            $annotation = $reader->getClassAnnotations($reflectionClass);

            $debug = true;
        }

        $debug = true;
    }

    protected function setCronTab() {


    }

    /**
     * @return array
     */
    protected function getCronTab() {

        $cronTab = [];

        $this->getDocumentRoot();
        $file = $_SERVER['DOCUMENT_ROOT'] . CRON_TAB_FILE;

        if(file_exists($file)) {
            $cronTab = include $file;
        }

        return (is_array($cronTab) ? $cronTab : []);
    }
}
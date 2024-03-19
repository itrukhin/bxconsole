<?php
namespace App\BxConsole;

use App\BxConsole\Annotations\Command;
use Doctrine\Common\Annotations\AnnotationReader;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class BxCommand extends \Symfony\Component\Console\Command\Command implements LoggerAwareInterface {

    use LoggerAwareTrait;

    /**
     * @return Application
     */
    public function getApplication()
    {
        return parent::getApplication();
    }

    /**
     * @return string
     */
    public function getDocumentRoot() {

        return $this->getApplication()->getDocumentRoot();
    }

    /**
     * @param $annotationName
     * @return mixed|object|null
     * @throws \ReflectionException
     */
    public function getAnnotation($annotationName) {

        $class = \get_called_class();
        $r = new \ReflectionClass($class);
        $reader = new AnnotationReader();
        return $reader->getClassAnnotation($r, $annotationName);
    }

    /**
     * @throws \ReflectionException
     */
    protected function configure() {

        /** @var Command $annotation */
        $annotation = $this->getAnnotation(\App\BxConsole\Annotations\Command::class);

        if($annotation) {
            $this->setName($annotation->name);
            $this->setDescription($annotation->description);
            if($annotation->help) {
                $this->setHelp($annotation->help);
            }
        }
    }

    /**
     * @return \Psr\Log\LoggerInterface|null
     */
    protected function getLogger() {

        return $this->logger;
    }
}
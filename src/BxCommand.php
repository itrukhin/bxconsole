<?php
namespace App\BxConsole;

use App\BxConsole\Annotations\Command;
use Doctrine\Common\Annotations\AnnotationReader;

class BxCommand extends \Symfony\Component\Console\Command\Command {

    /**
     * @return Application
     */
    public function getApplication()
    {
        return parent::getApplication();
    }

    /**
     * @return false|string
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

    protected function configure() {

        /** @var Command $annotation */
        $annotation = $this->getAnnotation(\App\BxConsole\Annotations\Command::class);

        if($annotation) {
            $this->setName($annotation->name);
            $this->setDescription($annotation->description);
            $this->setHelp($annotation->help);
        }
    }
}
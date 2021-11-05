<?php
namespace App\BxConsole\Annotations;

/**
 * @Annotation
 * @Target("CLASS")
 */
class Command {

    /**
     * @Required
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $help;
}
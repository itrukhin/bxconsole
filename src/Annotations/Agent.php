<?php
namespace App\BxConsole\Annotations;

/**
 * @Annotation
 * @Target("CLASS")
 */
class Agent {

    /** @var integer */
    public $period;

    /** @var string */
    public $time;
}
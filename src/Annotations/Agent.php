<?php
namespace App\BxConsole\Annotations;

/**
 * @Annotation
 * @Target("CLASS")
 */
class Agent {

    /** @var integer */
    public $period;

    /**
     * Times of day in HH:MM format
     * @var string[]
     */
    public $times;

    /** @var integer */
    public $timeout = 0;

    /** @var integer $priority */
    public $priority = 100;

    public function toArray() {

        return [
            'period' => (int) $this->period,
            'time' => $this->time,
            'timeout' => $this->timeout,
        ];
    }
}
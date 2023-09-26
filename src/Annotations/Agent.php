<?php
namespace App\BxConsole\Annotations;

/**
 * @Annotation
 * @Target("CLASS")
 */
class Agent {

    /** @var integer */
    public int $period;

    /**
     * Times of day in HH:MM format
     * @var array<string>
     */
    public array $times = [];

    public string $interval = '';

//    /** @var integer */
//    public $timeout = 0;
//
//    /** @var integer $priority */
//    public $priority = 100;

    public function toArray() {

        return [
            'period' => (int) $this->period,
            'times' => $this->times,
            'interval' => $this->interval,
            //'timeout' => $this->timeout,
        ];
    }
}
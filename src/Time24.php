<?php
namespace App\BxConsole;

class Time24 {

    private int $hours = 0;
    private int $minutes = 0;
    private int $seconds = 0;

    public function __construct($time = '') {

        $time = trim($time);
        if(empty($time)) {
            \App\BxConsole\EnvHelper::timeZoneSet();
            $time = date("H:i:s");
        }
        $timeparts = array_map('intval', explode(':', $time));

        if(isset($timeparts[0])) $this->setHours($timeparts[0]);
        if(isset($timeparts[1])) $this->setMinutes($timeparts[1]);
        if(isset($timeparts[2])) $this->setSeconds($timeparts[2]);
    }

    /**
     * @param int $hours
     * @param int $minutes
     * @param int $seconds
     */
    public function set(int $hours, int $minutes = 0, int $seconds = 0): void
    {
        $this->setHours($hours);
        $this->setMinutes($minutes);
        $this->setSeconds($seconds);
    }

    public function toString($seconds = true): string
    {
        $timeparts = [];
        $timeparts[] = sprintf("%02d", $this->hours);
        $timeparts[] = sprintf("%02d", $this->minutes);
        if($seconds) {
            $timeparts[] = sprintf("%02d", $this->seconds);
        }
        return join(':', $timeparts);
    }

    /**
     * @return int
     */
    public function getHours(): int
    {
        return $this->hours;
    }

    /**
     * @return int
     */
    public function getMinutes(): int
    {
        return $this->minutes;
    }

    /**
     * @return int
     */
    public function getSeconds(): int
    {
        return $this->seconds;
    }

    /**
     * @param int $hours
     */
    public function setHours(int $hours): void
    {
        $hours = (int) $hours;
        if($hours < 0) {
            $hours = 0;
        } else if($hours > 23) {
            $hours = 23;
        }
        $this->hours = $hours;
    }

    /**
     * @param int $minutes
     */
    public function setMinutes(int $minutes): void
    {
        $minutes = (int) $minutes;
        if($minutes < 0) {
            $minutes = 0;
        } else if($minutes > 59) {
            $minutes = 59;
        }
        $this->minutes = $minutes;
    }

    /**
     * @param int $seconds
     */
    public function setSeconds(int $seconds): void
    {
        $seconds = (int) $seconds;
        if($seconds < 0) {
            $seconds = 0;
        } else if($seconds > 59) {
            $seconds = 59;
        }
        $this->seconds = $seconds;
    }

    public static function validateTimeString($str): ?string
    {
        $str = trim($str);
        if(strpos($str, ':') !== false) {
            $timeParts = explode(':', $str);
            if(count($timeParts) == 2) {
                $timeParts[2] = '00';
            }
            $str = join(':', $timeParts);
            if(preg_match("/^(?:2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]$/", $str)) {
                return $str;
            }
        }
        return null;
    }

    public static function inInterval($minStrTime, $maxStrTime, $checkStrTime = ''): bool
    {
        $minTime = new self($minStrTime);
        if($maxStrTime == '00:00') {
            $maxStrTime = '23:59';
        }
        $maxTime = new self($maxStrTime);
        $checkTime = new self($checkStrTime);

        if($minTime->getHours() == $checkTime->getHours()) {
            if($minTime->getMinutes() == $checkTime->getMinutes()) {
                if($minTime->getSeconds() > $checkTime->getSeconds()) {
                    return false;
                }
            } else if($minTime->getMinutes() > $checkTime->getMinutes()) {
                return false;
            }
        } else if($minTime->getHours() > $checkTime->getHours()) {
            return false;
        }

        if($maxTime->getHours() == $checkTime->getHours()) {
            if($maxTime->getMinutes() == $checkTime->getMinutes()) {
                if($minTime->getSeconds() < $checkTime->getSeconds()) {
                    return false;
                }
            } else if($maxTime->getMinutes() < $checkTime->getMinutes()) {
                return false;
            }
        } else if($maxTime->getHours() < $checkTime->getHours()) {
            return false;
        }

        return true;
    }
}
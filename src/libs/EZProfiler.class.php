<?php
class EZProfiler
{
    protected $_profileData = array();

    public function __construct() {
        //Maybe something to configure the profiler later
    }

    public function start($name, $desc = '') {
        $this->_profileData[$name] = array(
            'startTime' => microtime(true),
            'desc'      => $desc
        );
    }

    public function stop($name) {
        if (!isset($this->_profileData[$name])) {
            return;
        }
        $this->_profileData[$name]['stopTime'] = microtime(true);
        $this->_profileData[$name]['totalTime'] = round(($this->_profileData[$name]['stopTime'] - $this->_profileData[$name]['startTime']), 4) . ' second(s)';
    }

    public function get($name, $formatted = true, $stopIt = true) {
        if (!isset($this->_profileData[$name])) {
            throw new Exception('Invalid profiling key: "' . $name . '"');
        }
        if (!isset($this->_profileData[$name]['stopTime']) && $stopIt) {
            $this->stop($name);
        }
        else {
            return '(N/A)';
        }
        return $formatted ? $this->_profileData[$name]['totalTime'] : $this->_profileData[$name]['stopTime'] - $this->_profileData[$name]['startTime'];
    }

    public function dump() {
        echo "<pre>==PROFILE DATA==\n";
        print_r($this->_profileData);
        echo "</pre>";
    }
}
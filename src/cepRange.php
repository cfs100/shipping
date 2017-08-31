<?php

namespace shipping;

class cepRange extends instance
{
    protected $ranges = [];
    protected $destination;

    public function __construct($destination)
    {
        $this->destination = $this::validateCEP($destination);
        if (!$this->destination) {
            throw new \InvalidArgumentException('Please inform a valid destination CEP code');
        }
    }

    public static function validateCEP($value)
    {
        $match = [];
        $regex = '<^([0-9]{5})(-?[0-9]{3})?$>';
        if (!preg_match($regex, $value, $match)) {
            return false;
        }

        return $match[1];
    }

    public function addRange($start, $end, $time, $cost)
    {
        $range = [];
        foreach (['start', 'end'] as $field) {
            $cep = $this::validateCEP($$field);
            if (!$cep) {
                throw new \InvalidArgumentException("Please inform a valid {$field} CEP code");
            }

            $range[$field] = $cep;
        }

        $range['time'] = (integer) $time;
        $range['cost'] = (float) $cost;

        $this->ranges[] = $range;

        return $this;
    }

    public function getResult()
    {
        $result = [];

        foreach ($this->ranges as $range) {
            if ($range['start'] <= $this->destination && $range['end'] >= $this->destination) {
                $result[] = [
                    'time' => $range['time'],
                    'cost' => $range['cost'],
                    'raw' => $range,
                ];
            }
        }

        return $result;
    }
}

<?php

namespace shipping;

abstract class instance implements base
{
    public static function validateCEP($value)
    {
        $match = [];
        $regex = '<^([0-9]{5})-?([0-9]{3})$>';
        if (!preg_match($regex, $value, $match)) {
            return false;
        }

        return $match[1] . $match[2];
    }
}

<?php

namespace App\Utility;

use Exception;

class Validator
{
    /**
     * @throws Exception
     */
    private function __construct()
    {
        throw new Exception('Logger is Utility Class. Only static methods.');
    }

    public static function isUrlValid(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public static function isIpv4Valid(string $ipv4): bool
    {
        return filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    public static function isUuid(string $str): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $str) === 1;
    }
}
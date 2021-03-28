<?php

namespace App\Utility;

use Exception;

class Logger
{
    private const ERROR = 'Error';
    private const WARNING = 'Warning';
    private const INFO = 'Info';

    /**
     * @throws Exception
     */
    private function __construct()
    {
        throw new Exception('Logger is Utility Class. Only static methods.');
    }

    /**
     * here we can use any special logger system for project
     * @param array $log
     */
    private static function writeLog(array $log): void
    {
        error_log(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    public static function error(string $message): void
    {
        self::writeLog([
            'level' => self::ERROR,
            'message' => $message,
        ]);
    }

    public static function warning(string $message): void
    {
        self::writeLog([
            'level' => self::WARNING,
            'message' => $message,
        ]);
    }

    public static function info(string $message): void
    {
        self::writeLog([
            'level' => self::INFO,
            'message' => $message,
        ]);
    }

    public static function getExceptionMessage(Exception $exception): string
    {
        return get_class($exception) .
            ' (' . $exception->getFile() . ':' . $exception->getLine() . ' ): ' .
            $exception->getMessage();
    }
}
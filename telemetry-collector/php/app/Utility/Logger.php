<?php

namespace App\Utility;

use Exception;

class Logger
{
    private const ERROR = 'Error';
    private const WARNING = 'Warning';
    private const INFO = 'Info';
    private const DEBUG = 'Debug';

    private const MAX_MESSAGE_SIZE = 512;

    /**
     * @throws Exception
     */
    private function __construct()
    {
        // todo: we can use instances of the class and remember fields when creating it
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
            'message' => substr($message, 0, self::MAX_MESSAGE_SIZE),
        ]);
    }

    public static function warning(string $message): void
    {
        self::writeLog([
            'level' => self::WARNING,
            'message' => substr($message, 0, self::MAX_MESSAGE_SIZE),
        ]);
    }

    public static function info(string $message): void
    {
        self::writeLog([
            'level' => self::INFO,
            'message' => substr($message, 0, self::MAX_MESSAGE_SIZE),
        ]);
    }

    public static function debug(string $message): void
    {
        // todo: turn on from config
        if (false) {
            self::writeLog([
                'level' => self::DEBUG,
                'message' => substr($message, 0, self::MAX_MESSAGE_SIZE),
            ]);
        }
    }

    public static function getExceptionMessage(Exception $exception): string
    {
        return get_class($exception) .
            ' (' . $exception->getFile() . ':' . $exception->getLine() . ' ): ' .
            $exception->getMessage();
    }
}
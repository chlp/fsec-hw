<?php

namespace App\Utility;

class Config
{
    private const DEFAULT_AWS_REGION = 'eu-west-1';
    private const DEFAULT_AWS_VERSION = 'latest';
    private const DEFAULT_AWS_ENDPOINT = 'http://localhost:4566';
    private const DEFAULT_AWS_KEY = 'foo';
    private const DEFAULT_AWS_SECRET = 'bar';

    private const DEFAULT_SUBMISSIONS_QUEUE_NAME = 'wrong_queue_name';
    private const DEFAULT_QUEUE_MAX_NUMBER_OF_MESSAGE_PER_REQUEST = 10;
    private const DEFAULT_QUEUE_WAIT_TIME_SEC = 1;
    private const DEFAULT_QUEUE_VISIBILITY_TIMEOUT_SEC = 30;

    private function __construct()
    {
    }

    /**
     * create new Config instance and check the settings
     */
    public static function create(): self
    {
        $conf = new self();

        // try to use methods to check the settings
        $conf->getSubmissionsQueueName();
        $conf->getQueueMaxNumberOfMessagePerRequest();
        $conf->getQueueVisibilityTimeoutSec();
        $conf->getAwsRegion();
        $conf->getAwsVersion();
        $conf->getAwsEndpoint();
        $conf->getAwsKey();
        $conf->getAwsSecret();

        return $conf;
    }

    public function getAwsRegion(): string
    {
        return self::getStringFromEnv('AWS_REGION', self::DEFAULT_AWS_REGION);
    }

    public function getAwsVersion(): string
    {
        return self::getStringFromEnv('AWS_VERSION', self::DEFAULT_AWS_VERSION);
    }

    public function getAwsEndpoint(): string
    {
        return self::getStringFromEnv('AWS_ENDPOINT', self::DEFAULT_AWS_ENDPOINT);
    }

    public function getAwsKey(): string
    {
        return self::getStringFromEnv('AWS_KEY', self::DEFAULT_AWS_KEY);
    }

    public function getAwsSecret(): string
    {
        return self::getStringFromEnv('AWS_SECRET', self::DEFAULT_AWS_SECRET);
    }

    public function getSubmissionsQueueName(): string
    {
        return self::getStringFromEnv('SUBMISSIONS_QUEUE_NAME', self::DEFAULT_SUBMISSIONS_QUEUE_NAME);
    }

    public function getQueueMaxNumberOfMessagePerRequest(): int
    {
        return self::getIntFromEnv('QUEUE_MAX_NUMBER_OF_MESSAGE_PER_REQUEST', self::DEFAULT_QUEUE_MAX_NUMBER_OF_MESSAGE_PER_REQUEST);
    }

    public function getQueueWaitTimeSec(): int
    {
        return self::getIntFromEnv('QUEUE_WAIT_TIME_SEC', self::DEFAULT_QUEUE_WAIT_TIME_SEC);
    }

    public function getQueueVisibilityTimeoutSec(): int
    {
        return self::getIntFromEnv('QUEUE_VISIBILITY_TIMEOUT_SEC', self::DEFAULT_QUEUE_VISIBILITY_TIMEOUT_SEC);
    }

    /**
     * @param string $name
     * @param int $default
     * @return int
     */
    private static function getIntFromEnv(string $name, int $default): int
    {
        $num = getenv($name);
        if ($num === false || (string)(int)$num !== $num) {
            $num = $default;
            \App\Utility\Logger::info("wrong int env {$name}. Gonna use default: {$num}");
        }
        return (int)$num;
    }

    /**
     * @param string $name
     * @param string $default
     * @return string
     */
    private static function getStringFromEnv(string $name, string $default): string
    {
        $val = getenv($name);
        if (!is_string($val) || strlen($val) === 0) {
            $val = $default;
            \App\Utility\Logger::info("wrong string env {$name}. Gonna use default: {$val}");
        }
        return (string)$val;
    }
}
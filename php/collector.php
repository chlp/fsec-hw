<?php

require 'vendor/autoload.php';

class Logger
{
    private const ERROR = 'Error';
    private const WARNING = 'Warning';
    private const INFO = 'Info';

    /**
     * @throws Exception
     */
    function __construct()
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

use Aws\Sqs\SqsClient;

$queueName = "submissions";

$sqsClient = new SqsClient([
    'region' => 'eu-west-1',
    'version' => 'latest',
    'endpoint' => 'http://localhost:4566',
    'credentials' => [
        'key' => 'foo',
        'secret' => 'bar',
    ],
]);

function isUrlValid(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function getQueueUrl(SqsClient $sqsClient, string $queueName): ?string
{
    try {
        $result = $sqsClient->getQueueUrl([
            'QueueName' => $queueName
        ]);
        if (!isset($result['QueueUrl'])) {
            error_log("QueueUrl is not returned");
            return null;
        }
        $queueUrl = (string)$result['QueueUrl'];
        if (!isUrlValid($queueUrl)) {
            Logger::error("wrong QueueUrl is returned" . $queueUrl);
            return null;
        }
        return $queueUrl;
    } catch (Exception $e) {
        Logger::error(Logger::getExceptionMessage($e));
    }
    return null;
}

$queueUrl = getQueueUrl($sqsClient, $queueName);

try {
    echo "Wait...\n";
    $result = $sqsClient->receiveMessage(array(
        'AttributeNames' => ['SentTimestamp'],
        'MaxNumberOfMessages' => 1,
        'MessageAttributeNames' => ['All'],
        'QueueUrl' => $queueUrl,
        'WaitTimeSeconds' => 1,
    ));
    var_dump($result);
} catch (Exception $e) {
    Logger::error(Logger::getExceptionMessage($e));
}
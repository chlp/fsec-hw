<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/loader.php';

use Aws\Sqs\SqsClient;
use App\Telemetry\Message\TelemetryMessage;
use App\Utility\Logger;
use App\Utility\Validator;

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
        if (!Validator::isUrlValid($queueUrl)) {
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
    $result = $sqsClient->receiveMessage(array(
        'AttributeNames' => ['SentTimestamp'],
        'MaxNumberOfMessages' => 1,
        'MessageAttributeNames' => ['All'],
        'QueueUrl' => $queueUrl,
        'WaitTimeSeconds' => 1,
    ));
    if (!is_array($result['Messages'])) {
        Logger::error('sqs messages are not array');
        return null;
    }
    foreach ($result['Messages'] as $message) {
        $tmM = TelemetryMessage::createFromQueueMessage($message);
        echo $tmM->getMessageIdMark();
        exit;
    }
    exit;
} catch (Exception $e) {
    Logger::error(Logger::getExceptionMessage($e));
}
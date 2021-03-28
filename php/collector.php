<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/loader.php';

use Aws\Sqs\SqsClient;
use App\Queue\QueueService;
use App\Telemetry\Message\TelemetryMessage;
use App\Utility\Config;
use App\Utility\Logger;

//$queueName = "submissions";

$conf = Config::create();
$queueService = new \App\Queue\QueueService(
    $conf->getAwsRegion(),
    $conf->getAwsVersion(),
    $conf->getAwsEndpoint(),
    $conf->getAwsKey(),
    $conf->getAwsSecret(),
    $conf->getSubmissionsQueueName(),
    $conf->getQueueMaxNumberOfMessagePerRequest(),
    $conf->getQueueWaitTimeSec(),
    $conf->getQueueVisibilityTimeoutSec()
);

$messages = $queueService->receiveMessages();
if ($messages === null) {
    var_dump('fail');
    exit;
}
foreach ($messages as $message) {
    $tmM = TelemetryMessage::createFromQueueMessage($message);
    echo $tmM->getMessageIdMark();
    exit;
}
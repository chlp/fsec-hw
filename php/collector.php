<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/loader.php';

use App\Telemetry\Message\TelemetryMessage;
use App\Utility\Config;

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

$dataStreamService = new \App\Queue\DataStreamService(
    $conf->getAwsRegion(),
    $conf->getAwsVersion(),
    $conf->getAwsEndpoint(),
    $conf->getAwsKey(),
    $conf->getAwsSecret(),
    $conf->getEventsDataStreamName(),
    $conf->getDataStreamMaxBufferSize(),
    $conf->getDataStreamFlushIntervalSec()
);

while (true) {
    $dataStreamService->flushIfNeed();

    $messages = $queueService->receiveMessages(); // long polling wait
    if ($messages === null) {
        var_dump('fail');
        sleep(1);
        continue;
    }

    $receiptHandles = $queueService->getReceiptHandles($messages);

    foreach ($messages as $message) {
        $telemetryMessage = TelemetryMessage::createFromQueueMessage($message);
        if ($telemetryMessage !== null) {
            echo $telemetryMessage->getMessageIdMark() . "\n";
            $dataStreamService->putMessage(['id' => $telemetryMessage->getMessageIdMark()]);
        }
    }
    // todo: need to delete
}
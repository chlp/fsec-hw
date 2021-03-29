<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/loader.php';

use App\DataStream\DataStreamService;
use App\DataStream\Supervisor;
use App\Queue\QueueService;
use App\Telemetry\Message\TelemetryMessage;
use App\Utility\Config;
use App\Utility\Logger;

$conf = Config::create();

$queueService = new QueueService(
    $conf->getAwsRegion(),
    $conf->getAwsVersion(),
    $conf->getAwsEndpoint(),
    $conf->getAwsKey(),
    $conf->getAwsSecret(),
    $conf->getSubmissionsQueueName(),
    $conf->getQueueMaxNumberOfMessagePerRequest(),
    $conf->getQueueWaitTimeSec(),
    $conf->getQueueVisibilityTimeoutSec(),
    $conf->getQueueMaxReceiptsToDeleteAtOnce(),
    $conf->getQueueReceiptsToDeleteIntervalSec()
);

$dataStreamService = new DataStreamService(
    $conf->getAwsRegion(),
    $conf->getAwsVersion(),
    $conf->getAwsEndpoint(),
    $conf->getAwsKey(),
    $conf->getAwsSecret(),
    $conf->getEventsDataStreamName(),
    $conf->getDataStreamMaxBufferSize(),
    $conf->getDataStreamFlushIntervalSec()
);
Supervisor::createService($dataStreamService);

// todo: need to create other instances of this application, but need to prepare it: Supervisor & calculate the required amount of the app on every tick
while (true) {
    $isDataStreamFlushed = $dataStreamService->flushIfNeed();
    $isQueueDeleted = $queueService->deleteAccumulatedMessagesIfNeed();
    $queueMessages = $queueService->receiveMessages(); // long polling wait
    if ($queueMessages === null) {
        Logger::error("Collector error: can not receive messages, sleep for a sec.");
        sleep(1);
        continue;
    }

    if (!$isDataStreamFlushed && !$isQueueDeleted && count($queueMessages) === 0 && !$queueService->isLongPollingWait()) {
        usleep(1000); // sleep 0.1 sec. save cpu
    }

    foreach ($queueMessages as $queueMessage) {
        $telemetryMessage = TelemetryMessage::createFromQueueMessage($queueMessage);
        if ($telemetryMessage !== null) {
            $telemetryMessageIdMark = $telemetryMessage->getMessageIdMark();
            $receiptHandle = $queueService->getReceiptHandle($queueMessage);
            $onDeleteCallback = function () use ($receiptHandle, $queueService) {
                $queueService->deleteMessage($receiptHandle);
            };
            if (!$dataStreamService->putRecord(['id' => $telemetryMessageIdMark], $onDeleteCallback)) {
                Logger::error("Collector error: can not save message, skip it: " . $telemetryMessageIdMark);
            }
        }
    }
}
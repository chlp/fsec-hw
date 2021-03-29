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
$eventsCountPerMessage = [];
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
        usleep(100000); // sleep 0.1 sec. save cpu
    }

    $eventsCount = 0;
    foreach ($queueMessages as $queueMessage) {
        $telemetryMessage = TelemetryMessage::createFromQueueMessage($queueMessage);
        if ($telemetryMessage !== null) {
            $receiptHandle = $queueService->getReceiptHandle($queueMessage);
            $telemetryMessageIdMark = $telemetryMessage->getMessageIdMark();
            $telemetryMessageEvents = $telemetryMessage->getEvents();
            $eventsCount = count($telemetryMessageEvents);
            $eventsCountPerMessage[$receiptHandle] = $eventsCount;
            $afterSendingCallback = function () use ($receiptHandle, $queueService, &$eventsCountPerMessage) {
                $eventsCountPerMessage[$receiptHandle] -= 1;
                if ($eventsCountPerMessage[$receiptHandle] === 0) {
                    unset($eventsCountPerMessage[$receiptHandle]);
                    $queueService->deleteMessage($receiptHandle);
                }
            };
            foreach ($telemetryMessageEvents as $event) {
                if (!$dataStreamService->putRecord($event->toDataStreamRecord(), $afterSendingCallback)) {
                    $eventsCountPerMessage[$receiptHandle] -= 1;
                    Logger::error("Collector error: can not put record, skip it: " . $event->idMark());
                    // todo: if all of records from message we could not put. If we understand that we never could it. Need to delete this message from queue too.
                }
            }
        } else {
            $receiptHandle = $queueService->getReceiptHandle($queueMessage);
            $queueService->deleteMessage($receiptHandle);
        }
    }

    Logger::info("Collect " . count($queueMessages) . " more messages ({$eventsCount} events)");
}
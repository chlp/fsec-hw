<?php

namespace App\Queue;

use App\Utility\Supervisor;
use Aws\Kinesis\KinesisClient;
use App\Utility\Logger;
use Exception;

class DataStreamService
{
    /**
     * @var KinesisClient
     */
    private $kinesisClient;

    /**
     * @var string
     */
    private $streamName;

    /**
     * @var int
     */
    private $maxBufferSize;

    /**
     * @var int
     */
    private $maxBufferFlushIntervalSec;

    /**
     * @var int
     */
    private $lastFlushTimestamp;

    /**
     * @var array
     */
    private $recordsBuffer;

    /**
     * @var callable[]
     */
    private $toCallAfterSending;

    /**
     * DataStreamService constructor.
     * @param string $region
     * @param string $version
     * @param string $endpointUrl
     * @param string $key
     * @param string $secret
     * @param string $streamName
     * @param int $maxBufferSize
     * @param int $maxBufferFlushIntervalSec
     */
    public function __construct(
        string $region,
        string $version,
        string $endpointUrl,
        string $key,
        string $secret,
        string $streamName,
        int $maxBufferSize,
        int $maxBufferFlushIntervalSec
    )
    {
        $this->kinesisClient = new KinesisClient([
            'region' => $region,
            'version' => $version,
            'endpoint' => $endpointUrl,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);
        $this->streamName = $streamName;
        $this->maxBufferSize = $maxBufferSize;
        $this->maxBufferFlushIntervalSec = $maxBufferFlushIntervalSec;
        $this->recordsBuffer = [];
        $this->toCallAfterSending = [];
        $this->lastFlushTimestamp = 0;
    }

    /**
     * Putting message to the buffer before it would be sent to data stream
     * @param array $data
     */
    public function putMessage(array $data): void
    {
        $this->recordsBuffer[] = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->flushIfNeed();
    }

    public function putCallbackOnSuccessSending(callable $func): void
    {
        $this->toCallAfterSending[] = $func;
    }

    /**
     * flush the buffer if records count more than max or time has come
     */
    public function flushIfNeed(): void
    {
        if (
            count($this->recordsBuffer) >= $this->maxBufferSize ||
            time() - $this->lastFlushTimestamp > $this->maxBufferFlushIntervalSec
        ) {
            $this->flush();
        }
    }

    /**
     * flushing the buffer
     */
    private function flush(): void
    {
        if (count($this->recordsBuffer) === 0) {
            $this->lastFlushTimestamp = time();
            return;
        }

        if ($this->send()) {
            foreach ($this->toCallAfterSending as $callable) {
                try {
                    $callable();
                } catch (Exception $e) {
                    Logger::warning('DataStreamService::flush() callable exception: ' . Logger::getExceptionMessage($e));
                }
            }
            $this->lastFlushTimestamp = time();
            $this->recordsBuffer = [];
            $this->toCallAfterSending = [];
        }
    }

    private function send(): bool
    {
        $parameter = [
            'StreamName' => $this->streamName,
            'Records' => []
        ];

        $sizeOfRecordsToSend = 0;
        foreach ($this->recordsBuffer as $record) {
            $parameter['Records'][] = [
                'Data' => $record,
                'PartitionKey' => md5($record)
            ];
            $sizeOfRecordsToSend += strlen(base64_encode($record));
        }
        Supervisor::moreDataStreamMessagesSent(count($this->recordsBuffer), $sizeOfRecordsToSend);

        try {
            $res = $this->kinesisClient->putRecords($parameter);
        } catch (Exception $e) {
            Logger::error('DataStreamService::sendRecords() exception: ' . Logger::getExceptionMessage($e));
            return false;
        }

        if ($res->get('FailedRecordCount') !== 0) {
            var_dump(count($this->recordsBuffer));
            var_dump($res->__toString());
            // todo: What can i do here? Can i detect the wrong record and if I so, what?
            Logger::error('DataStreamService::sendRecords() FailedRecordCount: ' . $res->get('FailedRecordCount'));
            return false;
        }

        return true;
    }
}
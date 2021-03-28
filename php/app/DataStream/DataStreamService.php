<?php

namespace App\Queue;

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
     * array of array with 2 elements: record and callable
     * @var array
     */
    private $recordsBuffer;

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
        $this->lastFlushTimestamp = 0;
    }

    /**
     * Putting message to the buffer before it would be sent to data stream
     * Call $callbackAfterSent after putting message in the stream
     * @param array $data
     * @param callable $callbackAfterSent
     */
    public function putMessage(array $data, callable $callbackAfterSent): void
    {
        $this->recordsBuffer[] = [json_encode($data, JSON_UNESCAPED_UNICODE), $callbackAfterSent];
        $this->flushIfNeed();
    }

    /**
     * flush the buffer if records count more than max or time has come
     */
    private function flushIfNeed(): void
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
        $parameter = [
            'StreamName' => $this->streamName,
            'Records' => []
        ];
        foreach ($this->recordsBuffer as $record) {
            $parameter['Records'][] = [
                'Data' => $record[0],
                'PartitionKey' => md5($record[0])
            ];
        }
        try {
            $res = $this->kinesisClient->putRecords($parameter);
        } catch (Exception $e) {
            Logger::error('DataStreamService::sendRecords() exception: ' . Logger::getExceptionMessage($e));
            return;
        }
        if ($res->get('FailedRecordCount') !== 0) {
            // todo: What can i do here? Can i detect the wrong record and if I so, what?
            Logger::error('DataStreamService::sendRecords() FailedRecordCount: ' . $res->get('FailedRecordCount'));
        }
        $this->lastFlushTimestamp = time();
        foreach ($this->recordsBuffer as $record) {
            $record[1]();
        }
        $this->recordsBuffer = [];
    }
}
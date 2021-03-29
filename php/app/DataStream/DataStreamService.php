<?php

namespace App\DataStream;

use Aws\Kinesis\KinesisClient;
use App\Utility\Logger;
use Exception;

class DataStreamService
{
    const MAX_SIZE_OF_RECORD = 1024 * 1024 * 1024;

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
    private $flushIntervalSec;

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
    private $onDeleteCallbacksBuffer;

    /**
     * DataStreamService constructor.
     * @param string $region
     * @param string $version
     * @param string $endpointUrl
     * @param string $key
     * @param string $secret
     * @param string $streamName
     * @param int $maxBufferSize
     * @param int $flushIntervalSec
     */
    public function __construct(
        string $region,
        string $version,
        string $endpointUrl,
        string $key,
        string $secret,
        string $streamName,
        int $maxBufferSize,
        int $flushIntervalSec
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
        $this->flushIntervalSec = $flushIntervalSec;
        $this->recordsBuffer = [];
        $this->onDeleteCallbacksBuffer = [];
        $this->lastFlushTimestamp = time();
    }

    /**
     * Putting record to the buffer before it would be sent to data stream
     * @param array $data
     * @param callable $onDeleteCallback - will be called after putting record to data stream
     * @return bool - true on success; false if we can never deliver this message
     */
    public function putRecord(array $data, callable $onDeleteCallback): bool
    {
        $record = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($this->sizeOfRecord($record) > self::MAX_SIZE_OF_RECORD) {
            // todo: we can truncate record here to fit in the size
            Logger::warning('DataStreamService::putRecord() record size too big: ' . substr($record, 0, 256));
            return false;
        }
        $this->recordsBuffer[] = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->onDeleteCallbacksBuffer[] = $onDeleteCallback;
        $this->flushIfNeed();
        return true;
    }

    /**
     * flush the buffer if records count more than max or time has come
     * @return bool - true if flushed
     */
    public function flushIfNeed(): bool
    {
        if (count($this->recordsBuffer) === 0) {
            $this->lastFlushTimestamp = time();
            return false;
        }
        if (
            count($this->recordsBuffer) >= $this->maxBufferSize ||
            time() - $this->lastFlushTimestamp > $this->flushIntervalSec
        ) {
            $this->flush();
            return true;
        }
        return false;
    }

    /**
     * flushing the buffer
     */
    private function flush(): void
    {
        if ($this->send()) {
            foreach ($this->onDeleteCallbacksBuffer as $callback) {
                try {
                    call_user_func($callback);
                } catch (Exception $e) {
                    Logger::warning('DataStreamService::flush() callback exception: ' . Logger::getExceptionMessage($e));
                }
            }
            // no need to use mutex or locker, because it is only inside current php application
            // if we want to use a distributed buffer, we need to remember to synchronize this resources
            $this->lastFlushTimestamp = time();
            $this->recordsBuffer = [];
            $this->onDeleteCallbacksBuffer = [];
        }
    }

    /**
     * @param string $record - json string
     * @return int - bytes
     */
    private function sizeOfRecord(string $record): int
    {
        return strlen(base64_encode($record));
    }

    /**
     * PutRecords from buffer to the DataStream
     * @return bool
     */
    private function send(): bool
    {
        $parameter = [
            'StreamName' => $this->streamName,
            'Records' => []
        ];

        $sizeOfRecordsToSend = 0;
        foreach ($this->recordsBuffer as $i => $record) {
            $parameter['Records'][] = [
                'Data' => $record,
                'PartitionKey' => md5($record)
            ];
            $sizeOfRecordsToSend += $this->sizeOfRecord($record);
        }
        Supervisor::moreDataStreamRecordsSent(count($this->recordsBuffer), $sizeOfRecordsToSend);

        try {
            $res = $this->kinesisClient->putRecords($parameter);
        } catch (Exception $e) {
            Logger::error('DataStreamService::send() putRecords exception: ' . Logger::getExceptionMessage($e));
            return false;
        }

        if ($res->get('FailedRecordCount') !== 0) {
            // now i retry to send records and it works, but what if it does not work because of summary buffer size?
            // todo: need to learn to break the buffer into smaller pieces and try to send them in parts
            // todo: if it is problem with the same data, may be we can log it and drop? so as not to hang in an infinite loop on one record
            // todo: How to identify specific failed record?
            Logger::warning(
                'DataStreamService::send() FailedRecordCount: ' . $res->get('FailedRecordCount') . '.' .
                ' We will try again. Records count: ' . count($this->recordsBuffer)
            );
            // todo: if it did not work after the retry, then it needs to write error
            return false;
        }

        return true;
    }

    /**
     * not tested yet
     * @param int $count
     */
    public function updateShardsCount(int $count)
    {
        // todo: not tested yet
        try {
            $result = $this->kinesisClient->UpdateShardCount([
                'ScalingType' => 'UNIFORM_SCALING',
                'StreamName' => $this->streamName,
                'TargetShardCount' => $count
            ]);
            var_dump($result);
            // todo: parse result
        } catch (Exception $e) {
            Logger::error('DataStreamService::updateShardsCount() exception: ' . Logger::getExceptionMessage($e));
        }
    }
}
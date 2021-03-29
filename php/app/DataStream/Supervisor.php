<?php

namespace App\DataStream;

use App\Utility\Logger;

// todo: need separate supervisor application, that could register count and size from all applications and  if needed than increase or reduce count of Kinesis shards

/**
 * Class Supervisor - Singleton. Monitors the work and takes general actions. For example, it increases the number of shards.
 * @package App\Utility
 */
class Supervisor
{
    private const AVERAGE_DIVISOR = 10; // collecting statistics in N seconds

    private const INITIAL_SHARDS_COUNT = 2;

    private const RECORDS_COUNT_PER_SHARD_PER_SEC = 800; // less than maximum 1000
    private const RECORDS_SIZE_PER_SHARD_PER_SEC = 1024 * 896; // less than maximum 1024*1024

    /**
     * @var int
     */
    private $currentShardsCount;

    private $averageTime = 0;
    private $count = 0;
    private $size = 0;

    /**
     * @var DataStreamService
     */
    private $dataStreamService;

    private static $instance = null;

    private function __construct(DataStreamService $dataStreamService)
    {
        $this->currentShardsCount = self::INITIAL_SHARDS_COUNT;
        $this->dataStreamService = $dataStreamService;
        $this->averageTime = $this->currentAverageTime();
    }

    public static function createService(DataStreamService $dataStreamService): self
    {
        self::$instance = new self($dataStreamService);
        return self::$instance;
    }

    public static function getService(): ?self
    {
        if (self::$instance === null) {
            return null;
        }
        return self::$instance;
    }

    private function currentAverageTime(): int
    {
        return (int)(time() / self::AVERAGE_DIVISOR);
    }

    private function averageCount(): int
    {
        return ceil($this->count / self::AVERAGE_DIVISOR);
    }

    private function averageSize(): int
    {
        return ceil($this->size / self::AVERAGE_DIVISOR);
    }

    private function calcAverage(): void
    {
        if ($this->averageTime !== $this->currentAverageTime()) {
            $averageCount = $this->averageCount();
            $averageSize = $this->averageSize();
            Logger::info("Supervisor next tick. Average count is {$averageCount}, average size {$averageSize} bytes.");

            $this->useAverageForShards($averageCount, $averageSize);

            $this->averageTime = $this->currentAverageTime();
            $this->size = 0;
            $this->count = 0;
        }
    }

    private function useAverageForShards(int $averageCount, int $averageSize): void
    {
        $newMinShardCount = (int)ceil($averageSize / self::RECORDS_SIZE_PER_SHARD_PER_SEC);
        $newMinShardCount = (int)max($newMinShardCount, ceil($averageCount / self::RECORDS_COUNT_PER_SHARD_PER_SEC));
        $newMinShardCount += 1; // keep more by 1
        if ($this->currentShardsCount !== $newMinShardCount) {
            $this->currentShardsCount = $newMinShardCount;
            $this->dataStreamService->updateShardsCount($newMinShardCount);
        }
    }

    /**
     * registering in Supervisor more sent data (count and size in bytes)
     * @param int $count
     * @param int $size bytes
     */
    public static function moreDataStreamRecordsSent(int $count, int $size): void
    {
        $instance = self::getService();
        if (!$instance) {
            Logger::error("Need to create Supervisor first before usage");
            return;
        }
        $instance->size += $size;
        $instance->count += $count;
        $instance->calcAverage();
        Logger::info("More records were put into the data stream. Total count is {$count} with total size {$size} bytes.");
    }
}
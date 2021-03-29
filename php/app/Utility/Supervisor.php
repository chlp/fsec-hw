<?php

namespace App\Utility;

class Supervisor
{
    /**
     * registering in Supervisor more sent data (count and size in bytes)
     * @param int $count
     * @param int $size bytes
     */
    public static function moreDataStreamRecordsSent(int $count, int $size): void
    {
        Logger::info("More records were put into the data stream. Total count is {$count} with total size {$size} bytes.");
        // todo: need supervisor application, that could register count and size from all applications and  if needed than increase or reduce count of Kinesis shards
    }
}
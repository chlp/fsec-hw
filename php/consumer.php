<?php

// curl -sS https://getcomposer.org/installer | php
// php composer.phar require aws/aws-sdk-php

// export AWS_ACCESS_KEY_ID=foo
// export AWS_SECRET_ACCESS_KEY=bar

$streamName = 'events';
$numberOfRecordsPerBatch = 100;

require_once 'vendor/autoload.php';
$sdk = new \Aws\Sdk();
$kinesisClient = $sdk->createKinesis([
    'region' => 'eu-west-1',
    'version' => 'latest',
    'endpoint' => 'http://localhost:4566',
    'credentials' => [
        'key' => 'foo',
        'secret'  => 'bar',
    ],
]);

$res = $kinesisClient->describeStream([ 'StreamName' => $streamName ]);
$shardIds = $res->search('StreamDescription.Shards[].ShardId');

$count = 0;
$startTime = microtime(true);
$sequenceForShards = [];
while (time() - $startTime < 100000) {
    foreach ($shardIds as $shardId) {
        echo "ShardId: $shardId\n";

        $shardIteratorOptions = [
            'ShardId' => $shardId,
            'ShardIteratorType' => 'TRIM_HORIZON',
            // 'ShardIteratorType' => 'TRIM_HORIZON', // 'AT_SEQUENCE_NUMBER|AFTER_SEQUENCE_NUMBER|TRIM_HORIZON|LATEST'
            // 'StartingSequenceNumber' => '',
            'StreamName' => $streamName,
        ];
        if (isset($sequenceForShards[$shardId])) {
            $shardIteratorOptions['ShardIteratorType'] = 'AFTER_SEQUENCE_NUMBER';
            $shardIteratorOptions['StartingSequenceNumber'] = $sequenceForShards[$shardId];
        }
        // get initial shard iterator
        $res = $kinesisClient->getShardIterator($shardIteratorOptions);
        $shardIterator = $res->get('ShardIterator');

        $start = time();

        do {
            echo "Get Records\n";
            $res = $kinesisClient->getRecords([
                'Limit' => $numberOfRecordsPerBatch,
                'ShardIterator' => $shardIterator
            ]);
            $shardIterator = $res->get('NextShardIterator');
            $localCount = 0;
            $sequenceNumber = null;
            foreach ($res->search('Records[].[SequenceNumber, Data]') as $data) {
                list($sequenceNumber, $item) = $data;
                echo "- [$sequenceNumber] $item\n";
                $count++;
                $localCount++;
            }
            if ($sequenceNumber !== null) {
                $sequenceForShards[$shardId] = $sequenceNumber;
            }
            echo "Processed $localCount records in this batch\n";
            sleep(1);
        // } while (time() - $start < 100);
        } while ($localCount>0);
    }
}

$duration = microtime(true) - $startTime;
$timePerMessage = $duration*1000 / $count;

echo "Total Duration: " . round($duration) . " seconds\n";
echo "Time per message: " . round($timePerMessage, 2) . " ms/message\n";
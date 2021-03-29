<?php

// curl -sS https://getcomposer.org/installer | php
// php composer.phar require aws/aws-sdk-php

// export AWS_ACCESS_KEY_ID=foo
// export AWS_SECRET_ACCESS_KEY=bar

use Aws\Kinesis\KinesisClient;

$streamName = 'events';
$totalNumberOfRecords = 10;

require_once 'vendor/autoload.php';
$sdk = new \Aws\Sdk();
$kinesisClient = new KinesisClient([
	'region' => 'eu-west-1',
	'version' => 'latest',
	'endpoint' => 'http://localhost:4566',
    'credentials' => [
        'key' => 'foo',
        'secret'  => 'bar',
    ],
]);

/**
 * Simple buffer that batches messages before passing them to a callback
 */
class Buffer {

    protected $callback;
    protected $size;
    protected $data = [];

    public function __construct($callback, $size=500) {
        $this->callback = $callback;
        $this->size = $size;
    }

    public function add($item) {
        $this->data[] = $item;
        if (count($this->data) >= $this->size) {
            $this->flush();
        }
    }

    public function reset() {
        $this->data = [];
    }

    public function flush() {
        if (count($this->data) > 0) {
            call_user_func($this->callback, $this->data);
            $this->reset();
        }
    }
}

$buffer = new Buffer(function(array $data) use ($kinesisClient, $streamName) {
    echo "Flushing\n";
    $parameter = [ 'StreamName' => $streamName, 'Records' => []];
    foreach ($data as $item) {
        $parameter['Records'][] = [
            'Data' => $item,
            'PartitionKey' => md5($item)
        ];
    }
    sleep(2);
    // try {
    	$res = $kinesisClient->putRecords($parameter);
    // } catch (Exception $ex) {
    	// var_dump($ex);
    	// exit;
    // }
    echo "Failed records: {$res->get('FailedRecordCount')}\n";
});

$startTime = microtime(true);
for ($i=0; $i<$totalNumberOfRecords; $i++) {
    $buffer->add(json_encode([
        'id' => rand(0, 10000),
        'title' => 'Foo',
        'Arg' => time(),
    ]));
}
$buffer->flush();

$duration = microtime(true) - $startTime;
$timePerMessage = $duration*1000 / $totalNumberOfRecords;

echo "Total Duration: " . round($duration) . " seconds\n";
echo "Time per message: " . round($timePerMessage, 2) . " ms/message\n";

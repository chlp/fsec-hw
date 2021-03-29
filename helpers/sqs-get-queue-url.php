<?php

require 'vendor/autoload.php';

use Aws\Sqs\SqsClient; 
use Aws\Exception\AwsException;

$queueName = "submissions";

$client = new SqsClient([
    'region' => 'eu-west-1',
    'version' => 'latest',
    'endpoint' => 'http://localhost:4566',
    'credentials' => [
        'key' => 'foo',
        'secret'  => 'bar',
    ],
]);
try {
    $result = $client->getQueueUrl([
        'QueueName' => $queueName
    ]);
    var_dump($result);
} catch (AwsException $e) {
    error_log($e->getMessage());
}

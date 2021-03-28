<?php

require 'vendor/autoload.php';

use Aws\Sqs\SqsClient; 
use Aws\Exception\AwsException;

$queueUrl = "http://localhost:4566/000000000000/submissions";

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
	echo "Wait...\n";
    $result = $client->receiveMessage(array(
        'AttributeNames' => ['SentTimestamp'],
        'MaxNumberOfMessages' => 10,
        'MessageAttributeNames' => ['All'],
        'QueueUrl' => $queueUrl,
        'WaitTimeSeconds' => 1,
    ));
    var_dump($result);
} catch (AwsException $e) {
    error_log($e->getMessage());
}
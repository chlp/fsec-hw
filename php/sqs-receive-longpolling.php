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
        'secret' => 'bar',
    ],
]);

try {
    echo "Wait...\n";
    $result = $client->receiveMessage(array(
        'AttributeNames' => ['SentTimestamp'],
        'MaxNumberOfMessages' => 2,
        'MessageAttributeNames' => ['All'],
        'QueueUrl' => $queueUrl,
        'WaitTimeSeconds' => 1,
    ));
    var_dump($result);

//    $result = $client->deleteMessage([
//        'QueueUrl' => $queueUrl, // REQUIRED
//        'ReceiptHandle' => $result['Messages'][0]['ReceiptHandle'] // REQUIRED
//    ]);
//    $client->deleteMessageBatch();
    $entries = [];
    foreach ($result['Messages'] as $i => $message) {
        $entries[] = [
            'Id' => 'item_id_' . $i, // REQUIRED
            'ReceiptHandle' => $message['ReceiptHandle'], // REQUIRED
//            'VisibilityTimeout' => 3600
        ];
    }
    $result = $client->deleteMessageBatch([
        'QueueUrl' => $queueUrl,
        'Entries' => $entries,
    ]);
    var_dump($result->__toString());
} catch (AwsException $e) {
    error_log($e->getMessage());
}
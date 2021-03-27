<?php

require 'vendor/autoload.php';

use Aws\Sqs\SqsClient; 
use Aws\Exception\AwsException;
// snippet-end:[sqs.php.long_polling_recieve_message.import]

/**
 * Receive SQS Queue with Long Polling
 *
 * This code expects that you have AWS credentials set up per:
 * https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html
 */
// snippet-start:[sqs.php.long_polling_recieve_message.main]
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
        'QueueUrl' => $queueUrl, // REQUIRED
        'WaitTimeSeconds' => 1,
    ));
    var_dump($result);
} catch (AwsException $e) {
    // output error message if fails
    error_log($e->getMessage());
}
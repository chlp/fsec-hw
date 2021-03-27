<?php

require 'vendor/autoload.php';

use Aws\Sqs\SqsClient; 
use Aws\Exception\AwsException;
// snippet-end:[sqs.php.get_queue_url.import]

/**
 * Get SQS queue url
 *
 * This code expects that you have AWS credentials set up per:
 * https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html
 */
// snippet-start:[sqs.php.get_queue_url.main]

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
// exit;
try {
    $result = $client->getQueueUrl([
        'QueueName' => $queueName // REQUIRED
    ]);
    var_dump($result);
} catch (AwsException $e) {
    // output error message if fails
    error_log($e->getMessage());
}

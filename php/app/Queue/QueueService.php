<?php

namespace App\Queue;

use Aws\Sqs\SqsClient;
use App\Utility\Logger;
use App\Utility\Validator;
use Exception;

class QueueService
{
    /**
     * @var SqsClient
     */
    private $sqsClient;

    /**
     * @var string
     */
    private $queueUrl;

    /**
     * @var string
     */
    private $queueName;

    /**
     * @var int
     */
    private $maxNumberOfMessagePerRequest;

    /**
     * @var int
     */
    private $waitTimeSec;

    /**
     * @var int
     */
    private $visibilityTimeoutSec;

    /**
     * QueueService constructor.
     * @param string $region
     * @param string $version
     * @param string $endpointUrl
     * @param string $key
     * @param string $secret
     * @param string $queueName
     * @param int $maxNumberOfMessagePerRequest
     * @param int $waitTimeSec
     * @param int $visibilityTimeoutSec
     */
    public function __construct(
        string $region,
        string $version,
        string $endpointUrl,
        string $key,
        string $secret,
        string $queueName,
        int $maxNumberOfMessagePerRequest,
        int $waitTimeSec,
        int $visibilityTimeoutSec
    )
    {
        $this->sqsClient = new SqsClient([
            'region' => $region,
            'version' => $version,
            'endpoint' => $endpointUrl,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);
        $this->queueName = $queueName;
        $this->maxNumberOfMessagePerRequest = $maxNumberOfMessagePerRequest;
        $this->waitTimeSec = $waitTimeSec;
        $this->visibilityTimeoutSec = $visibilityTimeoutSec;
        $this->queueUrl = self::getQueueUrl($this->sqsClient, $this->queueName);
    }

    public function receiveMessages(): ?array
    {
        try {
            $result = $this->sqsClient->receiveMessage(array(
                'AttributeNames' => ['SentTimestamp'],
                'MaxNumberOfMessages' => $this->maxNumberOfMessagePerRequest,
                'MessageAttributeNames' => ['All'],
                'QueueUrl' => $this->queueUrl,
                'WaitTimeSeconds' => $this->waitTimeSec,
            ));
        } catch (Exception $e) {
            Logger::error(Logger::getExceptionMessage($e));
            return null;
        }
        if (!isset($result['Messages']) || !is_array($result['Messages'])) {
            Logger::error('sqs messages are not array');
            return null;
        }
        return $result['Messages'];
    }

    private static function getQueueUrl(SqsClient $sqsClient, string $queueName): ?string
    {
        try {
            $result = $sqsClient->getQueueUrl([
                'QueueName' => $queueName
            ]);
            if (!isset($result['QueueUrl'])) {
                error_log("QueueUrl is not returned");
                return null;
            }
            $queueUrl = (string)$result['QueueUrl'];
            if (!Validator::isUrlValid($queueUrl)) {
                Logger::error("wrong QueueUrl is returned" . $queueUrl);
                return null;
            }
            return $queueUrl;
        } catch (Exception $e) {
            Logger::error(Logger::getExceptionMessage($e));
        }
        return null;
    }
}
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
     * @var array
     */
    private $receiptHandlesToDelete;

    /**
     * @var int
     */
    private $maxReceiptsToDeleteAtOnce;

    /**
     * @var int
     */
    private $receiptsToDeleteIntervalSec;

    /**
     * @var int - timestamp
     */
    private $lastDeleteTimestamp;

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
     * @param int $maxReceiptsToDeleteAtOnce
     * @param int $receiptsToDeleteIntervalSec
     * @throws Exception
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
        int $visibilityTimeoutSec,
        int $maxReceiptsToDeleteAtOnce,
        int $receiptsToDeleteIntervalSec
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
        if ($this->queueUrl === null) {
            throw new Exception('Can not get queue url.');
        }
        $this->maxReceiptsToDeleteAtOnce = $maxReceiptsToDeleteAtOnce;
        $this->receiptsToDeleteIntervalSec = $receiptsToDeleteIntervalSec;
        $this->receiptHandlesToDelete = [];
        $this->lastDeleteTimestamp = time();
    }

    /**
     * Long polling wait
     * @return array|null
     */
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
            Logger::error('QueueService::receiveMessages() exception: ' . Logger::getExceptionMessage($e));
            return null;
        }
        if (!isset($result['Messages'])) {
            return [];
        }
        if (!is_array($result['Messages'])) {
            Logger::error('sqs messages are not array');
            return null;
        }
        return $result['Messages'];
    }

    /**
     * @return bool - true if use a long polling wait
     */
    public function isLongPollingWait(): bool
    {
        return $this->waitTimeSec > 0;
    }

    /**
     * Get the receipt handles for the next deletion
     * @param array $messages - result of ::receiveMessages()
     * @return array
     */
    public function getReceiptHandles(array $messages): array
    {
        $receiptHandles = [];
        foreach ($messages as $message) {
            $receiptHandles[] = $message['ReceiptHandle'];
        }
        return $receiptHandles;
    }

    /**
     * Get the receipt handle (for the next deletion)
     * @param array $message - item from array (result of ::receiveMessages())
     * @return string
     */
    public function getReceiptHandle(array $message): string
    {
        return $message['ReceiptHandle'];
    }

    /**
     * @param string $receiptHandle - result from ::getReceiptHandle(). Not immediately
     */
    public function deleteMessage(string $receiptHandle): void
    {
        $this->receiptHandlesToDelete[] = $receiptHandle;
        $this->deleteAccumulatedMessagesIfNeed();
    }

    /**
     * @return bool - true if needed
     */
    public function deleteAccumulatedMessagesIfNeed(): bool
    {
        if (count($this->receiptHandlesToDelete) === 0) {
            $this->lastDeleteTimestamp = time();
            return false;
        }
        if (
            count($this->receiptHandlesToDelete) >= $this->maxReceiptsToDeleteAtOnce ||
            time() - $this->lastDeleteTimestamp > $this->receiptsToDeleteIntervalSec
        ) {
            foreach (array_chunk($this->receiptHandlesToDelete, $this->maxReceiptsToDeleteAtOnce) as $receiptHandles) {
                $this->deleteMessages($receiptHandles);
            }
            $this->receiptHandlesToDelete = [];
            $this->lastDeleteTimestamp = time();
            return true;
        }
        return false;
    }

    /**
     * @param array $receiptHandles
     */
    private function deleteMessages(array $receiptHandles): void
    {
        $entries = [];
        foreach ($receiptHandles as $i => $receiptHandle) {
            $entries[] = [
                'Id' => 'item_id_' . $i,
                'ReceiptHandle' => $receiptHandle,
            ];
        }
        try {
            $result = $this->sqsClient->deleteMessageBatch([
                'QueueUrl' => $this->queueUrl,
                'Entries' => $entries,
            ]);
        } catch (Exception $e) {
            Logger::error('QueueService::deleteAccumulatedMessages() exception: ' . Logger::getExceptionMessage($e));
            return;
        }
        if (!isset($result['Successful'])) {
            Logger::error("QueueService::deleteAccumulatedMessages() no Successful in result" . json_encode($result));
        }
        if (count($result['Successful']) != count($receiptHandles)) {
            Logger::error("something goes wrong with deletion: " . json_encode($receiptHandles));
        }
        // todo: need to parse $result and leave those entries in the array that have not been deleted
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
                Logger::error("wrong QueueUrl is returned " . $queueUrl);
                return null;
            }
            return $queueUrl;
        } catch (Exception $e) {
            Logger::error(Logger::getExceptionMessage($e));
        }
        return null;
    }
}
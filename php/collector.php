<?php

require 'vendor/autoload.php';

class Logger
{
    private const ERROR = 'Error';
    private const WARNING = 'Warning';
    private const INFO = 'Info';

    /**
     * @throws Exception
     */
    private function __construct()
    {
        throw new Exception('Logger is Utility Class. Only static methods.');
    }

    /**
     * here we can use any special logger system for project
     * @param array $log
     */
    private static function writeLog(array $log): void
    {
        error_log(json_encode($log, JSON_UNESCAPED_UNICODE));
    }

    public static function error(string $message): void
    {
        self::writeLog([
            'level' => self::ERROR,
            'message' => $message,
        ]);
    }

    public static function warning(string $message): void
    {
        self::writeLog([
            'level' => self::WARNING,
            'message' => $message,
        ]);
    }

    public static function info(string $message): void
    {
        self::writeLog([
            'level' => self::INFO,
            'message' => $message,
        ]);
    }

    public static function getExceptionMessage(Exception $exception): string
    {
        return get_class($exception) .
            ' (' . $exception->getFile() . ':' . $exception->getLine() . ' ): ' .
            $exception->getMessage();
    }
}

use Aws\Sqs\SqsClient;

$queueName = "submissions";

$sqsClient = new SqsClient([
    'region' => 'eu-west-1',
    'version' => 'latest',
    'endpoint' => 'http://localhost:4566',
    'credentials' => [
        'key' => 'foo',
        'secret' => 'bar',
    ],
]);

function isUrlValid(string $url): bool
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function getQueueUrl(SqsClient $sqsClient, string $queueName): ?string
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
        if (!isUrlValid($queueUrl)) {
            Logger::error("wrong QueueUrl is returned" . $queueUrl);
            return null;
        }
        return $queueUrl;
    } catch (Exception $e) {
        Logger::error(Logger::getExceptionMessage($e));
    }
    return null;
}

$queueUrl = getQueueUrl($sqsClient, $queueName);

class TelemetryEvent
{
    /**
     * @var TelemetryMessage
     */
    private $message;

    protected function __construct(TelemetryMessage $message)
    {
        $this->message = $message;
    }
}

class NewProcessEvent extends TelemetryEvent
{
    private const COMMANDLINE_MAX_LENGTH = 512;
    private const COMMANDLINE_MIN_LENGTH = 2;
    private const USER_MAX_LENGTH = 256;
    private const USER_MIN_LENGTH = 1;

    /**
     * @var string
     */
    private $commandline;
    /**
     * @var string
     */
    private $username;

    /**
     * NewProcessEvent constructor.
     * @param TelemetryMessage $message
     * @param string $commandline
     * @param string $username
     */
    private function __construct(TelemetryMessage $message, string $commandline, string $username)
    {
        parent::__construct($message);
        $this->commandline = $commandline;
        $this->username = $username;
    }

    public static function createFromEventData(TelemetryMessage $message, array $eventData): ?self
    {
        if (!isset($eventData['cmdl']) || !is_string($eventData['cmdl'])) {
            Logger::warning(
                'event data is invalid (no correct cmdl) ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            return null;
        }
        $cmdl = $eventData['cmdl'];
        if (strlen($cmdl) > self::COMMANDLINE_MAX_LENGTH) {
            Logger::warning(
                'cmdl suspicious big length  ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            $cmdl = substr($cmdl, 0, self::COMMANDLINE_MAX_LENGTH);
        }
        if (strlen($cmdl) < self::COMMANDLINE_MIN_LENGTH) {
            Logger::warning(
                'cmdl suspicious small length  ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
        }

        if (!isset($eventData['user']) || !is_string($eventData['user'])) {
            Logger::warning(
                'event data is invalid (no correct user) ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            return null;
        }
        $user = $eventData['user'];
        if (strlen($user) > self::USER_MAX_LENGTH) {
            Logger::warning(
                'user suspicious big length  ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            $user = substr($cmdl, 0, self::USER_MAX_LENGTH);
        }
        if (strlen($user) < self::USER_MIN_LENGTH) {
            Logger::warning(
                'user suspicious small length  ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
        }

        return new self($message, $cmdl, $user);
    }
}

class NetworkConnectionEvent extends TelemetryEvent
{
    private const MIN_VALID_PORT = 0;
    private const MAX_VALID_PORT = 65535;
    /**
     * @var string
     */
    private $sourceIp;
    /**
     * @var string
     */
    private $destinationIp;
    /**
     * @var int
     */
    private $destinationPort;

    /**
     * NetworkConnectionEvent constructor.
     * @param TelemetryMessage $message
     * @param string $sourceIp
     * @param string $destinationIp
     * @param int $destinationPort
     */
    private function __construct(TelemetryMessage $message, string $sourceIp, string $destinationIp, int $destinationPort)
    {
        parent::__construct($message);
        $this->sourceIp = $sourceIp;
        $this->destinationIp = $destinationIp;
        $this->destinationPort = $destinationPort;
    }

    private static function isIpv4Valid(string $ipv4): bool
    {
        return filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    public static function createFromEventData(TelemetryMessage $message, array $eventData): ?self
    {
        if (!isset($eventData['source_ip']) || !is_string($eventData['source_ip'])) {
            Logger::warning(
                'event data is invalid (no correct source_ip) ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            return null;
        }
        $sourceIp = $eventData['source_ip'];
        if (!self::isIpv4Valid($sourceIp)) {
            Logger::warning(
                'source_ip is incorrect  ' . $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
        }

        if (!isset($eventData['destination_ip']) || !is_string($eventData['destination_ip'])) {
            Logger::warning(
                'event data is invalid (no correct destination_ip) ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            return null;
        }
        $destinationIp = $eventData['destination_ip'];
        if (!self::isIpv4Valid($destinationIp)) {
            Logger::warning(
                'destination_ip is incorrect  ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
        }

        if (!isset($eventData['destination_port']) || !is_int($eventData['destination_port'])) {
            Logger::warning(
                'event data is invalid (no correct destination_port) ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            return null;
        }
        $destinationPort = $eventData['destination_port'];
        if ($destinationPort < self::MIN_VALID_PORT && $destinationPort > self::MAX_VALID_PORT) {
            Logger::warning(
                'destination_port is incorrect  ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            return null;
        }

        return new self($message, $sourceIp, $destinationIp, $destinationPort);
    }
}

class TelemetryMessage
{
    private const TIMECREATED_VALID_OLDEST_DIFF = '-30 years'; // may be we need to parse old data
    private const TIMECREATED_VALID_NEWEST_DIFF = '+10 minutes'; // may be something wrong with clocks

    /**
     * @var string UUID
     */
    private $submissionId;
    /**
     * @var string UUID
     */
    private $deviceId;
    /**
     * @var int UTC Timestamp
     */
    private $timeCreated;
    /**
     * @var NewProcessEvent[]
     */
    private $newProcessEvents;

    /**
     * @var NetworkConnectionEvent[]
     */
    private $networkConnectionEvents;

    /**
     * TelemetryMessage constructor.
     * @param string $submissionId
     * @param string $deviceId
     * @param int $timeCreated
     */
    private function __construct(
        string $submissionId,
        string $deviceId,
        int $timeCreated
    )
    {
        $this->submissionId = $submissionId;
        $this->deviceId = $deviceId;
        $this->timeCreated = $timeCreated;
        $this->newProcessEvents = [];
        $this->networkConnectionEvents = [];
    }

    /**
     * @param NewProcessEvent $newProcessEvent
     */
    private function addNewProcessEvent(NewProcessEvent $newProcessEvent): void
    {
        $this->newProcessEvents[] = $newProcessEvent;
    }

    /**
     * @param NetworkConnectionEvent $networkConnectionEvent
     */
    private function addNetworkConnectionEvent(NetworkConnectionEvent $networkConnectionEvent): void
    {
        $this->networkConnectionEvents[] = $networkConnectionEvent;
    }

    private static function isUuid(string $str): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $str) === 1;
    }

    /**
     * @param array $message
     * @return TelemetryMessage|null
     */
    public static function createFromQueueMessage(array $message): ?TelemetryMessage
    {
        if (!isset($message['Body']) || !is_string($message['Body'])) {
            Logger::warning('message is invalid (no Body) ' . json_encode($message));
            return null;
        }
        $queueMessageBody = base64_decode($message['Body'], true);
        if ($queueMessageBody === false) {
            Logger::warning('message is invalid (Body is incorrect) ' . json_encode($message));
            return null;
        }

        $body = json_decode($queueMessageBody, true);
        if (!is_array($body)) {
            Logger::warning('message is invalid (Body is not an array) ' . json_encode($message));
            return null;
        }

        if (!isset($body['submission_id']) || !is_string($body['submission_id'])) {
            Logger::warning('message is invalid (no string submission_id) ' . json_encode($message));
            return null;
        }
        if (!self::isUuid($body['submission_id'])) {
            Logger::warning('message is invalid (submission_id is non-uuid) ' . json_encode($message));
            return null;
        }

        if (!isset($body['device_id']) || !is_string($body['device_id'])) {
            Logger::warning('message is invalid (no string device_id) ' . json_encode($message));
            return null;
        }
        if (!self::isUuid($body['device_id'])) {
            Logger::warning('message is invalid (device_id is non-uuid) ' . json_encode($message));
            return null;
        }

        if (!isset($body['time_created']) || !is_string($body['time_created'])) {
            Logger::warning('message is invalid (no string time_created) ' . json_encode($message));
            return null;
        }
        $timeCreated = strtotime($body['time_created']);
        if (
            $timeCreated < strtotime(self::TIMECREATED_VALID_OLDEST_DIFF) ||
            $timeCreated > strtotime(self::TIMECREATED_VALID_NEWEST_DIFF)) {
            Logger::warning('message is invalid (time_created is out of valid scope) ' . json_encode($message));
            return null;
        }

        $telemetryMessage = new self($body['submission_id'], $body['device_id'], $timeCreated);

        $events = $body['events'];
        if (!is_array($events)) {
            Logger::warning('message is invalid (events is not an array) ' . json_encode($message));
            return null;
        }

        if (!is_array($events['new_process'])) {
            Logger::warning('message is invalid (events.new_process is not an array) ' . json_encode($message));
            return null;
        }
        foreach ($events['new_process'] as $newProcessEventData) {
            if (!is_array($newProcessEventData)) {
                Logger::warning('new_process event is invalid ' . json_encode($message));
            } else {
                $event = NewProcessEvent::createFromEventData($telemetryMessage, $newProcessEventData);
                if ($event !== null) {
                    $telemetryMessage->addNewProcessEvent($event);
                }
            }
        }

        if (!is_array($events['network_connection'])) {
            Logger::warning('message is invalid (events.network_connection is not an array) ' . json_encode($message));
            return null;
        }
        foreach ($events['network_connection'] as $networkConnectionEventData) {
            if (!is_array($networkConnectionEventData)) {
                Logger::warning('network_connection event is invalid ' . json_encode($message));
            } else {
                $event = NetworkConnectionEvent::createFromEventData($telemetryMessage, $networkConnectionEventData);
                if ($event !== null) {
                    $telemetryMessage->addNetworkConnectionEvent($event);
                }
            }
        }

        return $telemetryMessage;
    }

    public function getSubmissionId(): string
    {
        return $this->submissionId;
    }

    public function getDeviceId(): string
    {
        return $this->deviceId;
    }

    public function getTimeCreated(): int
    {
        return $this->timeCreated;
    }

    /**
     * Use this string to identify the message
     * @return string
     */
    public function getMessageIdMark(): string
    {
        return $this->submissionId . ':' . $this->deviceId . '(' . $this->timeCreated . ')';
    }
}

try {
    echo "Wait...\n";
    $result = $sqsClient->receiveMessage(array(
        'AttributeNames' => ['SentTimestamp'],
        'MaxNumberOfMessages' => 1,
        'MessageAttributeNames' => ['All'],
        'QueueUrl' => $queueUrl,
        'WaitTimeSeconds' => 1,
    ));
    if (!is_array($result['Messages'])) {
        Logger::error('sqs messages are not array');
        return null;
    }
    foreach ($result['Messages'] as $message) {
        TelemetryMessage::createFromQueueMessage($message);
        exit;
    }
    exit;
//    method_exists($result, 'me')
//    is_array($result)
//    $result
} catch (Exception $e) {
    Logger::error(Logger::getExceptionMessage($e));
}
<?php

namespace App\Telemetry\Message;

use App\Telemetry\Event\NetworkConnectionEvent;
use App\Telemetry\Event\NewProcessEvent;
use App\Utility\Logger;

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
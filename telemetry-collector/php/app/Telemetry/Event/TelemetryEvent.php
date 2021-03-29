<?php

namespace App\Telemetry\Event;

use App\Telemetry\Message\TelemetryMessage;
use App\Utility\Logger;

class TelemetryEvent
{
    const TYPE_NONE = 'none';
    const TYPE_NETWORK_CONNECTION = 'network_connection';
    const TYPE_NEW_PROCESS = 'new_process';

    /**
     * @var TelemetryMessage
     */
    private $message;

    /**
     * @var string
     */
    protected $type;

    protected function __construct(TelemetryMessage $message)
    {
        $this->type = self::TYPE_NONE;
        $this->message = $message;
    }

    /**
     * Please, use one of inherited classes
     * @return array
     */
    public function toDataStreamRecord(): array
    {
        Logger::warning("TelemetryEvent::toDataStreamRecord(): Please, use one of inherited classes");
        return $this->dataStreamRecordBase();
    }

    /**
     * @return string - identifier
     */
    public function idMark(): string
    {
        return $this->type . '_' . $this->message->getMessageIdMark();
    }

    protected function dataStreamRecordBase(): array
    {
        return [
            'type' => $this->type,
            'device_id' => $this->message->getDeviceId(),
            'submission_id' => $this->message->getSubmissionId(),
            'time_processed' => date('c', $this->message->getTimeProcessed()),
            'time_created' => $this->message->getTimeCreated(),
        ];
    }
}
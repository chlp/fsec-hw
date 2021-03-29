<?php

namespace App\Telemetry\Event;

use App\Telemetry\Message\TelemetryMessage;
use App\Utility\Helper;
use App\Utility\Logger;

class TelemetryEvent
{
    const TYPE_NONE = 'none';
    const TYPE_NETWORK_CONNECTION = 'network_connection';
    const TYPE_NEW_PROCESS = 'new_process';

    /**
     * @var string
     */
    private $id;

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
        $this->id = Helper::uuid();
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
            'event_id' => $this->id,
            'type' => $this->type,
            'device_id' => $this->message->getDeviceId(),
            'submission_id' => $this->message->getSubmissionId(),
            'time_processed' => date('c', $this->message->getTimeProcessed()),
            'time_created' => $this->message->getTimeCreated(),
        ];
    }
}
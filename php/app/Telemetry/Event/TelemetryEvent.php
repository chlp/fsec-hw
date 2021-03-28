<?php

namespace App\Telemetry\Event;

use App\Telemetry\Message\TelemetryMessage;

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
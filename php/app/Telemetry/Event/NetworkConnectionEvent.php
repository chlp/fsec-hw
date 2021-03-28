<?php

namespace App\Telemetry\Event;

use App\Telemetry\Message\TelemetryMessage;
use App\Utility\Logger;
use App\Utility\Validator;

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
        if (!Validator::isIpv4Valid($sourceIp)) {
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
        if (!Validator::isIpv4Valid($destinationIp)) {
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
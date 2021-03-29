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
        $this->type = self::TYPE_NETWORK_CONNECTION;
        $this->sourceIp = $sourceIp;
        $this->destinationIp = $destinationIp;
        $this->destinationPort = $destinationPort;
    }

    public static function createFromEventData(TelemetryMessage $message, array $eventData): ?self
    {
        if (!isset($eventData['source_ip']) || !is_string($eventData['source_ip'])) {
            Logger::debug(
                'event data is invalid (no correct source_ip) ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            return null;
        }
        $sourceIp = $eventData['source_ip'];
        if (!Validator::isIpv4Valid($sourceIp)) {
            Logger::debug(
                'source_ip is incorrect  ' . $sourceIp . ' ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
        }

        if (!isset($eventData['destination_ip']) || !is_string($eventData['destination_ip'])) {
            Logger::debug(
                'event data is invalid (no correct destination_ip) ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            return null;
        }
        $destinationIp = $eventData['destination_ip'];
        if (!Validator::isIpv4Valid($destinationIp)) {
            Logger::debug(
                'destination_ip is incorrect  ' . $destinationIp . ' ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
        }

        if (!isset($eventData['destination_port']) || !is_int($eventData['destination_port'])) {
            Logger::debug(
                'event data is invalid (no correct destination_port) ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            return null;
        }
        $destinationPort = $eventData['destination_port'];
        if ($destinationPort < self::MIN_VALID_PORT && $destinationPort > self::MAX_VALID_PORT) {
            Logger::debug(
                'destination_port is incorrect  ' . $destinationPort . ' ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            return null;
        }

        return new self($message, $sourceIp, $destinationIp, $destinationPort);
    }

    public function toDataStreamRecord(): array
    {
        return array_merge($this->dataStreamRecordBase(), [
            'type_specific_data' => [
                'source_ip' => $this->sourceIp,
                'destination_ip' => $this->destinationIp,
                'destination_port' => $this->destinationPort,
            ]
        ]);
    }
}
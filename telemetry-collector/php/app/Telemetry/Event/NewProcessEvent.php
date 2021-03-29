<?php

namespace App\Telemetry\Event;

use App\Telemetry\Message\TelemetryMessage;
use App\Utility\Logger;

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
        $this->type = self::TYPE_NEW_PROCESS;
        $this->commandline = $commandline;
        $this->username = $username;
    }

    public static function createFromEventData(TelemetryMessage $message, array $eventData): ?self
    {
        if (!isset($eventData['cmdl']) || !is_string($eventData['cmdl'])) {
            Logger::debug(
                'event data is invalid (no correct cmdl) ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            return null;
        }
        $cmdl = $eventData['cmdl'];
        if (strlen($cmdl) > self::COMMANDLINE_MAX_LENGTH) {
            Logger::debug(
                'cmdl suspicious big length  ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            $cmdl = substr($cmdl, 0, self::COMMANDLINE_MAX_LENGTH);
        }
        if (strlen($cmdl) < self::COMMANDLINE_MIN_LENGTH) {
            Logger::debug(
                'cmdl suspicious small length  ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
        }

        if (!isset($eventData['user']) || !is_string($eventData['user'])) {
            Logger::debug(
                'event data is invalid (no correct user) ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            return null;
        }
        $user = $eventData['user'];
        if (strlen($user) > self::USER_MAX_LENGTH) {
            Logger::debug(
                'user suspicious big length  ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
            $user = substr($cmdl, 0, self::USER_MAX_LENGTH);
        }
        if (strlen($user) < self::USER_MIN_LENGTH) {
            Logger::debug(
                'user suspicious small length  ' .
                $message->getMessageIdMark() . ' ' . json_encode($eventData)
            );
        }

        return new self($message, $cmdl, $user);
    }

    public function toDataStreamRecord(): array
    {
        return array_merge($this->dataStreamRecordBase(), [
            'cmdl' => $this->commandline,
            'user' => $this->username,
        ]);
    }
}
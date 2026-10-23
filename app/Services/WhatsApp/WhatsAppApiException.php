<?php

namespace App\Services\WhatsApp;

use RuntimeException;

class WhatsAppApiException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $errorCode = null, public readonly array $details = [])
    {
        parent::__construct($message);
    }

    /**
     * Errors that will not succeed on retry (invalid recipient, template problems, policy blocks).
     */
    public function isPermanent(): bool
    {
        return in_array((string) $this->errorCode, [
            '100', '131008', '131009', '131021', '131026', '131030', '131047', '131051', '132000', '132001', '132005', '132007', '132012', '132015', '132016', '133010', '470',
        ], true);
    }
}

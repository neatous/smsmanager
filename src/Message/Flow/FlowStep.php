<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message\Flow;

interface FlowStep
{

    public function hasBody(): bool;

    /** @return array<string, array<string, mixed>> */
    public function toRequestData(): array;
}

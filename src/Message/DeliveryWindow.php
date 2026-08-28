<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message;

use DateTimeZone;

final readonly class DeliveryWindow
{

    private ?WeekDayList $weekDays;

    private ?TimeOfDay $start;

    private ?TimeOfDay $end;

    private ?DateTimeZone $timezone;

    private function __construct(?WeekDayList $weekDays, ?TimeOfDay $start, ?TimeOfDay $end, ?DateTimeZone $timezone)
    {
        $this->weekDays = $weekDays;
        $this->start = $start;
        $this->end = $end;
        $this->timezone = $timezone;
    }

    public static function create(
        ?WeekDayList $weekDays = null,
        ?TimeOfDay $start = null,
        ?TimeOfDay $end = null,
        ?DateTimeZone $timezone = null,
    ): self
    {
        if ($weekDays === null && $start === null && $end === null && $timezone === null) {
            throw new \Neatous\SmsManager\Exception\InvalidDeliveryWindowException('Delivery window must define at least one constraint.');
        }

        if (($start === null) !== ($end === null)) {
            throw new \Neatous\SmsManager\Exception\InvalidDeliveryWindowException('Delivery window start and end must be both defined or both omitted.');
        }

        return new self($weekDays, $start, $end, $timezone);
    }

    public function getWeekDays(): ?WeekDayList
    {
        return $this->weekDays;
    }

    public function getStart(): ?TimeOfDay
    {
        return $this->start;
    }

    public function getEnd(): ?TimeOfDay
    {
        return $this->end;
    }

    public function getTimezone(): ?DateTimeZone
    {
        return $this->timezone;
    }
}

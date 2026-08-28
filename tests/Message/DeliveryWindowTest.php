<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message;

use DateTimeZone;
use Neatous\SmsManager\Message\DeliveryWindow;
use Neatous\SmsManager\Message\TimeOfDay;
use Neatous\SmsManager\Message\WeekDay;
use Neatous\SmsManager\Message\WeekDayList;
use PHPUnit\Framework\TestCase;

final class DeliveryWindowTest extends TestCase
{

    public function testCreatesFullWindow(): void
    {
        $weekDays = WeekDayList::fromWeekDays(WeekDay::MONDAY);
        $start = TimeOfDay::fromString('08:00');
        $end = TimeOfDay::fromString('18:00');
        $timezone = new DateTimeZone('Europe/Prague');
        $window = DeliveryWindow::create($weekDays, $start, $end, $timezone);
        self::assertSame($weekDays, $window->getWeekDays());
        self::assertSame($start, $window->getStart());
        self::assertSame($end, $window->getEnd());
        self::assertSame($timezone, $window->getTimezone());
    }

    public function testCreatesWindowWithWeekDaysOnly(): void
    {
        $window = DeliveryWindow::create(WeekDayList::fromWeekDays(WeekDay::SATURDAY));
        self::assertNull($window->getStart());
        self::assertNull($window->getEnd());
        self::assertNull($window->getTimezone());
    }

    public function testSerializesOnlyDefinedConstraints(): void
    {
        $window = DeliveryWindow::create(WeekDayList::fromWeekDays(WeekDay::SATURDAY, WeekDay::SUNDAY));
        self::assertSame(['days' => ['saturday', 'sunday']], $window->toRequestData());
    }

    public function testRejectsEmptyWindow(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidDeliveryWindowException::class);
        DeliveryWindow::create();
    }

    public function testRejectsStartWithoutEnd(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidDeliveryWindowException::class);
        DeliveryWindow::create(null, TimeOfDay::fromString('08:00'));
    }

    public function testRejectsEndWithoutStart(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidDeliveryWindowException::class);
        DeliveryWindow::create(null, null, TimeOfDay::fromString('18:00'));
    }
}

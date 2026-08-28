<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message;

use Neatous\SmsManager\Message\WeekDay;
use Neatous\SmsManager\Message\WeekDayList;
use PHPUnit\Framework\TestCase;

final class WeekDayListTest extends TestCase
{

    public function testHoldsWeekDays(): void
    {
        $weekDays = WeekDayList::fromWeekDays(WeekDay::MONDAY, WeekDay::FRIDAY);
        self::assertCount(2, $weekDays);
        self::assertSame([WeekDay::MONDAY, WeekDay::FRIDAY], iterator_to_array($weekDays));
        self::assertTrue($weekDays->contains(WeekDay::FRIDAY));
        self::assertFalse($weekDays->contains(WeekDay::SUNDAY));
    }

    public function testRejectsEmptyList(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidDeliveryWindowException::class);
        WeekDayList::fromWeekDays();
    }

    public function testRejectsDuplicates(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidDeliveryWindowException::class);
        WeekDayList::fromWeekDays(WeekDay::MONDAY, WeekDay::MONDAY);
    }
}

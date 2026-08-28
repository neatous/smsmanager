<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Webhook;

use Neatous\SmsManager\Webhook\ResultInfo;
use PHPUnit\Framework\TestCase;

final class ResultInfoTest extends TestCase
{

    public function testParsesCodeAndDescription(): void
    {
        $resultInfo = ResultInfo::fromString('[307] Insufficient credit');
        self::assertSame(307, $resultInfo->getCode());
        self::assertSame('Insufficient credit', $resultInfo->getDescription());
        self::assertSame('[307] Insufficient credit', $resultInfo->getValue());
    }

    public function testParsesCodeWithoutDescription(): void
    {
        $resultInfo = ResultInfo::fromString('[368]');
        self::assertSame(368, $resultInfo->getCode());
        self::assertNull($resultInfo->getDescription());
    }

    public function testParsesDescriptionWithoutCode(): void
    {
        $resultInfo = ResultInfo::fromString('Unauthorized error');
        self::assertNull($resultInfo->getCode());
        self::assertSame('Unauthorized error', $resultInfo->getDescription());
    }

    public function testRecognizesInsufficientCreditByCode(): void
    {
        self::assertTrue(ResultInfo::fromString('[307] Insufficient credit')->isInsufficientCredit());
        self::assertFalse(ResultInfo::fromString('[302] Invalid phone number format')->isInsufficientCredit());
    }

    public function testRejectsEmptyValue(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidWebhookPayloadException::class);
        ResultInfo::fromString('   ');
    }
}

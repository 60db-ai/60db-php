<?php

declare(strict_types=1);

namespace SixtyDB\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SixtyDB\WebhookSignature;

final class WebhookSignatureTest extends TestCase
{
    private const SIG = 'v1=1698a50bc74d1ff1db85c4e0a5297c2ad9fdba245d5737cdb789e4cc6e098940';

    public function testComputeMatchesVector(): void
    {
        $this->assertSame(self::SIG, WebhookSignature::compute('{"a":1}', '1700000000', 's3cret'));
    }

    public function testVerify(): void
    {
        $now = 1700000100;
        $this->assertTrue(WebhookSignature::verify('{"a":1}', self::SIG, '1700000000', 's3cret', 300, $now));
        $this->assertFalse(WebhookSignature::verify('{"a":2}', self::SIG, '1700000000', 's3cret', 300, $now));
        $this->assertFalse(WebhookSignature::verify('{"a":1}', self::SIG, '1700000000', 's3cret', 300, $now + 1000));
        $this->assertFalse(WebhookSignature::verify('{"a":1}', self::SIG, '1700000000', '', 300, $now));
        $this->assertFalse(WebhookSignature::verify('{"a":1}', '', '1700000000', 's3cret', 300, $now));
        $this->assertFalse(WebhookSignature::verify('{"a":1}', null, '1700000000', 's3cret', 300, $now));
        $this->assertFalse(WebhookSignature::verify('{"a":1}', self::SIG, '17x', 's3cret', 300, $now));
        $this->assertFalse(WebhookSignature::verify('{"a":1}', self::SIG, '1234567890123', 's3cret', 300, $now));
    }
}

<?php

namespace Tests\Unit;

use App\Support\DurationFormatter;
use PHPUnit\Framework\TestCase;

class DurationFormatterTest extends TestCase
{
    public function test_formats_minutes(): void
    {
        $this->assertSame('45 دقيقة', DurationFormatter::format(45));
        $this->assertSame('60 دقيقة', DurationFormatter::format(60));
    }

    public function test_formats_hours_and_minutes_after_sixty_minutes(): void
    {
        $this->assertSame('1 ساعة و1 دقيقة', DurationFormatter::format(61));
        $this->assertSame('5 ساعة و30 دقيقة', DurationFormatter::format(330));
    }

    public function test_formats_days_hours_and_minutes_after_twenty_four_hours(): void
    {
        $this->assertSame('1 يوم و0 ساعة و1 دقيقة', DurationFormatter::format(1441));
        $this->assertSame('2 يوم و3 ساعة و15 دقيقة', DurationFormatter::format(3075));
    }

    public function test_formats_null_as_null(): void
    {
        $this->assertNull(DurationFormatter::format(null));
    }
}

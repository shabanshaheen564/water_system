<?php

namespace App\Support;

class DurationFormatter
{
    public static function format(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        if ($minutes <= 60) {
            return $minutes . ' دقيقة';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours <= 24) {
            return $hours . ' ساعة و' . $remainingMinutes . ' دقيقة';
        }

        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;

        return $days . ' يوم و' . $remainingHours . ' ساعة و' . $remainingMinutes . ' دقيقة';
    }
}

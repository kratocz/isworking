<?php

class CalendarTools
{
    const freeDaysInWeek = [0, 6];
    const freeDaysInYear = ["12-24", "12-25", "12-26", "01-01"];
    const reservedDaysAtTheEndOfMonthCount = 2;

    public static function getPercentagesForDaysInMonth(DateTime $dateTime): array
    {
        $firstDay = DateTime::createFromFormat("Y-m-d", $dateTime->format("Y-m-01"));
        $lastDay = clone $firstDay;
        $lastDay->modify("+ 1 month - 1 day");
        $totalDaysCount = (int)$lastDay->format("d");

        $score = 0;
        $scores = [];
        $day = clone $firstDay;
        for ($dayOfMonth = 1; $dayOfMonth <= $totalDaysCount; $dayOfMonth++) {
            $isReservedDay = $dayOfMonth > $totalDaysCount - self::reservedDaysAtTheEndOfMonthCount;
            if (!$isReservedDay) {
                $dayOfWeek = $day->format("w");
                $monthAndDayString = $day->format("m-d");
                $isFreeDay = in_array($dayOfWeek, self::freeDaysInWeek)
                    || in_array($monthAndDayString, self::freeDaysInYear);
                if (!$isFreeDay) {
                    $score++;
                }
            }
            $scores[$dayOfMonth] = $score;
            $day->modify("+ 1 day");
        }

        $endScore = $score;

        $percentages = [];
        foreach ($scores as $dayOfMonth => $score) {
            $percentages[$dayOfMonth] = $score / $endScore;
        }
        return $percentages;
    }

}

<?php

namespace App\Support;

class PacketDeliveryRatio
{
    /**
     * Estimate PDR from node TX sequence numbers in time order.
     *
     * A decrease in seq is treated as a node reset (new session).
     * Duplicates in the same session count once.
     *
     * @param  iterable<int|string|null>  $sequences
     * @return array{received: int, expected: int, lost: int, ratio: float}|null
     */
    public static function fromSequences(iterable $sequences): ?array
    {
        $sessions = [];
        $current = [];
        $previous = null;

        foreach ($sequences as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $seq = (int) $value;
            if ($seq < 1) {
                continue;
            }

            if ($previous !== null && $seq < $previous) {
                $sessions[] = $current;
                $current = [];
            }

            $current[] = $seq;
            $previous = $seq;
        }

        if ($current !== []) {
            $sessions[] = $current;
        }

        if ($sessions === []) {
            return null;
        }

        $received = 0;
        $expected = 0;

        foreach ($sessions as $session) {
            $unique = array_values(array_unique($session));
            $received += count($unique);
            $expected += max($unique) - min($unique) + 1;
        }

        if ($expected <= 0) {
            return null;
        }

        return [
            'received' => $received,
            'expected' => $expected,
            'lost' => max(0, $expected - $received),
            'ratio' => $received / (float) $expected,
        ];
    }
}

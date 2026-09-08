<?php

namespace Tests\Unit;

use App\Support\PacketDeliveryRatio;
use PHPUnit\Framework\TestCase;

class PacketDeliveryRatioTest extends TestCase
{
    public function test_returns_null_when_no_sequences(): void
    {
        $this->assertNull(PacketDeliveryRatio::fromSequences([]));
        $this->assertNull(PacketDeliveryRatio::fromSequences([null, 0, -1]));
    }

    public function test_counts_gaps_inside_a_session(): void
    {
        $pdr = PacketDeliveryRatio::fromSequences([1, 2, 4]);

        $this->assertSame([
            'received' => 3,
            'expected' => 4,
            'lost' => 1,
            'ratio' => 0.75,
        ], $pdr);
    }

    public function test_treats_seq_decrease_as_a_new_session(): void
    {
        $pdr = PacketDeliveryRatio::fromSequences([1, 2, 3, 1, 2]);

        $this->assertSame(5, $pdr['received']);
        $this->assertSame(5, $pdr['expected']);
        $this->assertSame(0, $pdr['lost']);
        $this->assertEqualsWithDelta(1.0, $pdr['ratio'], 0.0001);
    }

    public function test_counts_duplicate_seq_once(): void
    {
        $pdr = PacketDeliveryRatio::fromSequences([5, 5, 6]);

        $this->assertSame(2, $pdr['received']);
        $this->assertSame(2, $pdr['expected']);
    }
}

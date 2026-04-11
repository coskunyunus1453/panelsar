<?php

namespace Tests\Unit;

use App\Services\BindZoneTextParser;
use PHPUnit\Framework\TestCase;

class BindZoneTextParserTest extends TestCase
{
    public function test_parses_a_mx_txt_and_skips_soa_ns(): void
    {
        $zone = <<<'ZONE'
$TTL 600
@   IN SOA ns1.example.com. admin.example.com. 1 3600 600 86400 600
@   3600 IN NS ns1.example.com.
@   IN A 192.0.2.1
www  IN A 192.0.2.2
mail IN MX 10 mail.example.com.
@ IN TXT "v=spf1 include:_spf.example.com ~all"
ZONE;

        $rows = BindZoneTextParser::parse($zone, 'example.com');
        $types = array_column($rows, 'type');
        $this->assertSame(['A', 'A', 'MX', 'TXT'], $types);
        $this->assertSame('@', $rows[0]['name']);
        $this->assertSame('192.0.2.1', $rows[0]['value']);
        $this->assertSame(600, $rows[0]['ttl']);
        $this->assertSame('www', $rows[1]['name']);
        $this->assertSame(10, $rows[2]['priority']);
    }

    public function test_cname_relative_name(): void
    {
        $zone = "app 300 IN CNAME target.other.com.\n";
        $rows = BindZoneTextParser::parse($zone, 'example.com');
        $this->assertCount(1, $rows);
        $this->assertSame('CNAME', $rows[0]['type']);
        $this->assertSame('app', $rows[0]['name']);
        $this->assertStringContainsString('target.other.com', $rows[0]['value']);
    }
}

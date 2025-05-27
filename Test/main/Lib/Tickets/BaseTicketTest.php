<?php
namespace FacturaScripts\Test\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Plugins\Tickets\Lib\Tickets\BaseTicket;
use FacturaScripts\Plugins\Tickets\Model\TicketPrinter;
use PHPUnit\Framework\TestCase;

final class BaseTicketTest extends TestCase
{
    private static function sanitize(string $txt): string
    {
        // helper para exponer método protegido
        return new class extends BaseTicket {
            public static function print($m, TicketPrinter $p, $u, $a = null): bool {return false;}
            public static function run(string $txt): string { return static::sanitize($txt); }
        }::run($txt);
    }

    public function testSanitize(): void
    {
        // verificamos caracteres especiales
        $clean = self::sanitize('áéíóú €');
        $this->assertSame('aeiou EUR', $clean);
    }
}

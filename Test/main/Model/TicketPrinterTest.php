<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Test\Plugins\Tickets\Model;

use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;
use PHPUnit\Framework\TestCase;

final class TicketPrinterTest extends TestCase
{
    use LogErrorsTrait;
    use RandomDataTrait;

    public function testCreate(): void
    {
        // crear usuario
        $user = $this->getRandomUser();
        $user->password = 'test1234';
        $this->assertTrue($user->save(), 'cant-create-user');

        // crear impresora
        $printer = new TicketPrinter();
        $printer->name = 'test printer';
        $printer->nick = $user->nick;
        $this->assertTrue($printer->save(), 'printer-cant-save');

        // eliminar
        $this->assertTrue($printer->delete(), 'printer-cant-delete');
        $this->assertTrue($user->delete(), 'user-cant-delete');
    }

    public function testGetDashLineAndIsActive(): void
    {
        // crear usuario
        $user = $this->getRandomUser();
        $user->password = 'test1234';
        $this->assertTrue($user->save(), 'cant-create-user');

        // crear impresora y configurar
        $printer = new TicketPrinter();
        $printer->name = 'test printer';
        $printer->nick = $user->nick;
        $printer->linelen = 8;
        $this->assertTrue($printer->save(), 'printer-cant-save');

        // impresora activa
        $printer->lastactivity = date('Y-m-d H:i:s', time() - 100);
        $this->assertTrue($printer->isActive(), 'printer-should-be-active');

        // impresora inactiva
        $printer->lastactivity = date('Y-m-d H:i:s', time() - TicketPrinter::MAX_INACTIVITY - 1);
        $this->assertFalse($printer->isActive(), 'printer-should-be-inactive');

        // línea de guiones
        $this->assertEquals(str_repeat('-', 8), $printer->getDashLine(), 'dash-line-wrong-length');

        // eliminar
        $this->assertTrue($printer->delete(), 'printer-cant-delete');
        $this->assertTrue($user->delete(), 'user-cant-delete');
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}

<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Test\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Lib\Calculator;
use FacturaScripts\Dinamic\Lib\Tickets\Normal;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;
use PHPUnit\Framework\TestCase;

final class NormalTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;
    use RandomDataTrait;

    const PRODUCT1_PRICE = 66.1;
    const PRODUCT1_QUANTITY = 3;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
        self::removeTaxRegularization();
    }

    public function testPrintSuccessful(): void
    {
        // crear usuario
        $user = $this->getRandomUser();
        $user->password = 'test1234';
        $this->assertTrue($user->save(), 'cant-create-user');

        // comprobar que no existe ningún ticket
        $ticket = new Ticket();
        $ticketCount = $ticket->count();
        // $this->assertEquals($ticketCount, $ticket->count(), 'ticket-not-created');

        // creamos el cliente
        $customer = $this->getRandomCustomer();
        $this->assertTrue($customer->save(), 'cant-create-customer');

        // creamos la factura
        $invoice = new FacturaCliente();
        $this->assertTrue($invoice->setSubject($customer), 'invoice-cant-set-subject');
        $this->assertTrue($invoice->save(), 'cant-create-invoice');

        // añadimos una línea
        $firstLine = $invoice->getNewLine();
        $firstLine->cantidad = self::PRODUCT1_QUANTITY;
        $firstLine->descripcion = 'Test';
        $firstLine->pvpunitario = self::PRODUCT1_PRICE;
        $this->assertTrue($firstLine->save(), 'cant-save-first-line');

        // recalculamos
        $lines = $invoice->getLines();
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'cant-update-invoice');

        // crear impresora
        $printer = new TicketPrinter();
        $printer->name = 'test printer';
        $printer->nick = $user->nick;
        $this->assertTrue($printer->save(), 'printer-cant-save');

        // crear ticket
        $this->assertTrue(Normal::print($invoice, $printer, new User()));

        // comprobar existencia ticket
        $this->assertEquals($ticketCount + 1, $ticket->count(), 'ticket-not-created');

        // eliminamos
        $this->assertTrue($invoice->delete(), 'cant-delete-invoice');
        $this->assertTrue($customer->getDefaultAddress()->delete(), 'contacto-cant-delete');
        $this->assertTrue($customer->delete(), 'cant-delete-customer');
        $this->assertTrue($printer->delete(), 'printer-cant-delete');
        $this->assertTrue($user->delete(), 'user-cant-delete');
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}

<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Test\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Dinamic\Lib\Tickets\Normal;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Ticket;
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
        $printer = new \stdClass();
        $printer->linelen = 48;
        $printer->font_size = 1;
        $printer->footer_font_size = 1;
        $printer->head_font_size = 1;
        $printer->title_font_size = 2;
        $printer->print_comp_shortname = false;
        $printer->print_comp_tlf = false;
        $printer->print_invoice_receipts = false;
        $printer->print_lines_description = true;
        $printer->print_lines_discount = false;
        $printer->print_lines_net = false;
        $printer->print_lines_price = false;
        $printer->print_lines_price_unitary = false;
        $printer->print_lines_price_tax = false;
        $printer->print_lines_quantity = true;
        $printer->print_lines_reference = false;
        $printer->print_lines_total = true;
        $printer->print_payment_methods = false;
        $printer->print_stored_logo = false;
        $printer->footer = '';
        $printer->head = '';

        // crear ticket
        $this->assertTrue(Normal::print($invoice, $printer, new User()));

        // comprobar existencia ticket
        $this->assertEquals($ticketCount + 1, $ticket->count(), 'ticket-not-created');

        // eliminamos
        $this->assertTrue($invoice->delete(), 'cant-delete-invoice');
        $this->assertTrue($customer->getDefaultAddress()->delete(), 'contacto-cant-delete');
        $this->assertTrue($customer->delete(), 'cant-delete-customer');
        $this->assertTrue($user->delete(), 'user-cant-delete');
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}

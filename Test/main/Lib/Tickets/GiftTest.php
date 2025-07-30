<?php
/**
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Test\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Dinamic\Lib\Tickets\Gift;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Test\Traits\DefaultSettingsTrait;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use PHPUnit\Framework\TestCase;

final class GiftTest extends TestCase
{
    use DefaultSettingsTrait;
    use LogErrorsTrait;

    public static function setUpBeforeClass(): void
    {
        self::setDefaultSettings();
        self::installAccountingPlan();
        self::removeTaxRegularization();
    }

    // probar que se puede imprimir un ticket
    public function testPrintTicket(): void
    {
        // crear usuario
        $user = new User();
        $user->nick = 'user_' . mt_rand(1, 999);
        $user->email = $user->nick . '@facturascripts.com';
        $user->password = 'test1234';
        $user->setPassword('test1234');
        $this->assertTrue($user->save(), 'user-cant-save');

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

        // crear factura
        $customer = new Cliente();
        $customer->cifnif = 'B' . mt_rand(1, 999999);
        $customer->nombre = 'Customer Rand ' . mt_rand(1, 99999);
        $customer->observaciones = 'test';
        $customer->razonsocial = 'Empresa ' . mt_rand(1, 99999);
        $this->assertTrue($customer->save(), 'customer-cant-save');

        $invoice = new FacturaCliente();
        $this->assertTrue($invoice->setSubject($customer), 'invoice-cant-set-subject');

        $this->assertTrue($invoice->save(), 'invoice-cant-save');
        $line = $invoice->getNewLine();
        $line->cantidad = 1;
        $line->pvpunitario = mt_rand(100, 9999);
        $line->save();

        $lines = $invoice->getLines();
        $this->assertTrue(Calculator::calculate($invoice, $lines, true), 'invoice-cant-update');
        $this->assertTrue($invoice->save(), 'invoice-cant-save');

        // imprimir ticket regalo
        $this->assertTrue(Gift::print($invoice, $printer, $user), 'gift-cant-print');

        // borrar usuario, impresora y factura
        $this->assertTrue($user->delete(), 'user-cant-delete');
        $this->assertTrue($invoice->delete(), 'invoice-cant-delete');
        $this->assertTrue($customer->delete(), 'customer-cant-delete');
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}

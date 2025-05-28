<?php

namespace FacturaScripts\Test\Plugins\Tickets\Model;

use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Test\Traits\LogErrorsTrait;
use FacturaScripts\Test\Traits\RandomDataTrait;
use PHPUnit\Framework\TestCase;

final class TicketTest extends TestCase
{
    use LogErrorsTrait;
    use RandomDataTrait;

    public function testGetPrinterAndUrl(): void
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

        // crear ticket
        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $this->assertTrue($ticket->save(), 'ticket-cant-save');

        $this->assertEquals($printer->id, $ticket->getPrinter()->id, 'printer-not-loaded');
        $this->assertEquals($printer->url(), $ticket->url('list'), 'wrong-url');

        $this->assertTrue($ticket->delete(), 'ticket-cant-delete');
        $this->assertTrue($printer->delete(), 'printer-cant-delete');
        $this->assertTrue($user->delete(), 'user-cant-delete');
    }

    public function testSaveCalculatesPrintDelay(): void
    {
        // creamos un agente
        $agent = $this->getRandomAgent();
        $this->assertTrue($agent->save(), 'cant-create-agent');

        // creamos un usuario vinculado
        $user = $this->getRandomUser();
        $user->password = 'test1234';
        $user->codagente = $agent->codagente;
        $this->assertTrue($user->save(), 'cant-create-user');

        // creamos la impresora
        $printer = new TicketPrinter();
        $printer->name = 'Test printer';
        $printer->nick = $user->nick;
        $this->assertTrue($printer->save(), 'printer-cant-save');

        // preparamos ticket impreso
        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $ticket->codagente = $agent->codagente;
        $ticket->creationdate = date('Y-m-d H:i:s', time() - 5);
        $ticket->printed = true;

        // debe calcularse la demora
        $this->assertTrue($ticket->save(), 'ticket-cant-save');
        $this->assertGreaterThanOrEqual(5, $ticket->printdelay, 'delay-not-calculated');

        // limpieza
        $this->assertTrue($ticket->delete(), 'ticket-cant-delete');
        $this->assertTrue($printer->delete(), 'printer-cant-delete');
        $this->assertTrue($user->delete(), 'user-cant-delete');
        $this->assertTrue($agent->delete(), 'agent-cant-delete');
    }

    protected function tearDown(): void
    {
        $this->logErrors();
    }
}
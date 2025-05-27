<?php
namespace FacturaScripts\Test\Plugins\Tickets\Lib\Export;

use FacturaScripts\Plugins\Tickets\Lib\Export\TicketExport;
use Symfony\Component\HttpFoundation\Response;
use PHPUnit\Framework\TestCase;

final class TicketExportTest extends TestCase
{
    private function getExport(): TicketExport
    {
        return new class extends TicketExport {
            public function getParams(): array { return $this->sendParams; }
        };
    }

    public function testAddPagesAndShow(): void
    {
        $export = $this->getExport();
        $model = new class {
            public function modelClassName() { return 'Model'; }
            public function primaryColumnValue() { return 10; }
        };

        // añadimos páginas
        $this->assertTrue($export->addModelPage($model, [], '')); // modelo
        $this->assertFalse($export->addBusinessDocPage($model));  // documento

        $params = $export->getParams();
        $this->assertSame('Model', $params['modelClassName']);
        $this->assertSame(10, $params['modelCode']);

        // comprobamos encabezado Refresh
        $response = new Response();
        $export->show($response);
        $this->assertTrue($response->headers->has('Refresh'));
    }
    public function testBusinessDocAndShow(): void
    {
        // exportador
        $export = new TicketExport();
        $model = new class {
            public function modelClassName() { return 'TestModel'; }
            public function primaryColumnValue() { return 5; }
        };

        // añadir página de documento de negocio
        $this->assertFalse($export->addBusinessDocPage($model));
        $this->assertTrue($export->addModelPage($model, []));

        // simular respuesta
        $response = new Response();
        $export->show($response);
        $this->assertStringContainsString('SendTicket?', $response->headers->get('Refresh'));
    }
}

<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Tickets\Gift;
use FacturaScripts\Dinamic\Lib\Tickets\Normal;
use FacturaScripts\Dinamic\Lib\Tickets\PaymentReceipt;
use FacturaScripts\Dinamic\Lib\Tickets\Service;
use FacturaScripts\Dinamic\Lib\Tickets\TicketBai;
use FacturaScripts\Dinamic\Model\ServicioAT;
use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class SendTicket extends Controller
{
    /** @var string */
    public $modelClassName = '';

    /** @var string */
    public $modelCode = '';

    /** @var TicketPrinter[] */
    public $printers = [];

    /** @var array */
    private static $formats = [];

    public static function addFormat(string $className, string $modelName, string $label): void
    {
        // si ya existe un formato con la misma className, no hacemos nada
        if (isset(self::$formats[$modelName])) {
            foreach (self::$formats[$modelName] as $format) {
                if ($format['className'] === $className) {
                    return;
                }
            }
        }

        // añadimos el formato
        self::$formats[$modelName][] = [
            'className' => $className,
            'label' => $label,
            'modelName' => $modelName,
        ];
    }

    public static function getFormats(string $modelClassName): array
    {
        return self::$formats[$modelClassName] ?? [];
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'sales';
        $data['title'] = 'print-ticket';
        $data['icon'] = 'fa-solid fa-receipt';
        $data['showonmenu'] = false;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->modelClassName = $this->request->get('modelClassName');
        $this->modelCode = $this->request->get('modelCode');
        $this->loadPrinters();

        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $this->modelClassName;
        if (empty($this->modelClassName) || empty($this->modelCode) || false === class_exists($modelClass)) {
            $this->setTemplate('Error/SendTicket');
            return;
        }

        $model = new $modelClass();
        if (false === $model->loadFromCode($this->modelCode)) {
            $this->setTemplate('Error/SendTicket');
            return;
        }

        $action = $this->request->request->get('action', '');
        if ($action === 'print') {
            $this->printAction($model);
        } elseif ($action === 'get-escpos') {
            $this->getEscposAction($model);
        }
    }

    protected function getEscposAction(ModelClass $model): void
    {
        $this->setTemplate(false);

        $translator = Tools::lang();
        $printerId = (int)$this->request->request->get('printer');
        $printer = new TicketPrinter();
        if (false === $printer->loadFromCode($printerId)) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => $translator->trans('printer-not-found')]));
            return;
        }

        // Obtiene la clase de formato dinámicamente desde la petición.
        $formatClass = $this->request->request->get('format', '');
        if (empty($formatClass)) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => $translator->trans('format-class-not-provided')]));
            return;
        }

        $formatClass = '\\' . $formatClass;
        if (false === class_exists($formatClass)) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => $translator->trans('invalid-format-class')]));
            return;
        }

        // 1. Llama a la función print de la clase dinámica para guardar el ticket.
        if (false === $formatClass::print($model, $printer, $this->user)) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => $translator->trans('failed-to-create-temporary-ticket')]));
            return;
        }

        // 2. Busca el ticket recién creado para esta impresora, ordenado por fecha de creación.
        $where = [new DataBaseWhere('printed', false)];
        $tickets = Ticket::all($where, ['creationdate' => 'DESC'], 0, 1);
        if (empty($tickets)) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => $translator->trans('could-not-retrieve-temporary-ticket')]));
            return;
        }

        $tempTicket = $tickets[0];
        $rawData = base64_decode($tempTicket->body);

        // 3. Borra el ticket temporal de la base de datos.
        if (false === $tempTicket->delete()) {
            Tools::log()->error($translator->trans('failed-to-delete-temporary-ticket') . ': ' . $tempTicket->id);
        }

        // 4. Comprueba que el cuerpo del ticket no esté vacío.
        if (empty($rawData)) {
            $this->response->setContent(json_encode(['ok' => false, 'error' => $translator->trans('generated-ticket-body-is-empty')]));
            return;
        }

        // 5. Devuelve una respuesta JSON correcta.
        $this->response->setContent(json_encode(['ok' => true, 'data' => $rawData]));
    }

    protected function getPrinter(int $id): TicketPrinter
    {
        foreach ($this->printers as $printer) {
            if ($printer->id === $id) {
                return $printer;
            }
        }

        return new TicketPrinter();
    }

    protected function loadPrinters(): void
    {
        $printerModel = new TicketPrinter();
        $this->printers = $printerModel->all([], ['creationdate' => 'DESC'], 0, 0);
    }

    protected function printAction(ModelClass $model): void
    {
        $formatClass = $this->request->request->get('format', '');
        if (empty($formatClass)) {
            return;
        }

        $formatClass = '\\' . $formatClass;
        if (false === class_exists($formatClass)) {
            return;
        }

        $printer = $this->getPrinter((int)$this->request->request->get('printer'));
        if (false === $printer->exists()) {
            return;
        }

        $format = new $formatClass();
        if ($format::print($model, $printer, $this->user)) {
            Tools::log()->notice('sending-to-printer');
            $this->redirect($model->url(), 1);
        }
    }
}

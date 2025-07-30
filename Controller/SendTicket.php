<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\Tickets\Gift;
use FacturaScripts\Dinamic\Lib\Tickets\Normal;
use FacturaScripts\Dinamic\Lib\Tickets\PaymentReceipt;
use FacturaScripts\Dinamic\Lib\Tickets\Service;
use FacturaScripts\Dinamic\Lib\Tickets\TicketBai;
use FacturaScripts\Dinamic\Model\ServicioAT;
use FacturaScripts\Plugins\Tickets\Model\TicketPrinter;

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

    public function getModel()
    {
        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $this->modelClassName;
        if (empty($this->modelClassName) || empty($this->modelCode) || false === class_exists($modelClass)) {
            $this->setTemplate('Error/SendTicket');
            return null;
        }

        $model = new $modelClass();
        if (false === $model->loadFromCode($this->modelCode)) {
            $this->setTemplate('Error/SendTicket');
            return null;
        }

        return $model;
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

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->setTemplate('SendTicket');

        $this->modelClassName = $this->request->get('modelClassName');
        $this->modelCode = $this->request->get('modelCode');

        $model = $this->getModel();
        if (is_null($model)) {
            $this->setTemplate('Error/SendTicket');
            return;
        }

        $this->loadPrinters();
        $this->view->set('formats', self::getFormats($this->modelClassName));

        $action = $this->request->request->get('action', '');
        if ($action === 'print') {
            $this->printAction($model);
        }
    }

    protected function printAction(ModelClass $model): void
    {
        $formatClass = $this->request->request->get('format', '');
        if (empty($formatClass)) {
            $this->toolBox()->log()->warning('format-class-not-found');
            return;
        }

        $formatClass = '\\' . $formatClass;
        if (false === class_exists($formatClass)) {
            $this->toolBox()->log()->warning('format-class-not-found');
            return;
        }

        $printer = $this->getPrinter((int)$this->request->request->get('printer'));
        if (false === $printer->exists()) {
            $this->toolBox()->log()->warning('printer-not-found');
            return;
        }

        if ($printer->type === 'qztray') {
            $this->setTemplate(false);
            $escpos = $formatClass::print($model, $printer, $this->user, null, false);
            $this->response->setContent(json_encode(['escpos_data' => base64_encode($escpos)]));
            $this->response->headers->set('Content-Type', 'application/json');
            return;
        }

        if ($formatClass::print($model, $printer, $this->user)) {
            $this->toolBox()->log()->notice('sending-to-printer');
            $this->redirect($model->url(), 1);
        }
    }
}

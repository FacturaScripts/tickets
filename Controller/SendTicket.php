<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Tools;
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

    public function generateTicket()
    {
        $model = $this->getModel();
        if (is_null($model)) {
            return [];
        }

        return $model->getLines();
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

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        $this->modelClassName = $this->request->get('modelClassName');
        $this->modelCode = $this->request->get('modelCode');

        $model = $this->getModel();
        if (is_null($model)) {
            return;
        }

        $this->loadPrinters();
        $this->setTemplate('SendTicket');
        $this->view->set('formats', self::getFormats($this->modelClassName));

        $action = $this->request->request->get('action', '');
        switch ($action) {
            case 'getESCPOS':
                $this->setTemplate(false);
                $format = $this->request->request->get('format', '80mm');
                $formatClass = $this->request->request->get('formatClass', '');

                // Create a virtual printer to get the commands
                $printer = new \stdClass();
                $printer->linelen = ($format === '80mm') ? 48 : 32;
                $printer->font_size = (int)$this->request->request->get('font_size', 1);
                $printer->footer_font_size = (int)$this->request->request->get('footer_font_size', 1);
                $printer->head_font_size = (int)$this->request->request->get('head_font_size', 1);
                $printer->title_font_size = (int)$this->request->request->get('title_font_size', 2);
                $printer->print_comp_shortname = $this->request->request->get('print_comp_shortname') === 'true';
                $printer->print_comp_tlf = $this->request->request->get('print_comp_tlf') === 'true';
                $printer->print_invoice_receipts = $this->request->request->get('print_invoice_receipts') === 'true';
                $printer->print_lines_description = $this->request->request->get('print_lines_description') === 'true';
                $printer->print_lines_discount = $this->request->request->get('print_lines_discount') === 'true';
                $printer->print_lines_net = $this->request->request->get('print_lines_net') === 'true';
                $printer->print_lines_price = $this->request->request->get('print_lines_price') === 'true';
                $printer->print_lines_price_tax = $this->request->request->get('print_lines_price_tax') === 'true';
                $printer->print_lines_quantity = $this->request->request->get('print_lines_quantity') === 'true';
                $printer->print_lines_reference = $this->request->request->get('print_lines_reference') === 'true';
                $printer->print_lines_total = $this->request->request->get('print_lines_total') === 'true';
                $printer->print_payment_methods = $this->request->request->get('print_payment_methods') === 'true';
                $printer->print_stored_logo = $this->request->request->get('print_stored_logo') === 'true';
                $printer->footer = $this->request->request->get('footer', '');
                $printer->head = $this->request->request->get('head', '');

                // Get the ESC/POS commands without saving the ticket
                $escpos = '';
                if (class_exists($formatClass)) {
                    $escpos = $formatClass::print($model, $printer, $this->user, null, false);
                }

                $this->response->setContent(json_encode(['escpos_data' => $escpos]));
                $this->response->headers->set('Content-Type', 'application/json');
                return;

            case 'print':
                $this->printAction($model);
                break;
        }
    }
}

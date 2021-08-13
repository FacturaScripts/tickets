<?php

namespace FacturaScripts\Plugins\Tickets\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\Base\SalesDocumentLine;
use FacturaScripts\Dinamic\Model\Impuesto;
use FacturaScripts\Plugins\Tickets\Model\Ticket;
use FacturaScripts\Plugins\Tickets\Model\TicketPrinter;

class SendTicket extends Controller
{
    /**
     * @var array
     */
    public $formats = [];

    /**
     * @var string
     */
    public $modelClassName = '';

    /**
     * @var string
     */
    public $modelCode = '';

    /**
     * @var TicketPrinter[]
     */
    public $printers = [];

    /**
     * @return array
     */
    public function getPageData()
    {
        $data = parent::getPageData();
        $data['menu'] = 'sales';
        $data['title'] = 'print-ticket';
        $data['icon'] = 'fas fa-receipt';
        $data['showonmenu'] = false;
        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        $this->modelClassName = $this->request->get('modelClassName');
        $this->modelCode = $this->request->get('modelCode');
        $this->loadPrinters();
        $this->loadFormats();

        $modelClass = '\\FacturaScripts\\Dinamic\\Model\\' . $this->modelClassName;
        $model = new $modelClass();
        $model->loadFromCode($this->modelCode);

        $format = $this->request->request->get('format');
        switch ($format) {
            case 'gift':
                $this->printGift($model);
                break;

            case 'normal':
                $this->printNormal($model);
                break;
        }
    }

    /**
     * @param TicketPrinter $printer
     *
     * @return string
     */
    protected function getCutCommand(TicketPrinter $printer): string
    {
        $commandStr = '';
        $chars = \explode('.', $printer->cutcommand);
        foreach ($chars as $char) {
            $commandStr .= \chr($char);
        }

        return $commandStr;
    }

    /**
     * @param TicketPrinter $printer
     *
     * @return string
     */
    protected function getDashLine(TicketPrinter $printer): string
    {
        $line = '';
        while (\strlen($line) < $printer->linelen) {
            $line .= '-';
        }

        return $line;
    }

    /**
     * @param int $id
     *
     * @return TicketPrinter
     */
    protected function getPrinter(int $id): TicketPrinter
    {
        foreach ($this->printers as $printer) {
            if ($printer->id === $id) {
                return $printer;
            }
        }

        return new TicketPrinter();
    }

    /**
     * @param SalesDocument $model
     * @param SalesDocumentLine $lines
     *
     * @return array
     */
    protected function getSubtotals($model, $lines): array
    {
        $subtotals = [];
        $eud = $model->getEUDiscount();

        foreach ($lines as $line) {
            if (!isset($subtotals[$line->codimpuesto])) {
                $subtotals[$line->codimpuesto] = [
                    'tax' => $line->codimpuesto,
                    'taxp' => $line->iva . '%',
                    'taxbase' => 0,
                    'taxamount' => 0,
                    'taxsurcharge' => 0
                ];

                $impuesto = new Impuesto();
                if ($line->codimpuesto && $impuesto->loadFromCode($line->codimpuesto)) {
                    $subtotals[$line->codimpuesto]['tax'] = $impuesto->descripcion;
                }
            }


            $subtotals[$line->codimpuesto]['taxbase'] += $line->pvptotal * $eud;
            $subtotals[$line->codimpuesto]['taxamount'] += $line->pvptotal * $eud * $line->iva / 100;
            $subtotals[$line->codimpuesto]['taxsurcharge'] += $line->pvptotal * $eud * $line->recargo / 100;
        }

        return $subtotals;
    }

    protected function loadFormats()
    {
        $this->formats[] = 'normal';
        $this->formats[] = 'gift';
    }

    protected function loadPrinters()
    {
        $printerModel = new TicketPrinter();
        $this->printers = $printerModel->all([], ['creationdate' => 'DESC'], 0, 0);
    }

    /**
     * @param SalesDocument $model
     */
    protected function printGift($model)
    {
        $i18n = $this->toolBox()->i18n();
        $printer = $this->getPrinter((int)$this->request->request->get('printer'));

        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $this->user->nick;
        $ticket->title = $i18n->trans($model->modelClassName() . '-min') . ' ' . $model->codigo;

        $company = $model->getCompany();
        $ticket->body = "\x1B" . "!" . "\x38" . $company->nombre . "\n" . "\x1B" . "!" . "\x00"
            . $company->direccion . "\nCP: " . $company->codpostal . ', ' . $company->ciudad . "\n"
            . $company->tipoidfiscal . ': ' . $company->cifnif . "\n\n"
            . $ticket->title . "\n"
            . $i18n->trans('date') . ': ' . $model->fecha . ' ' . $model->hora . "\n\n";

        $dwidth = $printer->linelen - 6;
        $ticket->body .= \sprintf("%5s", $i18n->trans('quantity-abb')) . " "
            . \sprintf("%-" . $dwidth . "s", $i18n->trans('description')) . "\n";
        $ticket->body .= $this->getDashLine($printer) . "\n";
        $lines = $model->getLines();
        foreach ($lines as $line) {
            $description = \mb_substr($line->descripcion, 0, $dwidth);
            $ticket->body .= \sprintf("%5s", $line->cantidad) . " "
                . \sprintf("%-" . $dwidth . "s", $description) . "\n";
        }
        $ticket->body .= $this->getDashLine($printer) . "\n";
        $ticket->body .= "\n\n\n\n\n\n" . $this->getCutCommand($printer);
        $ticket->save();

        $this->toolBox()->i18nLog()->notice('sending-to-printer');
        $this->redirect($model->url(), 1);
    }

    /**
     * @param SalesDocument $model
     */
    protected function printNormal($model)
    {
        $i18n = $this->toolBox()->i18n();
        $printer = $this->getPrinter((int)$this->request->request->get('printer'));

        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $this->user->nick;
        $ticket->title = $i18n->trans($model->modelClassName() . '-min') . ' ' . $model->codigo;

        $company = $model->getCompany();
        $ticket->body = "\x1B" . "!" . "\x38" . $company->nombre . "\n" . "\x1B" . "!" . "\x00"
            . $company->direccion . "\nCP: " . $company->codpostal . ', ' . $company->ciudad . "\n"
            . $company->tipoidfiscal . ': ' . $company->cifnif . "\n\n"
            . $ticket->title . "\n"
            . $i18n->trans('date') . ': ' . $model->fecha . ' ' . $model->hora . "\n\n";

        $dwidth = $printer->linelen - 17;
        $ticket->body .= \sprintf("%5s", $i18n->trans('quantity-abb')) . " "
            . \sprintf("%-" . $dwidth . "s", $i18n->trans('description')) . " "
            . \sprintf("%11s", $i18n->trans('total')) . "\n";
        $ticket->body .= $this->getDashLine($printer) . "\n";
        $lines = $model->getLines();
        foreach ($lines as $line) {
            $description = \mb_substr($line->descripcion, 0, $dwidth);
            $total = $line->pvptotal * (100 + $line->iva + $line->recargo) / 100;
            $ticket->body .= \sprintf("%5s", $line->cantidad) . " "
                . \sprintf("%-" . $dwidth . "s", $description) . " "
                . \sprintf("%10s", $this->toolBox()->numbers()->format($total)) . "\n";
        }
        $ticket->body .= $this->getDashLine($printer) . "\n";
        $ticket->body .= \sprintf("%" . ($printer->linelen - 11) . "s", $i18n->trans('total')) . " "
            . \sprintf("%10s", $this->toolBox()->numbers()->format($model->total)) . "\n";

        foreach ($this->getSubtotals($model, $lines) as $item) {
            $ticket->body .= \sprintf("%" . ($printer->linelen - 11) . "s", $i18n->trans('tax-base') . ' ' . $item['taxp']) . " "
                . \sprintf("%10s", $this->toolBox()->numbers()->format($item['taxbase'])) . "\n"
                . \sprintf("%" . ($printer->linelen - 11) . "s", $item['tax']) . " "
                . \sprintf("%10s", $this->toolBox()->numbers()->format($item['taxamount'])) . "\n";
        }

        $ticket->body .= "\n\n\n\n\n\n" . $this->getCutCommand($printer);
        $ticket->save();

        $this->toolBox()->i18nLog()->notice('sending-to-printer');
        $this->redirect($model->url(), 1);
    }
}
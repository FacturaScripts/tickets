<?php
/**
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Base\ModelCore;
use FacturaScripts\Dinamic\Model\Impuesto;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Dinamic\Model\User;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
abstract class BaseTicket
{
    /** @var DummyPrintConnector */
    protected static $connector;

    /** @var Printer */
    protected static $escpos;

    /** @var Translator */
    protected static $i18n;

    abstract public static function print(ModelClass $model, TicketPrinter $printer, User $user, Agente $agent = null): bool;

    protected static function getBody(): string
    {
        // dejamos espacio
        static::$escpos->feed(4);

        // abrimos el cajón
        static::$escpos->pulse();

        // cortamos el papel
        static::$escpos->cut();

        // devolvemos los comandos de impresión
        $body = static::$escpos->getPrintConnector()->getData();

        // cerramos la impresora
        static::$escpos->close();

        // devolvemos los comandos de impresión en base64
        return base64_encode($body);
    }

    protected static function getReceipts(ModelClass $model, TicketPrinter $printer): string
    {
        $paid = 0;
        $total = 0;
        $receipts = '';
        $widthTotal = $printer->linelen - 22;

        foreach ($model->getReceipts() as $receipt) {
            if (false === empty($receipts)) {
                $receipts .= "\n";
            }

            $total += $receipt->importe;

            if (empty($receipt->fechapago)) {
                $datePaid = '';
            } else {
                $paid += $receipt->importe;
                $datePaid = date(ModelCore::DATE_STYLE, strtotime($receipt->fechapago));
            }

            $receipts .= sprintf("%10s", date(ModelCore::DATE_STYLE, strtotime($receipt->vencimiento))) . " "
                . sprintf("%10s", $datePaid)
                . sprintf("%" . $widthTotal . "s", ToolBox::numbers()::format($receipt->importe));
        }

        if (empty($receipts)) {
            return '';
        }

        return "\n\n"
            . sprintf("%" . $printer->linelen . "s", static::$i18n->trans('receipts')) . "\n"
            . sprintf("%10s", static::$i18n->trans('expiration-abb')) . " "
            . sprintf("%10s", static::$i18n->trans('paid')) . " "
            . sprintf("%" . $widthTotal . "s", static::$i18n->trans('total')) . "\n"
            . $printer->getDashLine() . "\n"
            . $receipts . "\n"
            . $printer->getDashLine() . "\n"
            . sprintf("%" . ($printer->linelen - $widthTotal - 1) . "s", static::$i18n->trans('total')) . " "
            . sprintf("%" . $widthTotal . "s", ToolBox::numbers()::format($total)) . "\n"
            . sprintf("%" . ($printer->linelen - $widthTotal - 1) . "s", static::$i18n->trans('paid')) . " "
            . sprintf("%" . $widthTotal . "s", ToolBox::numbers()::format($paid)) . "\n"
            . sprintf("%" . ($printer->linelen - $widthTotal - 1) . "s", static::$i18n->trans('pending')) . " "
            . sprintf("%" . $widthTotal . "s", ToolBox::numbers()::format($total - $paid)) . "\n\n";
    }

    protected static function getSubtotals(ModelClass $model, array $lines): array
    {
        $subtotals = [];
        $eud = $model->getEUDiscount();

        foreach ($lines as $line) {
            $key = $line->iva . '_' . $line->recargo;
            if (!isset($subtotals[$key])) {
                $subtotals[$key] = [
                    'tax' => $line->codimpuesto,
                    'taxp' => $line->iva . '%',
                    'taxbase' => 0,
                    'taxamount' => 0,
                    'taxsurcharge' => 0,
                    'taxsurchargep' => $line->recargo . '%',
                ];

                $impuesto = new Impuesto();
                if ($line->codimpuesto && $impuesto->loadFromCode($line->codimpuesto)) {
                    $subtotals[$key]['tax'] = $impuesto->descripcion;
                }
            }


            $subtotals[$key]['taxbase'] += $line->pvptotal * $eud;
            $subtotals[$key]['taxamount'] += $line->pvptotal * $eud * $line->iva / 100;
            $subtotals[$key]['taxsurcharge'] += $line->pvptotal * $eud * $line->recargo / 100;
        }

        return $subtotals;
    }

    protected static function getTrazabilidad(ModelClass $line, int $width): string
    {
        if (empty($line->referencia) || false === Plugins::isEnabled('Trazabilidad')) {
            return '';
        }

        // obtenemos los movimientos de trazabilidad de la línea
        $MovimientosTraza = $line->getMovimientosLinea();
        if (empty($MovimientosTraza)) {
            return '';
        }

        $numSeries = [];
        foreach ($MovimientosTraza as $movimientoTraza) {
            $numSeries[] = $movimientoTraza->numserie;
        }

        $result = '';
        $txtLine = '';
        foreach ($numSeries as $numserie) {
            // añadimos el numserie carácter por carácter
            // cuando llegamos al ancho máximo, añadimos un salto de línea
            // y continuamos con el mismo numserie hasta terminar con el
            // después continuamos con el siguiente numserie
            // separamos cada numserie con una coma
            $numserieLength = strlen($numserie);
            for ($i = 0; $i < $numserieLength; $i++) {
                if (strlen($txtLine) + 1 > $width) {
                    $result .= sprintf("%5s", '') . " "
                        . sprintf("%-" . $width . "s", $txtLine) . " "
                        . sprintf("%10s", '') . "\n";
                    $txtLine = '';
                }

                $txtLine .= $numserie[$i];
            }

            if (strlen($txtLine) + 2 > $width) {
                $result .= sprintf("%5s", '') . " "
                    . sprintf("%-" . $width . "s", $txtLine) . " "
                    . sprintf("%10s", '') . "\n";
                $txtLine = ', ';
                continue;
            }

            $txtLine .= ', ';
        }

        // comprobamos si los 2 últimos caracteres son una coma y un espacio
        // si es así, los eliminamos
        if (substr($txtLine, -2) === ', ') {
            $txtLine = substr($txtLine, 0, -2);
        }

        if (empty($txtLine)) {
            return '';
        }

        return $result . sprintf("%5s", '') . " "
            . sprintf("%-" . $width . "s", $txtLine) . " "
            . sprintf("%10s", '') . "\n";
    }

    protected static function init()
    {
        static::$i18n = new Translator();

        // inicializamos la impresora virtual, para posteriormente obtener los comandos
        static::$connector = new DummyPrintConnector();
        static::$escpos = new Printer(static::$connector);
        static::$escpos->initialize();
    }

    protected static function printLines(TicketPrinter $printer, array $lines): void
    {
        $th = '';
        $width = $printer->linelen - 17;

        if ($printer->print_lines_reference) {
          $th .= sprintf("%-" . $width . "s", static::$i18n->trans('reference-abb'));
        }

        if ($printer->print_lines_description) {
            $th .= empty($th) ? '' : ' ';
            $th .= sprintf("%-" . $width . "s", static::$i18n->trans('description-abb'));
        }

        if ($printer->print_lines_quantity) {
            $th .= empty($th) ? '' : ' ';
            $th .= sprintf("%5s", static::$i18n->trans('quantity-abb'));
        }

        if ($printer->print_lines_price) {
            $th .= empty($th) ? '' : ' ';
            $th .= sprintf("%-" . $width . "s", static::$i18n->trans('price-abb'));
        }

        if ($printer->print_lines_discount) {
            $th .= empty($th) ? '' : ' ';
            $th .= sprintf("%5s", static::$i18n->trans('discount-abb'));
        }

        if ($printer->print_lines_net) {
            $th .= empty($th) ? '' : ' ';
            $th .= sprintf("%-" . $width . "s", static::$i18n->trans('net-abb'));
        }

        if ($printer->print_lines_total) {
            $th .= empty($th) ? '' : ' ';
            $th .= sprintf("%-" . $width . "s", static::$i18n->trans('total-abb'));
        }

        if (empty($th)) {
            return;
        }

        static::$escpos->text(static::sanitize($th) . "\n");
        static::$escpos->text($printer->getDashLine() . "\n");

        foreach ($lines as $line) {
            $td = '';

            if ($printer->print_lines_reference) {
                $td .= sprintf("%-" . $width . "s", $line->referencia);
            }

            if ($printer->print_lines_description) {
                $td .= empty($td) ? '' : ' ';
                $description = mb_substr($line->descripcion, 0, $width);
                $td .= sprintf("%-" . $width . "s", $description);
            }

            if ($printer->print_lines_quantity) {
                $td .= empty($td) ? '' : ' ';
                $td .= sprintf("%5s", $line->cantidad);
            }

            if ($printer->print_lines_price) {
                $td .= empty($td) ? '' : ' ';
                $td .= sprintf("%-" . $width . "s", ToolBox::numbers()::format($line->pvpunitario));
            }

            if ($printer->print_lines_discount) {
                $td .= empty($td) ? '' : ' ';
                $dto = round($line->dtopor + $line->dtopor2, FS_NF0);
                $td .= sprintf("%5s", $dto . '%');
            }

            if ($printer->print_lines_net) {
                $td .= empty($td) ? '' : ' ';
                $td .= sprintf("%-" . $width . "s", ToolBox::numbers()::format($line->pvptotal));
            }

            if ($printer->print_lines_total) {
                $td .= empty($td) ? '' : ' ';
                $total = $line->pvptotal * (100 + $line->iva + $line->recargo) / 100;
                $td .= sprintf("%-" . $width . "s", ToolBox::numbers()::format($total));
            }

            static::$escpos->text(static::sanitize($td) . "\n");
            static::$escpos->text(static::getTrazabilidad($line, $width));
        }

        static::$escpos->text($printer->getDashLine() . "\n");
    }

    protected static function sanitize(?string $txt): string
    {
        $changes = ['/à/' => 'a', '/á/' => 'a', '/â/' => 'a', '/ã/' => 'a', '/ä/' => 'a',
            '/å/' => 'a', '/æ/' => 'ae', '/ç/' => 'c', '/è/' => 'e', '/é/' => 'e', '/ê/' => 'e',
            '/ë/' => 'e', '/ì/' => 'i', '/í/' => 'i', '/î/' => 'i', '/ï/' => 'i', '/ð/' => 'd',
            '/ñ/' => 'n', '/ò/' => 'o', '/ó/' => 'o', '/ô/' => 'o', '/õ/' => 'o', '/ö/' => 'o',
            '/ő/' => 'o', '/ø/' => 'o', '/ù/' => 'u', '/ú/' => 'u', '/û/' => 'u', '/ü/' => 'u',
            '/ű/' => 'u', '/ý/' => 'y', '/þ/' => 'th', '/ÿ/' => 'y',
            '/&quot;/' => '-', '/´/' => '/\'/', '/€/' => 'EUR', '/º/' => '.',
            '/À/' => 'A', '/Á/' => 'A', '/Â/' => 'A', '/Ä/' => 'A',
            '/Ç/' => 'C', '/È/' => 'E', '/É/' => 'E', '/Ê/' => 'E',
            '/Ë/' => 'E', '/Ì/' => 'I', '/Í/' => 'I', '/Î/' => 'I', '/Ï/' => 'I',
            '/Ñ/' => 'N', '/Ò/' => 'O', '/Ó/' => 'O', '/Ô/' => 'O', '/Ö/' => 'O',
            '/Ù/' => 'U', '/Ú/' => 'U', '/Û/' => 'U', '/Ü/' => 'U',
            '/Ý/' => 'Y', '/Ÿ/' => 'Y'
        ];

        return preg_replace(array_keys($changes), $changes, $txt);
    }

    protected static function setBody(ModelClass $model, TicketPrinter $printer): void
    {
        if (false === in_array($model->modelClassName(), ['PresupuestoCliente', 'PedidoCliente', 'AlbaranCliente', 'FacturaCliente'])) {
            return;
        }

        static::$escpos->setTextSize($printer->font_size, $printer->font_size);

        // añadimos las líneas
        $lines = $model->getLines();
        static::printLines($printer, $lines);

        foreach (static::getSubtotals($model, $lines) as $item) {
            $text = sprintf("%" . ($printer->linelen - 11) . "s", static::$i18n->trans('tax-base') . ' ' . $item['taxp']) . " "
                . sprintf("%10s", ToolBox::numbers()::format($item['taxbase'])) . "\n"
                . sprintf("%" . ($printer->linelen - 11) . "s", $item['tax']) . " "
                . sprintf("%10s", ToolBox::numbers()::format($item['taxamount']));
            static::$escpos->text(static::sanitize($text) . "\n");

            if ($item['taxsurcharge']) {
                $text = sprintf("%" . ($printer->linelen - 11) . "s", "RE " . $item['taxsurchargep']) . " "
                    . sprintf("%10s", ToolBox::numbers()::format($item['taxsurcharge']));
                static::$escpos->text(static::sanitize($text) . "\n");
            }
        }
        static::$escpos->text($printer->getDashLine() . "\n");

        // añadimos los totales
        $text = sprintf("%" . ($printer->linelen - 11) . "s", static::$i18n->trans('total')) . " "
            . sprintf("%10s", ToolBox::numbers()::format($model->total));

        if (property_exists($model, 'tpv_efectivo') && $model->tpv_efectivo > 0) {
            $text .= sprintf("%" . ($printer->linelen - 11) . "s", static::$i18n->trans('cash')) . " "
                . sprintf("%10s", ToolBox::numbers()::format($model->tpv_efectivo)) . "\n";
        }
        if (property_exists($model, 'tpv_cambio') && $model->tpv_cambio > 0) {
            $text .= sprintf("%" . ($printer->linelen - 11) . "s", static::$i18n->trans('money-change')) . " "
                . sprintf("%10s", ToolBox::numbers()::format($model->tpv_cambio)) . "\n";
        }

        static::$escpos->text(static::sanitize($text) . "\n");

        if ($printer->print_invoice_receipts && $model->modelClassName() === 'FacturaCliente') {
            static::$escpos->text(static::sanitize(static::getReceipts($model, $printer)));
        }
    }

    protected static function setFooter(ModelClass $model, TicketPrinter $printer): void
    {
        static::$escpos->setTextSize($printer->footer_font_size, $printer->footer_font_size);

        // añadimos el pie de página
        if ($printer->footer) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            static::$escpos->text("\n" . static::sanitize($printer->footer) . "\n");
            static::$escpos->setJustification(Printer::JUSTIFY_LEFT);
        }
    }

    protected static function setHeader(ModelClass $model, TicketPrinter $printer, string $title):void
    {
        static::$escpos->setTextSize($printer->head_font_size, $printer->head_font_size);

        if ($printer->print_stored_logo) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            // imprimimos el logotipo almacenado en la impresora
            static::$connector->write("\x1Cp\x01\x00\x00");
            static::$escpos->feed();
        }

        // obtenemos los datos de la empresa
        $company = $model->getCompany();

        // imprimimos el nombre corto de la empresa
        if ($printer->print_comp_shortname) {
            static::$escpos->setTextSize($printer->head_font_size + 1, $printer->head_font_size + 1);
            static::$escpos->text(static::sanitize($company->nombrecorto) . "\n");
            static::$escpos->setTextSize($printer->head_font_size, $printer->head_font_size);
        }

        // imprimimos el nombre de la empresa
        static::$escpos->text(static::sanitize($company->nombre) . "\n");
        static::$escpos->setJustification();

        // imprimimos la dirección de la empresa
        static::$escpos->text(static::sanitize($company->direccion) . "\n");
        static::$escpos->text(static::sanitize("CP: " . $company->codpostal . ', ' . $company->ciudad) . "\n");
        static::$escpos->text(static::sanitize($company->tipoidfiscal . ': ' . $company->cifnif) . "\n\n");

        if ($printer->print_comp_tlf) {
            if (false === empty($company->telefono1) && false === empty($company->telefono2)) {
                static::$escpos->text(static::sanitize($company->telefono1 . ' / ' . $company->telefono2) . "\n");
            } elseif (false === empty($company->telefono1)) {
                static::$escpos->text(static::sanitize($company->telefono1) . "\n");
            } elseif (false === empty($company->telefono2)) {
                static::$escpos->text(static::sanitize($company->telefono2) . "\n");
            }
        }

        // imprimimos el título del documento
        static::$escpos->text(static::sanitize($title) . "\n");

        // si es un documento de venta
        // imprimimos la fecha y el cliente
        if (in_array($model->modelClassName(), ['PresupuestoCliente', 'PedidoCliente', 'AlbaranCliente', 'FacturaCliente'])) {
            static::$escpos->text(static::sanitize(static::$i18n->trans('date') . ': ' . $model->fecha . ' ' . $model->hora) . "\n");
            static::$escpos->text(static::sanitize(static::$i18n->trans('customer') . ': ' . $model->nombrecliente) . "\n\n");
        }

        // añadimos la cabecera
        if ($printer->head) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            static::$escpos->text(static::sanitize($printer->head) . "\n\n");
            static::$escpos->setJustification();
        }
    }
}
<?php
/**
 * Copyright (C) 2023-2024 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Translator;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\Impuesto;
use FacturaScripts\Dinamic\Model\PrePago;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Dinamic\Model\User;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Pais;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
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

    /** @var array */
    protected static $lines;

    private static $openDrawer = true;

    abstract public static function print(ModelClass $model, TicketPrinter $printer, User $user, ?Agente $agent = null): bool;

    public static function setLines(?array $lines = null): void
    {
        static::$lines = $lines;
    }

    protected static function setOpenDrawer(bool $openDrawer): void
    {
        static::$openDrawer = $openDrawer;
    }

    protected static function getBody(): string
    {
        // dejamos espacio
        static::$escpos->feed(4);

        // abrimos el cajón
        if (static::$openDrawer) {
            static::$escpos->pulse();
        }

        // cortamos el papel
        static::$escpos->cut();

        // devolvemos los comandos de impresión
        $body = static::$escpos->getPrintConnector()->getData();

        // cerramos la impresora
        static::$escpos->close();

        // devolvemos los comandos de impresión en base64
        return base64_encode($body);
    }

    protected static function getLines(ModelClass $model): array
    {
        return self::$lines ?? $model->getLines();
    }

    protected static function getPaymentMethods(ModelClass $model, TicketPrinter $printer): string
    {
        $paymentMethods = [];

        // si es una factura, obtenemos sus recibos
        if ($model->modelClassName() === 'FacturaCliente') {
            $receipts = $model->getReceipts();
            foreach ($receipts as $receipt) {
                if (isset($paymentMethods[$receipt->codpago])) {
                    $paymentMethods[$receipt->codpago] += $receipt->importe;
                    continue;
                }

                $paymentMethods[$receipt->codpago] = $receipt->importe;
            }
        } elseif (Plugins::isEnabled('PrePagos')) {
            // si no es una factura buscamos si tiene anticipos
            $prepagoModel = new PrePago();
            $where = [
                new DataBaseWhere('modelid', $model->id()),
                new DataBaseWhere('modelname', $model->modelClassName()),
            ];
            foreach ($prepagoModel->all($where, [], 0, 0) as $prepago) {
                if (isset($paymentMethods[$prepago->codpago])) {
                    $paymentMethods[$prepago->codpago] += $prepago->amount;
                    continue;
                }

                $paymentMethods[$prepago->codpago] = $prepago->amount;
            }
        }

        // si no hay formas de pago, salimos
        if (empty($paymentMethods)) {
            return '';
        }

        $txt = '';
        $cont = 0;
        $widthTotal = $printer->linelen - 22;

        // pintamos las formas de pago
        foreach ($paymentMethods as $codpago => $total) {
            $payment = new FormaPago();
            if (false === $payment->load($codpago)) {
                continue;
            }

            if (false === empty($txt)) {
                $txt .= "\n";
            }

            $cont ++;
            $width = $cont === 1
                ? $printer->linelen - $widthTotal
                : $printer->linelen - $widthTotal - 1;

            $txt .= sprintf("%-" . $width . "s", $payment->descripcion) . " "
                . sprintf("%" . $widthTotal . "s", Tools::number($total));
        }

        // si no hay texto de las formas de pago, salimos
        if (empty($txt)) {
            return '';
        }

        return sprintf("%" . $printer->linelen . "s", static::$i18n->trans('payment-methods')) . "\n"
            . $printer->getDashLine() . "\n"
            . $txt
            . "\n\n";
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
                $datePaid = Tools::date($receipt->fechapago);
            }

            $receipts .= sprintf("%10s", Tools::date($receipt->vencimiento)) . " "
                . sprintf("%10s", $datePaid) . " "
                . sprintf("%" . $widthTotal . "s", Tools::number($receipt->importe));
        }

        if (empty($receipts)) {
            return '';
        }

        return sprintf("%" . $printer->linelen . "s", static::$i18n->trans('receipts')) . "\n"
            . sprintf("%10s", static::$i18n->trans('expiration-abb')) . " "
            . sprintf("%10s", static::$i18n->trans('paid')) . " "
            . sprintf("%" . ($widthTotal - 1) . "s", static::$i18n->trans('total')) . "\n"
            . $printer->getDashLine() . "\n"
            . $receipts . "\n"
            . $printer->getDashLine() . "\n"
            . sprintf("%" . ($printer->linelen - $widthTotal - 1) . "s", static::$i18n->trans('total')) . " "
            . sprintf("%" . $widthTotal . "s", Tools::number($total)) . "\n"
            . sprintf("%" . ($printer->linelen - $widthTotal - 1) . "s", static::$i18n->trans('paid')) . " "
            . sprintf("%" . $widthTotal . "s", Tools::number($paid)) . "\n"
            . sprintf("%" . ($printer->linelen - $widthTotal - 1) . "s", static::$i18n->trans('pending')) . " "
            . sprintf("%" . $widthTotal . "s", Tools::number($total - $paid)) . "\n\n";
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
                if ($line->codimpuesto && $impuesto->load($line->codimpuesto)) {
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

    protected static function init(): void
    {
        static::$i18n = new Translator();

        // inicializamos la impresora virtual, para posteriormente obtener los comandos
        static::$connector = new DummyPrintConnector();
        static::$escpos = new Printer(static::$connector);
        static::$connector->clear();
        static::$escpos->initialize();
    }

    protected static function printLines(TicketPrinter $printer, array $lines): void
    {
        $th = '';
        $width = $printer->linelen;

        if ($printer->print_lines_quantity) {
            $th .= sprintf("%5s", static::$i18n->trans('quantity-abb')) . ' ';
            $width -= 6;
        }

        if ($printer->print_lines_net || $printer->print_lines_total) {
            $width -= 11;
        }

        if ($printer->print_lines_reference) {
            $th .= sprintf("%-" . $width . "s", static::$i18n->trans('reference-abb'));
        } elseif ($printer->print_lines_description) {
            $th .= sprintf("%-" . $width . "s", static::$i18n->trans('description-abb'));
        }

        if ($printer->print_lines_net) {
            $th .= sprintf("%11s", static::$i18n->trans('net-abb'));
        } elseif ($printer->print_lines_total) {
            $th .= sprintf("%11s", static::$i18n->trans('total-abb'));
        }
        if (empty($th)) {
            return;
        }

        static::$escpos->text(static::sanitize($th) . "\n");
        static::$escpos->text($printer->getDashLine() . "\n");

        foreach ($lines as $line) {
            $td = '';
            if ($printer->print_lines_quantity) {
                $td .= sprintf("%5s", $line->cantidad) . ' ';
            }

            if ($printer->print_lines_reference) {
                $td .= sprintf("%-" . $width . "s", $line->referencia);
            } elseif ($printer->print_lines_description) {
                $td .= sprintf("%-" . $width . "s", substr($line->descripcion, 0, $width));
            }

            if ($printer->print_lines_net) {
                $td .= sprintf("%11s", Tools::number($line->pvptotal));
            } elseif ($printer->print_lines_total) {
                $total = $line->pvptotal * (100 + $line->iva + $line->recargo) / 100;
                $td .= sprintf("%11s", Tools::number($total));
            }

            $jump = false;
            if ($printer->print_lines_reference && $printer->print_lines_description) {
                $td .= "\n" . sprintf("%-" . $printer->linelen . "s", substr($line->descripcion, 0, $printer->linelen));
                $jump = true;
            }

            if ($printer->print_lines_price) {
                $td .= "\n" . sprintf("%11s", Tools::lang()->trans('price-abb') . ': ' . Tools::number($line->pvpunitario));
                $jump = true;
            }

            if ($printer->print_lines_price_tax) {
                $priceVat = $line->pvpunitario * (100 + $line->iva + $line->recargo) / 100;
                $td .= "\n" . sprintf("%11s", Tools::lang()->trans('price-abb') . ': ' . Tools::number($priceVat));
                $jump = true;
            }

            if ($printer->print_lines_discount && $line->dtopor > 0) {
                $td .= $printer->print_lines_price ? ' ' : "\n";
                $td .= sprintf("%11s", Tools::lang()->trans('discount-abb') . ': ' . $line->dtopor . '%');
                $jump = true;
            }

            if ($printer->print_lines_net && $printer->print_lines_total) {
                $td .= $printer->print_lines_price ? ' ' : "\n";
                $td .= sprintf("%11s", Tools::lang()->trans('net-abb') . ': ' . Tools::number($line->pvptotal));
                $jump = true;
            }

            if ($jump) {
                $td .= "\n";
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

        // establecemos el tamaño de la fuente
        static::$escpos->setTextSize($printer->font_size, $printer->font_size);

        // añadimos las líneas
        $lines = self::getLines($model);
        static::printLines($printer, $lines);

        foreach (static::getSubtotals($model, $lines) as $item) {
            $text = sprintf("%" . ($printer->linelen - 11) . "s", static::$i18n->trans('tax-base') . ' ' . $item['taxp']) . " "
                . sprintf("%10s", Tools::number($item['taxbase'])) . "\n"
                . sprintf("%" . ($printer->linelen - 11) . "s", $item['tax']) . " "
                . sprintf("%10s", Tools::number($item['taxamount']));
            static::$escpos->text(static::sanitize($text) . "\n");

            if ($item['taxsurcharge']) {
                $text = sprintf("%" . ($printer->linelen - 11) . "s", "RE " . $item['taxsurchargep']) . " "
                    . sprintf("%10s", Tools::number($item['taxsurcharge']));
                static::$escpos->text(static::sanitize($text) . "\n");
            }
        }
        static::$escpos->text($printer->getDashLine() . "\n");

        // añadimos los totales
        $text = sprintf("%" . ($printer->linelen - 11) . "s", static::$i18n->trans('total')) . " "
            . sprintf("%10s", Tools::number($model->total));

        if (property_exists($model, 'tpv_efectivo') && $model->tpv_efectivo > 0) {
            $text .= sprintf("%" . ($printer->linelen - 11) . "s", static::$i18n->trans('cash')) . " "
                . sprintf("%10s", Tools::number($model->tpv_efectivo)) . "\n";
        }
        if (property_exists($model, 'tpv_cambio') && $model->tpv_cambio > 0) {
            $text .= sprintf("%" . ($printer->linelen - 11) . "s", static::$i18n->trans('money-change')) . " "
                . sprintf("%10s", Tools::number($model->tpv_cambio)) . "\n";
        }

        static::$escpos->text(static::sanitize($text) . "\n\n");

        // añadimos las formas de pago
        if ($printer->print_payment_methods) {
            static::$escpos->text(static::sanitize(static::getPaymentMethods($model, $printer)));
        }

        // añadimos los recibos
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

    protected static function setHeader(ModelClass $model, TicketPrinter $printer, string $title): void
    {
        if ($printer->print_stored_logo) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            // imprimimos el logotipo almacenado en la impresora
            static::$connector->write("\x1Cp\x01\x00\x00");
            static::$escpos->feed();
        }

        // obtenemos los datos de la empresa
        $company = $model->getCompany();

        // establecemos el tamaño de la fuente
        static::$escpos->setTextSize($printer->title_font_size, $printer->title_font_size);

        // imprimimos el nombre corto de la empresa
        if ($printer->print_comp_shortname) {
            static::$escpos->text(static::sanitize($company->nombrecorto) . "\n");
            static::$escpos->setTextSize($printer->head_font_size, $printer->head_font_size);

            // imprimimos el nombre de la empresa
            static::$escpos->text(static::sanitize($company->nombre) . "\n");
        } else {
            // imprimimos el nombre de la empresa
            static::$escpos->text(static::sanitize($company->nombre) . "\n");
            static::$escpos->setTextSize($printer->head_font_size, $printer->head_font_size);
        }

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

        static::setHeaderTPV($model, $printer);

        // si es un documento de venta
        // imprimimos la fecha y el cliente
        if (in_array($model->modelClassName(), ['PresupuestoCliente', 'PedidoCliente', 'AlbaranCliente', 'FacturaCliente'])) {
            static::$escpos->text(static::sanitize(static::$i18n->trans('date') . ': ' . $model->fecha . ' ' . $model->hora) . "\n");
            static::$escpos->text(static::sanitize(static::$i18n->trans('customer') . ': ' . $model->nombrecliente) . "\n\n");

            // si se debe imprimir la dirección de envio
            if ($printer->print_shipping_address) {
                static::$escpos->text(static::sanitize(static::$i18n->trans('address') . ': '));
                $shippingAddress = new Contacto();
                
                if(empty($model->idcontactoenv) && empty($model->direccion)){
                    // si las dos están vacías entonces un -
                    static::$escpos->text(static::sanitize(' - '));
                    
                } else if ($shippingAddress->load($model->idcontactoenv)) {
                    // si existe el contacto de envio lo imprimimos
                    static::$escpos->text(static::sanitize($shippingAddress->direccion) . ", ");
                    static::$escpos->text(static::sanitize(
                            $shippingAddress->codpostal . ' (' . $shippingAddress->ciudad . '), ' . $shippingAddress->provincia
                        ) . ", ");
                    $pais = new Pais();
                    if ($pais->load($shippingAddress->codpais)) {
                        static::$escpos->text(static::sanitize($pais->nombre) . "\n\n");
                    } else {
                        static::$escpos->text(static::sanitize($shippingAddress->codpais) . "\n\n");
                    }

                }else{
                    // sino imprimimos la direccion de factura
                    static::$escpos->text(static::sanitize($model->direccion) . "\n");
                }
            }
        }

        // añadimos la cabecera
        if ($printer->head) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            static::$escpos->text(static::sanitize($printer->head) . "\n\n");
            static::$escpos->setJustification();
        }
    }

    protected static function setHeaderTPV(ModelClass $model, TicketPrinter $printer): void
    {
        if (false === Plugins::isEnabled('TPVneo') ||
            false === isset($printer->print_name_terminal) ||
            false === $printer->print_name_terminal ||
            false === isset($model->idtpv) ||
            empty($model->idtpv)) {
            return;
        }

        static::$escpos->text(
            static::sanitize(static::$i18n->trans('pos-terminal') . ': ' . $model->getTerminal()->name) . "\n"
        );
    }
}

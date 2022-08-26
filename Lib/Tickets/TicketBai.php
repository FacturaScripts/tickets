<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\Base\SalesDocumentLine;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Impuesto;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\Tickets\Model\Ticket;
use FacturaScripts\Plugins\Tickets\Model\TicketPrinter;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class TicketBai
{
    /**
     * @param SalesDocument $doc
     * @param TicketPrinter $printer
     * @param User $user
     * @param Agente|null $agent
     * @return bool
     */
    public static function print($doc, TicketPrinter $printer, User $user, Agente $agent = null): bool
    {
        $i18n = ToolBox::i18n();

        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $ticket->title = $i18n->trans($doc->modelClassName() . '-min') . ' ' . $doc->codigo;
        $ticket->body = static::getBody($doc, $i18n, $printer, $ticket->title);
        $ticket->base64 = true;
        $ticket->appversion = 1;

        if ($agent) {
            $ticket->codagente = $agent->codagente;
        }

        return $ticket->save();
    }

    protected static function getBody($doc, $i18n, $printer, $title): string
    {
        // inicializamos la impresora virtual, para posteriormente obtener los comandos
        $connector = new DummyPrintConnector();
        $escpos = new Printer($connector);
        $escpos->initialize();

        // imprimimos el nombre de la empresa
        $escpos->setTextSize(2, 2);
        $company = $doc->getCompany();
        $escpos->text(static::sanitize($company->nombre) . "\n");
        $escpos->setTextSize(1, 1);
        $escpos->setJustification();

        // imprimimos la dirección de la empresa
        $escpos->text(static::sanitize($company->direccion) . "\n");
        $escpos->text(static::sanitize("CP: " . $company->codpostal . ', ' . $company->ciudad) . "\n");
        $escpos->text(static::sanitize($company->tipoidfiscal . ': ' . $company->cifnif) . "\n\n");
        $escpos->text(static::sanitize($title) . "\n");
        $escpos->text(static::sanitize($i18n->trans('date') . ': ' . $doc->fecha . ' ' . $doc->hora) . "\n\n");

        // añadimos la cabecera
        if ($printer->head) {
            $escpos->setJustification(Printer::JUSTIFY_CENTER);
            $escpos->text(static::sanitize($printer->head) . "\n\n");
            $escpos->setJustification();
        }

        // añadimos las líneas
        $width = $printer->linelen - 17;
        $text = sprintf("%5s", $i18n->trans('quantity-abb')) . " "
            . sprintf("%-" . $width . "s", $i18n->trans('description')) . " "
            . sprintf("%11s", $i18n->trans('total'));
        $escpos->text(static::sanitize($text) . "\n");
        $escpos->text($printer->getDashLine() . "\n");

        $lines = $doc->getLines();
        foreach ($lines as $line) {
            $description = mb_substr($line->descripcion, 0, $width);
            $total = $line->pvptotal * (100 + $line->iva + $line->recargo) / 100;
            $text = sprintf("%5s", $line->cantidad) . " "
                . sprintf("%-" . $width . "s", $description) . " "
                . sprintf("%10s", ToolBox::numbers()::format($total));
            $escpos->text(static::sanitize($text) . "\n");
        }
        $escpos->text($printer->getDashLine() . "\n");

        // añadimos los totales
        $text = sprintf("%" . ($printer->linelen - 11) . "s", $i18n->trans('total')) . " "
            . sprintf("%10s", ToolBox::numbers()::format($doc->total));
        $escpos->text(static::sanitize($text) . "\n");

        foreach (self::getSubtotals($doc, $lines) as $item) {
            $text = sprintf("%" . ($printer->linelen - 11) . "s", $i18n->trans('tax-base') . ' ' . $item['taxp']) . " "
                . sprintf("%10s", ToolBox::numbers()::format($item['taxbase'])) . "\n"
                . sprintf("%" . ($printer->linelen - 11) . "s", $item['tax']) . " "
                . sprintf("%10s", ToolBox::numbers()::format($item['taxamount']));
            $escpos->text(static::sanitize($text) . "\n");
        }

        // añadimos el qr de ticketbai
        if (isset($doc->tbaicodbar)) {
            $escpos->setJustification(Printer::JUSTIFY_CENTER);
            $escpos->text("\n" . $doc->tbaicodbar . "\n");
            $escpos->qrCode($doc->codigo, Printer::QR_ECLEVEL_L, 13);
            $escpos->setJustification();
        }

        // añadimos el pie de página
        if ($printer->footer) {
            $escpos->setJustification(Printer::JUSTIFY_CENTER);
            $escpos->text("\n" . static::sanitize($printer->footer) . "\n");
            $escpos->setJustification(Printer::JUSTIFY_LEFT);
        }

        // dejamos espacio, abrimos el cajón y cortamos el papel
        $escpos->feed(6);
        $escpos->pulse();
        $escpos->cut();

        // devolvemos los comandos de impresión
        $body = $escpos->getPrintConnector()->getData();
        $escpos->close();
        return base64_encode($body);
    }

    /**
     * @param SalesDocument $doc
     * @param SalesDocumentLine $lines
     *
     * @return array
     */
    protected static function getSubtotals($doc, $lines): array
    {
        $subtotals = [];
        $eud = $doc->getEUDiscount();

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
}
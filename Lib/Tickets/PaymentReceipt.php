<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\Tickets\Model\Ticket;
use FacturaScripts\Plugins\Tickets\Model\TicketPrinter;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;

class PaymentReceipt
{
    public static function print(ReciboCliente $receipt, TicketPrinter $printer, User $user): bool
    {
        $i18n = ToolBox::i18n();

        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $ticket->title = $i18n->trans('receipt') . ' ' . $receipt->codigofactura;
        $ticket->body = static::getBody($receipt, $i18n, $printer);
        $ticket->base64 = true;
        $ticket->appversion = 1;

        return $ticket->save();
    }

    protected static function getBody(ReciboCliente $receipt, Translator $i18n, TicketPrinter $printer): string
    {
        // inicializamos la impresora virtual, para posteriormente obtener los comandos
        $connector = new DummyPrintConnector();
        $escpos = new Printer($connector);
        $escpos->initialize();

        // imprimimos el nombre de la empresa
        $escpos->setTextSize(2, 2);
        $company = $receipt->getCompany();
        $escpos->text(static::sanitize($company->nombre) . "\n");
        $escpos->setTextSize(1, 1);
        $escpos->setJustification();

        // imprimimos la dirección de la empresa
        $escpos->text(static::sanitize($company->direccion) . "\n");
        $escpos->text(static::sanitize("CP: " . $company->codpostal . ', ' . $company->ciudad) . "\n");
        $escpos->text(static::sanitize($company->tipoidfiscal . ': ' . $company->cifnif) . "\n\n");

        // imprimimos los datos del recibo
        $escpos->text(static::sanitize($i18n->trans('invoice') . ': ' . $receipt->codigofactura) . "\n");
        $escpos->text(static::sanitize($i18n->trans('customer') . ': ' . $receipt->getInvoice()->nombrecliente) . "\n");
        $escpos->text(static::sanitize($i18n->trans('receipt') . ': ' . $receipt->numero) . "\n");
        $escpos->text(static::sanitize($i18n->trans('date') . ': ' . $receipt->fecha) . "\n\n");
        $escpos->text(static::sanitize($i18n->trans('amount') . ': ' . $receipt->importe . ' ' . $receipt->coddivisa) . "\n");
        if ($receipt->pagado) {
            $escpos->text(static::sanitize($i18n->trans('payment-date') . ': ' . $receipt->fechapago) . "\n\n");
        } else {
            $escpos->text(static::sanitize($i18n->trans('expiration') . ': ' . $receipt->vencimiento) . "\n\n");
        }

        if ($receipt->observaciones) {
            $escpos->text(static::sanitize($i18n->trans('observations') . ': ' . $receipt->observaciones) . "\n\n");
        }

        // añadimos la cabecera
        if ($printer->head) {
            $escpos->setJustification(Printer::JUSTIFY_CENTER);
            $escpos->text(static::sanitize($printer->head) . "\n\n");
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
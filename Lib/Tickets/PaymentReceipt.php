<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Dinamic\Model\ReciboCliente;
use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Dinamic\Model\User;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PaymentReceipt extends BaseTicket
{
    public static function print(ReciboCliente $receipt, TicketPrinter $printer, User $user): bool
    {
        static::init();

        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $ticket->title = static::$i18n->trans('receipt') . ' ' . $receipt->codigofactura;

        static::setHeader($receipt, $printer, $ticket->title);
        static::setBody($receipt, $printer);
        static::setFooter($receipt, $printer);
        $ticket->body = static::getBody();
        $ticket->base64 = true;
        $ticket->appversion = 1;

        return $ticket->save();
    }

    protected static function setBody($receipt, TicketPrinter $printer): void
    {
        static::$escpos->setTextSize($printer->font_size, $printer->font_size);

        // imprimimos los datos del recibo
        static::$escpos->text(static::sanitize(static::$i18n->trans('invoice') . ': ' . $receipt->codigofactura) . "\n");
        static::$escpos->text(static::sanitize(static::$i18n->trans('customer') . ': ' . $receipt->getInvoice()->nombrecliente) . "\n");
        static::$escpos->text(static::sanitize(static::$i18n->trans('receipt') . ': ' . $receipt->numero) . "\n");
        static::$escpos->text(static::sanitize(static::$i18n->trans('date') . ': ' . $receipt->fecha) . "\n\n");
        static::$escpos->text(static::sanitize(static::$i18n->trans('amount') . ': ' . $receipt->importe . ' ' . $receipt->coddivisa) . "\n");

        if ($receipt->pagado) {
            static::$escpos->text(static::sanitize(static::$i18n->trans('payment-date') . ': ' . $receipt->fechapago) . "\n\n");
        } else {
            static::$escpos->text(static::sanitize(static::$i18n->trans('expiration') . ': ' . $receipt->vencimiento) . "\n\n");
        }

        if ($receipt->observaciones) {
            static::$escpos->text(static::sanitize(static::$i18n->trans('observations') . ': ' . $receipt->observaciones) . "\n\n");
        }
    }
}
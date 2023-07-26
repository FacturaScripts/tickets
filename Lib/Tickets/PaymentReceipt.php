<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Dinamic\Model\User;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class PaymentReceipt extends BaseTicket
{
    public static function print(ModelClass $model, TicketPrinter $printer, User $user, Agente $agent = null): bool
    {
        static::init();

        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $ticket->title = static::$i18n->trans('receipt') . ' ' . $model->codigofactura;

        static::setHeader($model, $printer, $ticket->title);
        static::setBody($model, $printer);
        static::setFooter($model, $printer);
        $ticket->body = static::getBody();
        $ticket->base64 = true;
        $ticket->appversion = 1;

        return $ticket->save();
    }

    protected static function setBody(ModelClass $model, TicketPrinter $printer): void
    {
        static::$escpos->setTextSize($printer->font_size, $printer->font_size);

        // imprimimos los datos del recibo
        static::$escpos->text(static::sanitize(static::$i18n->trans('invoice') . ': ' . $model->codigofactura) . "\n");
        static::$escpos->text(static::sanitize(static::$i18n->trans('customer') . ': ' . $model->getInvoice()->nombrecliente) . "\n");
        static::$escpos->text(static::sanitize(static::$i18n->trans('receipt') . ': ' . $model->numero) . "\n");
        static::$escpos->text(static::sanitize(static::$i18n->trans('date') . ': ' . $model->fecha) . "\n\n");
        static::$escpos->text(static::sanitize(static::$i18n->trans('amount') . ': ' . $model->importe . ' ' . $model->coddivisa) . "\n");

        if ($model->pagado) {
            static::$escpos->text(static::sanitize(static::$i18n->trans('payment-date') . ': ' . $model->fechapago) . "\n\n");
        } else {
            static::$escpos->text(static::sanitize(static::$i18n->trans('expiration') . ': ' . $model->vencimiento) . "\n\n");
        }

        if ($model->observaciones) {
            static::$escpos->text(static::sanitize(static::$i18n->trans('observations') . ': ' . $model->observaciones) . "\n\n");
        }
    }
}
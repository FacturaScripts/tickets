<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\ServicioAT;
use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Dinamic\Model\User;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Service extends BaseTicket
{
    public static function print(ServicioAT $service, TicketPrinter $printer, User $user, Agente $agent = null): bool
    {
        static::init();

        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $ticket->title = static::$i18n->trans('service') . ' ' . $service->primaryColumnValue();

        static::setHeader($service, $printer, $ticket->title);
        static::setBody($service, $printer);
        static::setFooter($service, $printer);
        $ticket->body = static::getBody();
        $ticket->base64 = true;
        $ticket->appversion = 1;

        if ($agent) {
            $ticket->codagente = $agent->codagente;
        }

        return $ticket->save();
    }

    protected static function setBody($service, TicketPrinter $printer): void
    {
        static::$escpos->setTextSize($printer->font_size, $printer->font_size);

        static::$escpos->text(static::sanitize(static::$i18n->trans('description') . ': ' . $service->descripcion) . "\n");

        if ($service->material) {
            static::$escpos->text(static::sanitize(static::$i18n->trans('material') . ': ' . $service->material) . "\n");
        }
    }
}
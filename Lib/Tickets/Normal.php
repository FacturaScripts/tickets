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
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Normal extends BaseTicket
{
    public static function print(ModelClass $model, TicketPrinter $printer, User $user, Agente $agent = null): bool
    {
        static::init();

        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $ticket->title = self::$i18n->trans($model->modelClassName() . '-min') . ' ' . $model->codigo;

        static::setHeader($model, $printer, $ticket->title);
        static::setBody($model, $printer);
        static::setFooter($model, $printer);
        $ticket->body = static::getBody();
        $ticket->base64 = true;
        $ticket->appversion = 1;

        if ($agent) {
            $ticket->codagente = $agent->codagente;
        }

        return $ticket->save();
    }
}
<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use Mike42\Escpos\Printer;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class TicketBai extends Normal
{
    protected static function setBody($doc, TicketPrinter $printer): void
    {
        parent::setBody($doc, $printer);

        // añadimos el qr de ticketbai
        if (isset($doc->tbaicodbar)) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            static::$escpos->text("\n" . $doc->tbaicodbar . "\n");
            static::$escpos->qrCode($doc->tbaiurl, Printer::QR_ECLEVEL_L, 7);
            static::$escpos->setJustification();
        }
    }
}
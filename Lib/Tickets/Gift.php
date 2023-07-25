<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Gift extends Normal
{
    protected static function setBody($doc, TicketPrinter $printer): void
    {
        static::$escpos->setTextSize($printer->font_size, $printer->font_size);

        $width = $printer->linelen - 17;
        $text = sprintf("%5s", static::$i18n->trans('quantity-abb')) . " "
            . sprintf("%-" . $width . "s", static::$i18n->trans('description')) . " ";

        static::$escpos->text(static::sanitize($text) . "\n");
        static::$escpos->text($printer->getDashLine() . "\n");

        foreach ($doc->getLines() as $line) {
            $description = mb_substr($line->descripcion, 0, $width);
            $text = sprintf("%5s", $line->cantidad) . " "
                . sprintf("%-" . $width . "s", $description) . " ";

            static::$escpos->text(static::sanitize($text) . "\n");
        }

        static::$escpos->text($printer->getDashLine() . "\n");
    }
}
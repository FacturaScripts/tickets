<?php
/**
 * Copyright (C) 2019-2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Tools;

use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Gift extends Normal
{
    protected static function setBody(ModelClass $model, TicketPrinter $printer): void
    {
        static::$escpos->setTextSize($printer->font_size, $printer->font_size);

        $th = '';
        $width = $printer->linelen;

        if ($printer->print_lines_quantity) {
            $th .= sprintf("%5s", static::$i18n->trans('quantity-abb')) . ' ';
            $width -= 6;
        }

        if ($printer->print_lines_reference) {
            $th .= sprintf("%-" . $width . "s", static::$i18n->trans('reference-abb'));
        } elseif ($printer->print_lines_description) {
            $th .= sprintf("%-" . $width . "s", static::$i18n->trans('description-abb'));
        }
        if (empty($th)) {
            return;
        }

        static::$escpos->text(static::sanitize($th) . "\n");
        static::$escpos->text($printer->getDashLine() . "\n");

        foreach (self::getLines($model) as $line) {
            $td = '';
            if ($printer->print_lines_quantity) {
                $td .= sprintf("%5s", $line->cantidad) . ' ';
            }

            if ($printer->print_lines_reference) {
                $td .= sprintf("%-" . $width . "s", $line->referencia);
            } elseif ($printer->print_lines_description) {
                $td .= sprintf("%-" . $width . "s", substr($line->descripcion, 0, $width));
            }

            static::$escpos->text(static::sanitize($td) . "\n");
        }

        static::$escpos->text($printer->getDashLine() . "\n");
    }
}

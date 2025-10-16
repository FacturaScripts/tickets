<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Template\ExtensionsTrait;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Dinamic\Model\User;

/**
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Gift extends BaseTicket
{
    use ExtensionsTrait;

    public static function print(ModelClass $model, TicketPrinter $printer, User $user, ?Agente $agent = null): bool
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

    protected static function setBody(ModelClass $model, TicketPrinter $printer): void
    {
        $extensionVar = new static();
        $extensionVar->pipe('setBodyBefore', $model, $printer);

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

        $extensionVar->pipe('setBodyAfter', $model, $printer);
    }
}

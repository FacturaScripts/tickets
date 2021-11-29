<?php

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\Tickets\Model\Ticket;
use FacturaScripts\Plugins\Tickets\Model\TicketPrinter;

class Gift extends Normal
{
    /**
     * @param SalesDocument $doc
     * @param TicketPrinter $printer
     * @param User $user
     * @return bool
     */
    public static function print($doc, TicketPrinter $printer, User $user): bool
    {
        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $ticket->title = ToolBox::i18n()->trans($doc->modelClassName() . '-min') . ' ' . $doc->codigo;

        $company = $doc->getCompany();
        $ticket->body = "\x1B" . "!" . "\x38" . $company->nombre . "\n" . "\x1B" . "!" . "\x00"
            . $company->direccion . "\nCP: " . $company->codpostal . ', ' . $company->ciudad . "\n"
            . $company->tipoidfiscal . ': ' . $company->cifnif . "\n\n"
            . $ticket->title . "\n"
            . ToolBox::i18n()->trans('date') . ': ' . $doc->fecha . ' ' . $doc->hora . "\n\n";

        $width = $printer->linelen - 6;
        $ticket->body .= sprintf("%5s", ToolBox::i18n()->trans('quantity-abb')) . " "
            . sprintf("%-" . $width . "s", ToolBox::i18n()->trans('description')) . "\n";
        $ticket->body .= $printer->getDashLine() . "\n";
        $lines = $doc->getLines();
        foreach ($lines as $line) {
            $description = mb_substr($line->descripcion, 0, $width);
            $ticket->body .= sprintf("%5s", $line->cantidad) . " "
                . sprintf("%-" . $width . "s", $description) . "\n";
        }

        $ticket->body .= $printer->getDashLine() . "\n\n\n\n\n\n\n"
            . $printer->getCommandStr('open') . "\n"
            . $printer->getCommandStr('cut') . "\n";
        return $ticket->save();
    }
}
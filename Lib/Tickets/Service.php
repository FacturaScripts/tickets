<?php

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\Servicios\Model\ServicioAT;
use FacturaScripts\Plugins\Tickets\Model\Ticket;
use FacturaScripts\Plugins\Tickets\Model\TicketPrinter;

class Service extends Normal
{
    /**
     * @param ServicioAT $doc
     * @param TicketPrinter $printer
     * @param User $user
     * @return bool
     */
    public static function print($doc, TicketPrinter $printer, User $user): bool
    {
        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $ticket->title = ToolBox::i18n()->trans('service') . ' ' . $doc->primaryColumnValue();

        $company = $doc->getCompany();
        $ticket->body = "\x1B" . "!" . "\x38" . $company->nombre . "\n" . "\x1B" . "!" . "\x00"
            . $company->direccion . "\nCP: " . $company->codpostal . ', ' . $company->ciudad . "\n"
            . $company->tipoidfiscal . ': ' . $company->cifnif . "\n\n";

        $customer = $doc->getSubject();
        $ticket->body .= $ticket->title . "\n"
            . ToolBox::i18n()->trans('date') . ': ' . $doc->fecha . ' ' . $doc->hora . "\n"
            . ToolBox::i18n()->trans('customer') . ': ' . $customer->razonsocial . "\n";
        if ($customer->telefono1) {
            $ticket->body .= ToolBox::i18n()->trans('phone') . ': ' . $customer->telefono1 . "\n";
        }
        if ($customer->telefono2) {
            $ticket->body .= ToolBox::i18n()->trans('phone') . ': ' . $customer->telefono2 . "\n";
        }

        $ticket->body .= "\n" . ToolBox::i18n()->trans('description') . ': ' . $doc->descripcion . "\n";
        if ($doc->material) {
            $ticket->body .= "\n" . ToolBox::i18n()->trans('material') . ': ' . $doc->material . "\n";
        }

        $ticket->body .= $printer->getDashLine() . "\n\n\n\n\n\n\n"
            . $printer->getCommandStr('open') . "\n"
            . $printer->getCommandStr('cut') . "\n";
        return $ticket->save();
    }
}
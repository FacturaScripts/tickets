<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\Base\SalesDocumentLine;
use FacturaScripts\Dinamic\Model\Impuesto;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Plugins\Tickets\Model\Ticket;
use FacturaScripts\Plugins\Tickets\Model\TicketPrinter;

/**
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Normal
{
    /**
     * @param SalesDocument $doc
     * @param TicketPrinter $printer
     * @param User $user
     * @param Agente $agent
     * @return bool
     */
    public static function print($doc, TicketPrinter $printer, User $user, Agente $agent = null): bool
    {
        $i18n = ToolBox::i18n();

        $ticket = new Ticket();
        $ticket->idprinter = $printer->id;
        $ticket->nick = $user->nick;
        $ticket->title = $i18n->trans($doc->modelClassName() . '-min') . ' ' . $doc->codigo;

        if ($agent) {
            $ticket->codagente = $agent->codagente;
        }

        $company = $doc->getCompany();
        $ticket->body = "\x1B" . "!" . "\x38" . $company->nombre . "\n" . "\x1B" . "!" . "\x00"
            . $company->direccion . "\nCP: " . $company->codpostal . ', ' . $company->ciudad . "\n"
            . $company->tipoidfiscal . ': ' . $company->cifnif . "\n\n"
            . $ticket->title . "\n"
            . $i18n->trans('date') . ': ' . $doc->fecha . ' ' . $doc->hora . "\n\n";

        $width = $printer->linelen - 17;
        $ticket->body .= sprintf("%5s", $i18n->trans('quantity-abb')) . " "
            . sprintf("%-" . $width . "s", $i18n->trans('description')) . " "
            . sprintf("%11s", $i18n->trans('total')) . "\n";
        $ticket->body .= $printer->getDashLine() . "\n";
        $lines = $doc->getLines();
        foreach ($lines as $line) {
            $description = mb_substr($line->descripcion, 0, $width);
            $total = $line->pvptotal * (100 + $line->iva + $line->recargo) / 100;
            $ticket->body .= sprintf("%5s", $line->cantidad) . " "
                . sprintf("%-" . $width . "s", $description) . " "
                . sprintf("%10s", ToolBox::numbers()::format($total)) . "\n";
        }
        $ticket->body .= $printer->getDashLine() . "\n";
        $ticket->body .= sprintf("%" . ($printer->linelen - 11) . "s", $i18n->trans('total')) . " "
            . sprintf("%10s", ToolBox::numbers()::format($doc->total)) . "\n";

        foreach (self::getSubtotals($doc, $lines) as $item) {
            $ticket->body .= sprintf("%" . ($printer->linelen - 11) . "s", $i18n->trans('tax-base') . ' ' . $item['taxp']) . " "
                . sprintf("%10s", ToolBox::numbers()::format($item['taxbase'])) . "\n"
                . sprintf("%" . ($printer->linelen - 11) . "s", $item['tax']) . " "
                . sprintf("%10s", ToolBox::numbers()::format($item['taxamount'])) . "\n";
        }

        $ticket->body .= "\n\n\n\n\n\n" . $printer->getCommandStr('open') . "\n"
            . $printer->getCommandStr('cut') . "\n";
        return $ticket->save();
    }

    /**
     * @param SalesDocument $doc
     * @param SalesDocumentLine $lines
     *
     * @return array
     */
    protected static function getSubtotals($doc, $lines): array
    {
        $subtotals = [];
        $eud = $doc->getEUDiscount();

        foreach ($lines as $line) {
            if (!isset($subtotals[$line->codimpuesto])) {
                $subtotals[$line->codimpuesto] = [
                    'tax' => $line->codimpuesto,
                    'taxp' => $line->iva . '%',
                    'taxbase' => 0,
                    'taxamount' => 0,
                    'taxsurcharge' => 0
                ];

                $impuesto = new Impuesto();
                if ($line->codimpuesto && $impuesto->loadFromCode($line->codimpuesto)) {
                    $subtotals[$line->codimpuesto]['tax'] = $impuesto->descripcion;
                }
            }


            $subtotals[$line->codimpuesto]['taxbase'] += $line->pvptotal * $eud;
            $subtotals[$line->codimpuesto]['taxamount'] += $line->pvptotal * $eud * $line->iva / 100;
            $subtotals[$line->codimpuesto]['taxsurcharge'] += $line->pvptotal * $eud * $line->recargo / 100;
        }

        return $subtotals;
    }
}
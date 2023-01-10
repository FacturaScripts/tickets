<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\Base\SalesDocumentLine;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Base\ModelCore;
use FacturaScripts\Dinamic\Model\Impuesto;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\Tickets\Model\Ticket;
use FacturaScripts\Plugins\Tickets\Model\TicketPrinter;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class Normal
{
    /**
     * @param SalesDocument $doc
     * @param TicketPrinter $printer
     * @param User $user
     * @param Agente|null $agent
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
        $ticket->body = static::getBigText($company->nombre, $printer->linelen)
            . $company->direccion . "\nCP: " . $company->codpostal . ', ' . $company->ciudad . "\n"
            . $company->tipoidfiscal . ': ' . $company->cifnif . "\n\n"
            . $ticket->title . "\n"
            . $i18n->trans('date') . ': ' . $doc->fecha . ' ' . $doc->hora . "\n"
            . $i18n->trans('customer') . ': ' . $doc->nombrecliente . "\n\n";

        if ($printer->head) {
            $ticket->body .= $printer->head . "\n\n";
        }

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
        if (property_exists($doc, 'tpv_efectivo') && $doc->tpv_efectivo > 0) {
            $ticket->body .= sprintf("%" . ($printer->linelen - 11) . "s", $i18n->trans('cash')) . " "
                . sprintf("%10s", ToolBox::numbers()::format($doc->tpv_efectivo)) . "\n";
        }
        if (property_exists($doc, 'tpv_cambio') && $doc->tpv_cambio > 0) {
            $ticket->body .= sprintf("%" . ($printer->linelen - 11) . "s", $i18n->trans('money-change')) . " "
                . sprintf("%10s", ToolBox::numbers()::format($doc->tpv_cambio)) . "\n";
        }

        foreach (self::getSubtotals($doc, $lines) as $item) {
            $ticket->body .= sprintf("%" . ($printer->linelen - 11) . "s", $i18n->trans('tax-base') . ' ' . $item['taxp']) . " "
                . sprintf("%10s", ToolBox::numbers()::format($item['taxbase'])) . "\n"
                . sprintf("%" . ($printer->linelen - 11) . "s", $item['tax']) . " "
                . sprintf("%10s", ToolBox::numbers()::format($item['taxamount'])) . "\n";
        }

        if ($printer->print_invoice_receipts && $doc->modelClassName() === 'FacturaCliente') {
            $ticket->body .= self::getReceipts($doc, $printer, $i18n);
        }

        if ($printer->footer) {
            $ticket->body .= "\n" . $printer->footer;
        }

        $ticket->body .= "\n\n\n\n\n\n"
            . $printer->getCommandStr('open') . "\n"
            . $printer->getCommandStr('cut') . "\n";
        return $ticket->save();
    }

    protected static function getBigText(string $text, int $lineLength): string
    {
        $bigLine = '';
        $bigLineLength = 0;
        $bigLineMax = intval($lineLength / 2);
        $words = explode(' ', $text);
        foreach ($words as $word) {
            if ($bigLineLength === 0) {
                $bigLine .= $word;
                $bigLineLength += strlen($word);
                continue;
            }

            $bigLineLength += strlen($word) + 1;
            if ($bigLineLength <= $bigLineMax) {
                $bigLine .= ' ' . $word;
                continue;
            }

            $bigLine .= "\n" . $word;
            $bigLineLength = strlen($word);
        }

        return "\x1B" . "!" . "\x38" . $bigLine . "\n" . "\x1B" . "!" . "\x00";
    }

    protected static function getReceipts($doc, $printer, $i18n): string
    {
        $paid = 0;
        $total = 0;
        $receipts = '';
        $widthTotal = $printer->linelen - 22;

        foreach ($doc->getReceipts() as $receipt) {
            if (false === empty($receipts)) {
                $receipts .= "\n";
            }

            $total += $receipt->importe;

            if (empty($receipt->fechapago)) {
                $datePaid = '';
            } else {
                $paid += $receipt->importe;
                $datePaid = date(ModelCore::DATE_STYLE, strtotime($receipt->fechapago));
            }

            $receipts .= sprintf("%10s", date(ModelCore::DATE_STYLE, strtotime($receipt->vencimiento))) . " "
                . sprintf("%10s", $datePaid) . " "
                . sprintf("%" . $widthTotal . "s", ToolBox::numbers()::format($receipt->importe));
        }

        if (empty($receipts)) {
            return '';
        }

        return "\n\n"
            . sprintf("%" . $printer->linelen . "s", $i18n->trans('receipts')) . "\n"
            . sprintf("%10s", $i18n->trans('expiration-abb')) . " "
            . sprintf("%10s", $i18n->trans('paid')) . " "
            . sprintf("%" . $widthTotal . "s", $i18n->trans('total')) . "\n"
            . $printer->getDashLine() . "\n"
            . $receipts . "\n"
            . $printer->getDashLine() . "\n"
            . sprintf("%" . ($printer->linelen - $widthTotal - 1) . "s", $i18n->trans('total')) . " "
            . sprintf("%" . $widthTotal . "s", ToolBox::numbers()::format($total)) . "\n"
            . sprintf("%" . ($printer->linelen - $widthTotal - 1) . "s", $i18n->trans('paid')) . " "
            . sprintf("%" . $widthTotal . "s", ToolBox::numbers()::format($paid)) . "\n"
            . sprintf("%" . ($printer->linelen - $widthTotal - 1) . "s", $i18n->trans('pending')) . " "
            . sprintf("%" . $widthTotal . "s", ToolBox::numbers()::format($total - $paid)) . "\n\n";
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
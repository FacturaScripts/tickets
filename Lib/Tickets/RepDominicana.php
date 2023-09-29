<?php
/**
 * Copyright (C) 2023 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Lib\Tickets;

use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Ticket;
use FacturaScripts\Dinamic\Model\TicketPrinter;
use FacturaScripts\Dinamic\Model\User;
use Mike42\Escpos\Printer;

/**
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class RepDominicana extends BaseTicket
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

    protected static function setHeader(ModelClass $model, TicketPrinter $printer, string $title): void
    {
        if ($printer->print_stored_logo) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            // imprimimos el logotipo almacenado en la impresora
            static::$connector->write("\x1Cp\x01\x00\x00");
            static::$escpos->feed();
        }

        // obtenemos los datos de la empresa
        $company = $model->getCompany();

        // establecemos el tamaño de la fuente
        static::$escpos->setTextSize($printer->title_font_size, $printer->title_font_size);

        // imprimimos el nombre corto de la empresa
        if ($printer->print_comp_shortname) {
            static::$escpos->text(static::sanitize($company->nombrecorto) . "\n");
            static::$escpos->setTextSize($printer->head_font_size, $printer->head_font_size);

            // imprimimos el nombre de la empresa
            static::$escpos->text(static::sanitize($company->nombre) . "\n");
        } else {
            // imprimimos el nombre de la empresa
            static::$escpos->text(static::sanitize($company->nombre) . "\n");
            static::$escpos->setTextSize($printer->head_font_size, $printer->head_font_size);
        }

        static::$escpos->setJustification();

        // imprimimos la dirección de la empresa
        static::$escpos->text(static::sanitize($company->direccion) . "\n");
        static::$escpos->text(static::sanitize("CP: " . $company->codpostal . ', ' . $company->ciudad) . "\n");
        static::$escpos->text(static::sanitize($company->tipoidfiscal . ': ' . $company->cifnif) . "\n\n");

        if ($printer->print_comp_tlf) {
            if (false === empty($company->telefono1) && false === empty($company->telefono2)) {
                static::$escpos->text(static::sanitize($company->telefono1 . ' / ' . $company->telefono2) . "\n");
            } elseif (false === empty($company->telefono1)) {
                static::$escpos->text(static::sanitize($company->telefono1) . "\n");
            } elseif (false === empty($company->telefono2)) {
                static::$escpos->text(static::sanitize($company->telefono2) . "\n");
            }
        }

        // imprimimos el título del documento
        static::$escpos->text(static::sanitize($title) . "\n");

        static::setHeaderTPV($model, $printer);

        // si es un documento de venta
        // imprimimos la fecha y el cliente
        if (in_array($model->modelClassName(), ['PresupuestoCliente', 'PedidoCliente', 'AlbaranCliente', 'FacturaCliente'])) {
            static::$escpos->text(static::sanitize(static::$i18n->trans('date') . ': ' . $model->fecha . ' ' . $model->hora) . "\n");
            static::$escpos->text(static::sanitize(static::$i18n->trans('customer') . ': ' . $model->nombrecliente) . "\n\n");

            if ($model->modelClassName() == 'FacturaCliente') {
                if (property_exists($model, 'numeroncf') && $model->numeroncf) {
                    static::$escpos->text(static::sanitize(static::$i18n->trans('ncf-number') . ': ' . $model->numeroncf)) . "\n";
                }
                if (property_exists($model, 'tipocomprobante') && $model->tipocomprobante) {
                    static::$escpos->text(static::sanitize(static::$i18n->trans('tipo_comprobante') . ': ' . static::getTipoComprobanteRD($model->tipocomprobante))) . "\n";
                }
                if (property_exists($model, 'ncffechavencimiento') && $model->ncffechavencimiento) {
                    static::$escpos->text(static::sanitize(static::$i18n->trans('due-date') . ': ' . $model->ncffechavencimiento)) . "\n";
                }
            }
        }

        // añadimos la cabecera
        if ($printer->head) {
            static::$escpos->setJustification(Printer::JUSTIFY_CENTER);
            static::$escpos->text(static::sanitize($printer->head) . "\n\n");
            static::$escpos->setJustification();
        }
    }

    protected static function getTipoComprobanteRD(string $numero): string
    {
        switch ($numero) {
            case '01':
                return static::$i18n->trans('desc-ncf-type-01');

            case '02':
                return static::$i18n->trans('desc-ncf-type-02');

            case '03':
                return static::$i18n->trans('desc-ncf-type-03');

            case '04':
                return static::$i18n->trans('desc-ncf-type-04');

            case '11':
                return static::$i18n->trans('desc-ncf-type-11');

            case '12':
                return static::$i18n->trans('desc-ncf-type-12');

            case '13':
                return static::$i18n->trans('desc-ncf-type-13');

            case '14':
                return static::$i18n->trans('desc-ncf-type-14');

            case '15':
                return static::$i18n->trans('desc-ncf-type-15');

            case '16':
                return static::$i18n->trans('desc-ncf-type-16');

            case '17':
                return static::$i18n->trans('desc-ncf-type-17');

            default:
                return '';
        }
    }
}
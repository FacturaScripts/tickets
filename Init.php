<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets;

require_once __DIR__ . '/vendor/autoload.php';

use FacturaScripts\Core\Html;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Controller\SendTicket;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Lib\Tickets\Gift;
use FacturaScripts\Dinamic\Lib\Tickets\Normal;
use FacturaScripts\Dinamic\Lib\Tickets\PaymentReceipt;
use Twig\TwigFunction;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
final class Init extends InitClass
{
    public function init(): void
    {
        ExportManager::addOption('Ticket', 'ticket', 'fa-solid fa-receipt');
        $this->loadFormatTickets();
        $this->loadTwigFunctions();
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        // activamos la API
        $this->setAPI();
    }

    private function loadFormatTickets(): void
    {
        $models = ['PresupuestoCliente', 'PedidoCliente', 'AlbaranCliente', 'FacturaCliente'];
        foreach ($models as $model) {
            SendTicket::addFormat(Normal::class, $model, 'normal');
            SendTicket::addFormat(Gift::class, $model, 'gift');
        }

        SendTicket::addFormat(PaymentReceipt::class, 'ReciboCliente', 'receipt');
    }

    private function loadTwigFunctions(): void
    {
        Html::addFunction(new TwigFunction('mc20printerWs', function () {
            $canal = md5(Tools::siteUrl());
            return 'https://ai.factura.city/mc20printer/' . $canal . '/print';
        }));
    }

    private function setAPI(): void
    {
        // si hay clave de API en el config, no hacemos nada
        if (defined('FS_API_KEY')) {
            return;
        }

        // activamos la API
        Tools::settingsSet('default', 'enable_api', true);
        Tools::settingsSave();
    }
}

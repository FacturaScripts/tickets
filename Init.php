<?php
/**
 * Copyright (C) 2019-2026 Carlos García Gómez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets;

require_once __DIR__ . '/vendor/autoload.php';

use FacturaScripts\Core\Html;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Lib\Mc20Printer;
use FacturaScripts\Dinamic\Lib\Tickets\Gift;
use FacturaScripts\Dinamic\Lib\Tickets\Normal;
use FacturaScripts\Dinamic\Lib\Tickets\PaymentReceipt;
use Twig\TwigFunction;

/**
 * @author Carlos García Gómez <carlos@facturascripts.com>
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
        $sendTicketClass = '\\FacturaScripts\\Dinamic\\Controller\\SendTicket';
        if (false === class_exists($sendTicketClass)) {
            return;
        }

        $models = ['PresupuestoCliente', 'PedidoCliente', 'AlbaranCliente', 'FacturaCliente'];
        foreach ($models as $model) {
            $sendTicketClass::addFormat(Normal::class, $model, 'normal');
            $sendTicketClass::addFormat(Gift::class, $model, 'gift');
        }

        $sendTicketClass::addFormat(PaymentReceipt::class, 'ReciboCliente', 'receipt');
    }

    private function loadTwigFunctions(): void
    {
        Html::addFunction(new TwigFunction('mc20printerWs', function () {
            return Mc20Printer::printUrl();
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

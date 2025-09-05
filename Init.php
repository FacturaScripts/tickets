<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets;

require_once __DIR__ . '/vendor/autoload.php';

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Controller\SendTicket;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Lib\Tickets\Gift;
use FacturaScripts\Dinamic\Lib\Tickets\Normal;
use FacturaScripts\Dinamic\Lib\Tickets\PaymentReceipt;
use FacturaScripts\Dinamic\Lib\AssetManager;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
final class Init extends InitClass
{
    public function init(): void
    {
        ExportManager::addOption('Ticket', 'ticket', 'fa-solid fa-receipt');
        $this->loadFormatTickets();

        // assets
        AssetManager::addJs(FS_ROUTE . '/Plugins/Tickets/node_modules/qz-tray/qz-tray.js');
    }

    public function uninstall(): void
    {
    }

    public function update(): void
    {
        // activamos la API
        $this->setAPI();

        // renombramos la tabla de tickets de antiguas versiones
        $this->renameTicketsTable('tickets', 'tickets_docs');
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

    private function renameTicketsTable(string $oldTable, string $newTable): void
    {
        $dataBase = new DataBase();
        if (false === $dataBase->tableExists($oldTable)) {
            return;
        }

        // comprobamos las columnas de la tabla antigua
        $columns = $dataBase->getColumns($oldTable);
        if (isset($columns['id']) && isset($columns['idprinter'])) {
            $dataBase->exec("RENAME TABLE " . $oldTable . " TO " . $newTable . ";");
        }
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

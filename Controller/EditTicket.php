<?php
/**
 * Copyright (C) 2019-2026 Carlos García Gómez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;

/**
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class EditTicket extends EditController
{
    public function getModelClassName(): string
    {
        return 'Ticket';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'Ticket';
        $data['icon'] = 'fa-solid fa-search';
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();

        // desactivamos los botones de nuevo, opciones e imprimir
        $this->tab($this->getMainViewName())
            ->setSettings('btnNew', false)
            ->setSettings('btnOptions', false)
            ->setSettings('btnPrint', false);
    }
}

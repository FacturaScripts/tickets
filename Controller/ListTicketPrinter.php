<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class ListTicketPrinter extends ListController
{
    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'tickets';
        $pageData['menu'] = 'admin';
        $pageData['icon'] = 'fa-solid fa-print';
        return $pageData;
    }

    protected function createViews()
    {
        $this->createViewsTicketPrinter();
        $this->createViewsTicket();
    }

    protected function createViewsTicket(string $viewName = 'ListTicket'): void
    {
        $this->addView($viewName, 'Ticket', 'tickets', 'fa-solid fa-receipt')
            ->addOrderBy(['creationdate'], 'creation-date', 2)
            ->addOrderBy(['id'], 'id')
            ->addOrderBy(['title'], 'title')
            ->addSearchFields(['title'])
            ->setSettings('btnNew', false);
    }

    protected function createViewsTicketPrinter(string $viewName = 'ListTicketPrinter'): void
    {
        $this->addView($viewName, 'TicketPrinter', 'printers', 'fa-solid fa-print')
            ->addOrderBy(['creationdate'], 'creation-date')
            ->addOrderBy(['id'], 'id')
            ->addOrderBy(['name'], 'name', 1)
            ->addSearchFields(['id', 'name']);
    }
}

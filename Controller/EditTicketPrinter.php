<?php
/**
 * Copyright (C) 2019-2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\Tickets\Controller;

use FacturaScripts\Core\Lib\ExtendedController\BaseView;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;

/**
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditTicketPrinter extends EditController
{
    public function getAppKey(): string
    {
        return $this->getModel()->apikey;
    }

    public function getAppUrl(): string
    {
        return Tools::siteUrl();
    }

    public function getModelClassName(): string
    {
        return 'TicketPrinter';
    }

    public function getPageData(): array
    {
        $pageData = parent::getPageData();
        $pageData['menu'] = 'admin';
        $pageData['title'] = 'printer';
        $pageData['icon'] = 'fa-solid fa-print';
        return $pageData;
    }

    protected function createViews()
    {
        parent::createViews();

        // desactivamos el botón de opciones
        $this->setSettings($this->getMainViewName(), 'btnOptions', false);

        $this->setTabsPosition('bottom');
        $this->createViewsDownloadApp();
        $this->createViewsTicket();
    }

    protected function createViewsDownloadApp(string $viewName = 'app'): void
    {
        $this->addHtmlView($viewName, 'Tab/DownloadPrinterApp', 'TicketPrinter', 'app', 'fa-solid fa-desktop');
    }

    protected function createViewsTicket(string $viewName = 'ListTicket'): void
    {
        $this->addListView($viewName, 'Ticket', 'tickets', 'fa-solid fa-receipt')
            ->addOrderBy(['id'], 'id')
            ->addOrderBy(['title'], 'title')
            ->addOrderBy(['creationdate'], 'date', 2)
            ->addSearchFields(['title'])
            ->disableColumn('printer')
            ->setSettings('btnNew', false);
    }

    /**
     * Load view data.
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        $mvn = $this->getMainViewName();
        switch ($viewName) {
            case $mvn:
                parent::loadData($viewName, $view);
                if (false === $view->model->exists()) {
                    $view->model->nick = $this->user->nick;
                }
                break;

            case 'ListTicket':
                $id = $this->views[$mvn]->model->id();
                $where = [Where::column('idprinter', $id)];
                $view->loadData('', $where);
                break;
        }
    }
}

<?php
namespace FacturaScripts\Plugins\Tickets\Controller;

class EditTicket extends \FacturaScripts\Core\Lib\ExtendedController\EditController
{
    /**
     * @return string
     */
    public function getModelClassName() {
        return "Ticket";
    }

    /**
     * @return array
     */
    public function getPageData() {
        $pageData = parent::getPageData();
        $pageData["title"] = "Ticket";
        $pageData["icon"] = "fas fa-search";
        return $pageData;
    }

    /**
     * @param string $viewName
     * @param \FacturaScripts\Core\Lib\ExtendedController\BaseView $view
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
        }
    }
}
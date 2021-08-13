<?php

namespace FacturaScripts\Plugins\Tickets\Model;

class Ticket extends \FacturaScripts\Core\Model\Base\ModelClass
{
    use \FacturaScripts\Core\Model\Base\ModelTrait;

    /**
     * @var string
     */
    public $body;

    /**
     * @var string
     */
    public $creationdate;

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $idprinter;

    /**
     * @var string
     */
    public $nick;

    /**
     * @var bool
     */
    public $printed;

    /**
     * @var string
     */
    public $title;

    public function clear()
    {
        parent::clear();
        $this->creationdate = \date(self::DATETIME_STYLE);
        $this->printed = false;
    }

    /**
     * @return TicketPrinter
     */
    public function getPrinter(): TicketPrinter
    {
        $printer = new TicketPrinter();
        $printer->loadFromCode($this->idprinter);
        return $printer;
    }

    /**
     * @return string
     */
    public static function primaryColumn()
    {
        return "id";
    }

    /**
     * @return bool
     */
    public function save()
    {
        $this->body = $this->sanitize($this->body);
        $this->title = $this->toolBox()->utils()->noHtml($this->title);

        return parent::save();
    }

    /**
     * @return string
     */
    public static function tableName()
    {
        return "tickets";
    }

    /**
     * @param string $type
     * @param string $list
     *
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'List')
    {
        return $type === 'list' ? $this->getPrinter()->url() : parent::url($type, $list);
    }

    /**
     * @param string $txt
     *
     * @return string
     */
    protected function sanitize(string $txt): string
    {
        $changes = ['/à/' => 'a', '/á/' => 'a', '/â/' => 'a', '/ã/' => 'a', '/ä/' => 'a',
            '/å/' => 'a', '/æ/' => 'ae', '/ç/' => 'c', '/è/' => 'e', '/é/' => 'e', '/ê/' => 'e',
            '/ë/' => 'e', '/ì/' => 'i', '/í/' => 'i', '/î/' => 'i', '/ï/' => 'i', '/ð/' => 'd',
            '/ñ/' => 'n', '/ò/' => 'o', '/ó/' => 'o', '/ô/' => 'o', '/õ/' => 'o', '/ö/' => 'o',
            '/ő/' => 'o', '/ø/' => 'o', '/ù/' => 'u', '/ú/' => 'u', '/û/' => 'u', '/ü/' => 'u',
            '/ű/' => 'u', '/ý/' => 'y', '/þ/' => 'th', '/ÿ/' => 'y',
            '/&quot;/' => '-', '/´/' => '/\'/', '/€/' => 'EUR',
            '/À/' => 'A', '/Á/' => 'A', '/Â/' => 'A', '/Ä/' => 'A',
            '/Ç/' => 'C', '/È/' => 'E', '/É/' => 'E', '/Ê/' => 'E',
            '/Ë/' => 'E', '/Ì/' => 'I', '/Í/' => 'I', '/Î/' => 'I', '/Ï/' => 'I',
            '/Ñ/' => 'N', '/Ò/' => 'O', '/Ó/' => 'O', '/Ô/' => 'O', '/Ö/' => 'O',
            '/Ù/' => 'U', '/Ú/' => 'U', '/Û/' => 'U', '/Ü/' => 'U',
            '/Ý/' => 'Y', '/Ÿ/' => 'Y'
        ];

        $txtNoHtml = $this->toolBox()->utils()->noHtml($txt);
        return \preg_replace(\array_keys($changes), $changes, $txtNoHtml);
    }
}
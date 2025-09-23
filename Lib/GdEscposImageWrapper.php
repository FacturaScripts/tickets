<?php
namespace FacturaScripts\Plugins\Tickets\Lib;

use Mike42\Escpos\GdEscposImage;
use Mike42\Escpos\EscposImage;

/**
 * Esta clase tiene el simple propósito de actuar como wrapper y modificar el método por defecto
 * para que se acepte tanto un recurso GD (PHP 7) como un objeto GdImage (PHP 8).
 * 
 * (Este es un bug causado por la propia librería escpos-php al no actualizarse para adaptarse a las nuevas 
 * versiones de gd y malinterpretar los objetos de gd como si no fueran objetos gd.)
 */
class GdEscposImageWrapper extends GdEscposImage
{
    /**
     * Acepta tanto resource GD (PHP 7) como objeto GdImage (PHP 8).
     *
     * @param mixed $im Recurso/objeto GD válido
     * @throws \Exception Si no es un recurso/objeto GD o si falta la extensión
     */
    public function readImageFromGdResource($im)
    {
        // Inicio lineas modificadas
        $isGd = is_resource($im) || (class_exists('GdImage') && $im instanceof \GdImage);
        if (!$isGd) {
            // Fin lineas modificadas
            throw new \Exception("Failed to load image: expected GD resource or GdImage.");
        } elseif (!EscposImage::isGdLoaded()) {
            throw new \Exception(__FUNCTION__ . " requires 'gd' extension.");
        }

        /* Make a string of 1's and 0's */
        $imgHeight = imagesy($im);
        $imgWidth  = imagesx($im);
        $imgData   = str_repeat("\0", $imgHeight * $imgWidth);

        for ($y = 0; $y < $imgHeight; $y++) {
            for ($x = 0; $x < $imgWidth; $x++) {
                /* Faster to average channels, blend alpha and negate the image here than via filters (tested!) */
                $cols = imagecolorsforindex($im, imagecolorat($im, $x, $y));
                // 1 for white, 0 for black, ignoring transparency
                $greyness = (int)(($cols['red'] + $cols['green'] + $cols['blue']) / 3) >> 7;
                // 1 for black, 0 for white, taking into account transparency
                $black = (1 - $greyness) >> ($cols['alpha'] >> 6);
                $imgData[$y * $imgWidth + $x] = $black;
            }
        }

        $this->setImgWidth($imgWidth);
        $this->setImgHeight($imgHeight);
        $this->setImgData($imgData);
    }
}


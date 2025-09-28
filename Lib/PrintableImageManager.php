<?php
/**
 * Clase encargada de obtener una imágen y crear una copia apta para imprimir.
 * 
 * Generalmente lo que realiza es bajar el tamaño hasta que ocupe 384x384 (tamaño generalmente correcto para impresión)
 * 
 * Si la imagen tiene un aspect ratio diferente se hace más pequeña hasta caber en 384x384 sin cambiar el aspect ratio propio de la imágen.
 * 
 * También se aplica un filtro leve de cambio de color
 * 
 * Y después cambia a monocolor o 1 bit de color (blanco y negro)
 * 
 * @author Abderrahim Darghal Belkacemi
*/

namespace FacturaScripts\Plugins\Tickets\Lib;

use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\Tickets\Lib\GdEscposImageWrapper;
use Mike42\Escpos\GdEscposImage;

class PrintableImageManager
{
    // Tamaño de la imagen que se imprime
    const MAX_WIDTH = 384;
    const MAX_HEIGHT = 384;

    // gerarquía de carpetas
    const PUBLIC_FOLDER = 'MyFiles';
    const PUBLIC_SUBFOLDER = 'Public';
    const PUBLIC_NAMESPACE = 'Tickets';

    // Nombre del fichero de la imagen procesada
    const PUBLIC_LOGO_FILENAME = 'printable-logo.png';

    /**
     * Crea una copia de la imagen pasada por argumento con el formato adecuado para la impresión
     * y la guarda en la ruta pública estática del plugin (sobrescribe si ya existe)
     * 
     * Devuelve falso si el formato de imagen no es soportado o GdEscposImage si existe la imagen
     * 
     * @param string $imagePath Ruta de la imagen
     * @return GdEscposImage | bool
     */
    static public function createPrintableImage($imagePath): GdEscposImage | bool
    {
        // Cargar imagen con GD y redimensionar a 384x384 manteniendo aspect ratio (letterboxing)
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        $src = null;
        switch ($ext) {
            case 'png':
                $src = @imagecreatefrompng($imagePath);
                break;
            case 'jpg':
            case 'jpeg':
                $src = @imagecreatefromjpeg($imagePath);
                break;
            case 'gif':
                $src = @imagecreatefromgif($imagePath);
                break;
            case 'bmp':
                if (function_exists('imagecreatefrombmp')) {
                    $src = @imagecreatefrombmp($imagePath);
                }
                break;
        }

        if (!$src) {
            return false;
        }

        $dst = imagecreatetruecolor(self::MAX_WIDTH, self::MAX_HEIGHT);

        // Mantener canal alfa si existe, rellenando fondo blanco para coherencia con térmica
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $white = imagecolorallocatealpha($dst, 255, 255, 255, 0);
        imagefilledrectangle($dst, 0, 0, self::MAX_WIDTH, self::MAX_HEIGHT, $white);

        // Calcular escalado manteniendo proporción, sin ampliar si es más pequeño
        $srcW = imagesx($src);
        $srcH = imagesy($src);
        $scale = min(self::MAX_WIDTH / $srcW, self::MAX_HEIGHT / $srcH, 1.0);
        $newW = (int) floor($srcW * $scale);
        $newH = (int) floor($srcH * $scale);
        $dstX = (int) floor((self::MAX_WIDTH - $newW) / 2);
        $dstY = (int) floor((self::MAX_HEIGHT - $newH) / 2);

        imagecopyresampled(
            $dst,
            $src,
            $dstX,
            $dstY,
            0,
            0,
            $newW,
            $newH,
            $srcW,
            $srcH
        );
        imagedestroy($src);

        // Preparar para impresión térmica: escala de grises + más contraste y leve oscurecido
        if (function_exists('imagefilter')) {
            @imagefilter($dst, IMG_FILTER_GRAYSCALE);
            @imagefilter($dst, IMG_FILTER_CONTRAST, -20); // valores negativos aumentan contraste
            @imagefilter($dst, IMG_FILTER_BRIGHTNESS, -10); // oscurecer ligeramente para marcar mejor
        }
        // Opcional: un ligero "sharpen" para resaltar bordes
        if (function_exists('imageconvolution')) {
            $sharpen = [
                [-1, -1, -1],
                [-1, 16, -1],
                [-1, -1, -1],
            ];
            // Divisor 8 suaviza el efecto a un nivel moderado
            @imageconvolution($dst, $sharpen, 8, 0);
        }

        // Convertir a 1-bit (blanco/negro) con la misma lógica que usa escpos-php (GdEscposImage)
        $bw = imagecreate(self::MAX_WIDTH, self::MAX_HEIGHT); // imagen indexada (paleta) para limitar colores a 2
        $whiteIdx = imagecolorallocate($bw, 255, 255, 255);
        $blackIdx = imagecolorallocate($bw, 0, 0, 0);
        imagefilledrectangle($bw, 0, 0, self::MAX_WIDTH, self::MAX_HEIGHT, $whiteIdx);
        for ($y = 0; $y < self::MAX_HEIGHT; $y++) {
            for ($x = 0; $x < self::MAX_WIDTH; $x++) {
                $cols = imagecolorsforindex($dst, imagecolorat($dst, $x, $y));
                // Igual a GdEscposImage: promedio canales, umbral 128 y mezcla de alpha
                $greyness = (int)(($cols['red'] + $cols['green'] + $cols['blue']) / 3) >> 7; // 1 blanco, 0 negro (ignorando alpha)
                $blackBit = (1 - $greyness) >> ($cols['alpha'] >> 6); // 1 negro, 0 blanco (considerando alpha)
                imagesetpixel($bw, $x, $y, $blackBit ? $blackIdx : $whiteIdx);
            }
        }

        // Hacer el color blanco transparente para previsualización (mejor estética)
        // En imágenes indexadas funciona con imagecolortransparent y se preserva en PNG
        @imagecolortransparent($bw, $whiteIdx);

        // Guardar salida en carpeta pública (ruta estática y formato PNG)
        self::saveToPublic($bw);

        // Pasar recurso GD 1-bit al objeto EscposImage basado en GD
        $img = new GdEscposImageWrapper("", false);
        $img->readImageFromGdResource($bw);

        // Limpiar recursos GD, datos ya están en $img
        imagedestroy($dst);
        imagedestroy($bw);

        return $img;
    }

    /**
     * Guarda la imagen procesada en MyFiles/Public con el mismo nombre del fichero original.
     * Siempre sobreescribe si ya existe.
     *
     * @param resource|\GdImage $gdImageBW Recurso u objeto GD (1-bit) a guardar.
     */
    protected static function saveToPublic($gdImageBW): void
    {
        try {
            // aseguramos la carpeta pública (namespacing del plugin)
            $publicFolderAbs = Tools::folder(self::PUBLIC_FOLDER, self::PUBLIC_SUBFOLDER, self::PUBLIC_NAMESPACE);
            if (!Tools::folderCheckOrCreate($publicFolderAbs)) {
                Tools::log('tickets')->warning('No se pudo crear carpeta pública: ' . $publicFolderAbs);
                return;
            }

            $targetPath = Tools::folder($publicFolderAbs, self::PUBLIC_LOGO_FILENAME);

            // Guardamos siempre como PNG para impresión térmica
            $saved = @imagepng($gdImageBW, $targetPath, 6);

            if (!$saved) {
                Tools::log('tickets')->warning('No se pudo guardar imagen pública en: ' . $targetPath);
            }
        } catch (\Throwable $e) {
            Tools::log('tickets')->error('Excepción guardando imagen pública: ' . $e->getMessage());
        }
    }

    /**
     * Devuelve la ruta pública (URL relativa) del logo procesado en MyFiles/Public/Tickets.
     * Si no existe, devuelve cadena vacía.
     *
     * @return string URL relativa tipo 'MyFiles/Public/Tickets/printable-logo.png' o vacía.
     */
    public static function getPublicImageUrl(): string
    {
        $publicFolderAbs = Tools::folder(self::PUBLIC_FOLDER, self::PUBLIC_SUBFOLDER, self::PUBLIC_NAMESPACE);
        $publicFileAbs = Tools::folder($publicFolderAbs, self::PUBLIC_LOGO_FILENAME);
        if (is_file($publicFileAbs)) {
            return self::PUBLIC_FOLDER . '/' . self::PUBLIC_SUBFOLDER . '/' . self::PUBLIC_NAMESPACE . '/' . self::PUBLIC_LOGO_FILENAME;
        }
        return '';
    }
}

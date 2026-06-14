<?php

namespace App\Services;

/**
 * Generador de comandos ESC/POS para impresoras térmicas.
 *
 * Uso:
 *   $ticket = new TicketEscPos(48);  // 48 cols = papel 80 mm
 *   $ticket = new TicketEscPos(32);  // 32 cols = papel 58 mm
 *
 * El método get() devuelve la cadena binaria lista para enviar al puerto.
 */
class TicketEscPos
{
    // ── Comandos ESC/POS ──────────────────────────────────────────────────
    const INIT         = "\x1B\x40";          // Inicializar impresora
    const LF           = "\x0A";              // Salto de línea
    const ALIGN_LEFT   = "\x1B\x61\x00";
    const ALIGN_CENTER = "\x1B\x61\x01";
    const ALIGN_RIGHT  = "\x1B\x61\x02";
    const BOLD_ON      = "\x1B\x45\x01";
    const BOLD_OFF     = "\x1B\x45\x00";
    const DOUBLE_SIZE  = "\x1D\x21\x11";      // Doble alto + doble ancho
    const NORMAL_SIZE  = "\x1D\x21\x00";
    const FEED_3       = "\x1B\x64\x03";      // Avanzar 3 líneas
    const CUT_FULL     = "\x1D\x56\x00";      // Corte total
    const CUT_PARTIAL  = "\x1D\x56\x01";      // Corte parcial

    private int    $cols;
    private string $buf = '';

    public function __construct(int $cols = 48)
    {
        $this->cols = $cols;
        $this->buf  = self::INIT;
    }

    // ── Alineación y texto ────────────────────────────────────────────────

    public function center(string $text, bool $bold = false): self
    {
        $this->buf .= self::ALIGN_CENTER;
        if ($bold) $this->buf .= self::BOLD_ON;
        $this->buf .= $text . self::LF;
        if ($bold) $this->buf .= self::BOLD_OFF;
        return $this;
    }

    public function left(string $text, bool $bold = false): self
    {
        $this->buf .= self::ALIGN_LEFT;
        if ($bold) $this->buf .= self::BOLD_ON;
        $this->buf .= $text . self::LF;
        if ($bold) $this->buf .= self::BOLD_OFF;
        return $this;
    }

    public function bigCenter(string $text): self
    {
        $this->buf .= self::ALIGN_CENTER . self::DOUBLE_SIZE . $text . self::NORMAL_SIZE . self::LF;
        return $this;
    }

    /** Línea con dos columnas: texto izquierda y texto derecha. */
    public function row(string $left, string $right): self
    {
        $pad = max(1, $this->cols - mb_strlen($left) - mb_strlen($right));
        $this->buf .= self::ALIGN_LEFT . $left . str_repeat(' ', $pad) . $right . self::LF;
        return $this;
    }

    /**
     * Fila de 4 columnas para los renglones de detalle.
     *
     * @param string $name    Nombre del artículo (se trunca si es largo)
     * @param string $qty     Cantidad
     * @param string $price   Precio unitario
     * @param string $total   Importe
     */
    public function detailRow(string $name, string $qty, string $price, string $total): self
    {
        // Anchos por columna según papel
        if ($this->cols >= 42) {
            $wName  = $this->cols - 26; // resto para nombre
            [$wQty, $wPrice, $wTotal] = [6, 10, 10];
        } else {
            // 32 cols: nombre en segunda línea
            $this->left(mb_substr($name, 0, $this->cols));
            $line = str_pad($qty, 6, ' ', STR_PAD_LEFT)
                  . str_pad($price, 13, ' ', STR_PAD_LEFT)
                  . str_pad($total, 13, ' ', STR_PAD_LEFT);
            $this->buf .= self::ALIGN_LEFT . $line . self::LF;
            return $this;
        }

        $nameTrunc = mb_substr($name, 0, $wName);
        $line = str_pad($nameTrunc, $wName)
              . str_pad($qty,   $wQty,   ' ', STR_PAD_LEFT)
              . str_pad($price, $wPrice, ' ', STR_PAD_LEFT)
              . str_pad($total, $wTotal, ' ', STR_PAD_LEFT);

        $this->buf .= self::ALIGN_LEFT . $line . self::LF;
        return $this;
    }

    // ── Separadores ───────────────────────────────────────────────────────

    public function line(): self
    {
        $this->buf .= self::ALIGN_LEFT . str_repeat('-', $this->cols) . self::LF;
        return $this;
    }

    public function doubleLine(): self
    {
        $this->buf .= self::ALIGN_LEFT . str_repeat('=', $this->cols) . self::LF;
        return $this;
    }

    public function feed(int $lines = 1): self
    {
        $this->buf .= str_repeat(self::LF, $lines);
        return $this;
    }

    // ── Corte ─────────────────────────────────────────────────────────────

    public function cut(bool $partial = false): self
    {
        $this->buf .= self::FEED_3 . ($partial ? self::CUT_PARTIAL : self::CUT_FULL);
        return $this;
    }

    // ── Logo / imagen ─────────────────────────────────────────────────────

    /**
     * Imprime el logo del almacén (raster ESC/POS GS v 0) seguido de las
     * líneas de texto del encabezado.  Si GD no está disponible o la imagen
     * es inválida lanza una excepción para que el caller use el header de texto.
     *
     * @param string   $imageData  Contenido binario del archivo de imagen
     * @param string[] $textLines  Líneas de texto a imprimir debajo del logo
     */
    public function addLogoHeader(string $imageData, array $textLines): self
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('GD no disponible');
        }

        $src = @imagecreatefromstring($imageData);
        if ($src === false) {
            throw new \RuntimeException('Imagen inválida');
        }

        // Ancho máximo en puntos según ancho de papel
        $maxW  = $this->cols >= 42 ? 576 : 384;
        $maxH  = 200;
        $origW = imagesx($src);
        $origH = imagesy($src);
        $scale = min($maxW / $origW, $maxH / $origH, 1.0);
        $newW  = max(1, (int) round($origW * $scale));
        $newH  = max(1, (int) round($origH * $scale));

        $dst = imagecreatetruecolor($newW, $newH);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($src);

        $widthBytes = (int) ceil($newW / 8);

        // GS v 0 — raster bit image (modo normal)
        $this->buf .= self::ALIGN_CENTER;
        $this->buf .= "\x1D\x76\x30\x00";
        $this->buf .= chr($widthBytes & 0xFF) . chr(($widthBytes >> 8) & 0xFF);
        $this->buf .= chr($newH & 0xFF)       . chr(($newH >> 8) & 0xFF);

        for ($y = 0; $y < $newH; $y++) {
            for ($xb = 0; $xb < $widthBytes; $xb++) {
                $byte = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $x = $xb * 8 + $bit;
                    if ($x < $newW) {
                        $color = imagecolorat($dst, $x, $y);
                        $lum   = (int)(
                            0.299 * (($color >> 16) & 0xFF) +
                            0.587 * (($color >> 8)  & 0xFF) +
                            0.114 * ( $color        & 0xFF)
                        );
                        if ($lum < 128) {
                            $byte |= 0x80 >> $bit;
                        }
                    }
                }
                $this->buf .= chr($byte);
            }
        }

        imagedestroy($dst);
        $this->buf .= self::LF;

        foreach ($textLines as $line) {
            $this->center($line);
        }

        return $this;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public static function money(float $amount): string
    {
        return '$' . number_format($amount, 2);
    }

    public function get(): string
    {
        return $this->buf;
    }
}

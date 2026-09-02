<?php

namespace App\Support;

use Picqer\Barcode\BarcodeGeneratorSVG;

class Barcode
{
    public static function svg(string $code): string
    {
        $svg = (new BarcodeGeneratorSVG)->getBarcode(
            $code,
            BarcodeGeneratorSVG::TYPE_CODE_128,
            widthFactor: 1.6,
            height: 40,
        );

        return trim((string) preg_replace(['/<\?xml.*?\?>/s', '/<!DOCTYPE.*?>/s'], '', $svg));
    }
}

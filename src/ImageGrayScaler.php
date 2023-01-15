<?php

namespace FrameFinder;

use FrameFinder\Models\Image;
use GdImage;

class ImageGrayScaler
{
    private GdImage $gdImage;

    public function __construct($path)
    {
        $this->gdImage = imagecreatefromjpeg($path);
    }

    public function getGrayScaledImage(): Image
    {
        $this->imageBlackWhiteScale($this->gdImage);
        $this->imageFullColoredScale($this->gdImage);

        return new Image($this->gdImage);
    }

    private function imageFullColoredScale(GdImage $gdImage): void
    {
        imagepalettetotruecolor($gdImage);
        imagealphablending($gdImage, true);
    }

    /**
     * Set image to grayscale
     * Heavy method (work 0.06s)
     * @param GdImage $img
     * @param int $dither Smoothing
     */
    private function imageBlackWhiteScale(GdImage $img, int $dither = 0): void
    {
        // Set to 256 colors
        if (!($t = imagecolorstotal($img))) {
            $t = 256;
            imagetruecolortopalette($img, $dither, $t);
        }

        // Set to 2 colors
        for ($c = 0; $c < $t; $c++) {
            $col = imagecolorsforindex($img, $c);

            $sumCol = ($col['red'] + $col['green'] + $col['blue']) / 3;
            if ($sumCol < 220) {
                $i = 0;
            } else {
                $i = 255;
            }

            imagecolorset($img, $c, $i, $i, $i);
            imagetruecolortopalette($img, $dither, 2);
        }
    }
}
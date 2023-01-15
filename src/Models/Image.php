<?php

namespace FrameFinder\Models;

use GdImage;

class Image
{
    public GdImage $image;
    private int $width;
    private int $height;
    private int $delimeterColor = 255;

    public function __construct(GdImage $image)
    {
        $this->image = $image;
        $this->width = imagesx($image);
        $this->height = imagesy($image);

    }

    /**
     * @return GdImage
     */
    public function getGdImage(): GdImage
    {
        return $this->image;
    }

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    public function isLongRead(): bool
    {
        return $this->width * 3 < $this->height;
    }

    public function getDelimeterColor(): int
    {
        return $this->delimeterColor;
    }

    public function getMinimalFrameSizeWidth(): float
    {
        return $this->width / 6;
    }

    public function getMinimalFrameSizeHeight(): float
    {
        return $this->isLongRead() ? $this->width / 8 : $this->height / 8;
    }

}
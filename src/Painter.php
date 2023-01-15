<?php

namespace FrameFinder;

use FrameFinder\Models\Image;
use FrameFinder\Models\PolygonRepository;

class Painter
{
    private PolygonRepository $polygonRepository;
    private Image $image;

    const ALPHA = 75;

    public function __construct(Image $image, $polygonRepository)
    {
        $this->image = $image;
        $this->polygonRepository = $polygonRepository;
    }

    public function paintImage(bool $paintPolygons = true): void
    {
        $this->paintPolygonsOverImage($paintPolygons, self::ALPHA);

        header('Content-type: image/jpeg');
        imagejpeg($this->image->getGdImage());
    }

    /**
     * Paint all finded polygons over image
     * @param bool $paintPolygons
     * @param int $alpha
     */
    private function paintPolygonsOverImage(bool $paintPolygons, int $alpha): void
    {
        if ($paintPolygons) {
            foreach ($this->polygonRepository->getPolygons() as $polygon) {
                $color = imagecolorallocatealpha(
                    $this->image->getGdImage(),
                    rand(0, 255),
                    rand(0, 255),
                    rand(0, 255),
                    $alpha
                );

                imagefilledrectangle(
                    $this->image->getGdImage(),
                    $polygon->x,
                    $polygon->y,
                    $polygon->xe,
                    $polygon->ye,
                    $color
                );
            }
        }
    }
}
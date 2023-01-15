<?php

namespace FrameFinder;


use FrameFinder\Enums\Direction;
use FrameFinder\Exceptions\LineBreakException;
use FrameFinder\Models\Color;
use FrameFinder\Models\FramePolygon;
use FrameFinder\Models\Image;
use FrameFinder\Models\PolygonList;
use FrameFinder\Models\Queue;

class FrameFinder
{
    private Image $image;

    private Queue $verticalSearchQueue;
    private Queue $horisontalSearchQueue;

    const FRAME_WIDTH = 5;

    private PolygonList $polygonList;

    public function __construct(Image $image)
    {
        $this->image = $image;
        $this->polygonList = new PolygonList();

        $this->verticalSearchQueue = new Queue();
        $this->horisontalSearchQueue = new Queue();
    }

    public function getFramesCoordinates(): PolygonList
    {
        $this->createFirstPolygon();

        while ($this->verticalSearchQueue->hasTasks() || $this->horisontalSearchQueue->hasTasks()) {
            $this->resolveQueues();
        }

        return $this->polygonList;
    }

    private function resolveQueues(): void
    {
        foreach ($this->horisontalSearchQueue->getTasks() as $polygon) {
            $this->findHorizontalLinesInPolygon($polygon);

            $this->horisontalSearchQueue->clearTaskById($polygon->id);
        }

        if (!$this->horisontalSearchQueue->hasTasks()) {
            foreach ($this->verticalSearchQueue->getTasks() as $polygon) {
                $this->findVerticalLinesInPolygon($polygon);

                $this->verticalSearchQueue->clearTaskById($polygon->id);
            }
        }
    }

    private function createFirstPolygon(): void
    {
        $polygon = new FramePolygon(
            self::FRAME_WIDTH,
            self::FRAME_WIDTH,
            $this->image->getWidth() - self::FRAME_WIDTH,
            $this->image->getHeight() - self::FRAME_WIDTH
        );

        $this->polygonList->add($polygon);

        $this->addPolygonsToSearchList($polygon, Direction::Horisontal);
        $this->addPolygonsToSearchList($polygon, Direction::Vertical);
    }

    public function findHorizontalLinesInPolygon(FramePolygon $polygon): void
    {
        $lineX = $polygon->x + 10;

        $minimalFrameSizeHeight = $this->image->getMinimalFrameSizeHeight();
        $startPosition = $polygon->y + $minimalFrameSizeHeight;
        $endPosition = $polygon->ye - $minimalFrameSizeHeight;

        $step = 1;

        for ($i = $startPosition; $i < $endPosition; $i = $i + $step) {
            if ($this->isDotColorEqualsDelimeter($lineX, $i)) {

                try {
                    $this->filterHorizontalLine($i, $polygon->x + 50, $polygon->xe - 50);
                } catch (LineBreakException) {
                    continue;
                }

                $this->splitPolygon($i, $polygon, Direction::Horisontal);
                return;
            }
        }

    }

    private function findVerticalLinesInPolygon(FramePolygon $polygon): void
    {
        $frameHeight = $polygon->ye - $polygon->y; // высота поиска
        $lineY = $polygon->y + 2 * ($frameHeight / 3);

        $minimalFrameSizeWidth = $this->image->getMinimalFrameSizeWidth();
        $startPosition = $polygon->x + $minimalFrameSizeWidth;
        $endPosition = $polygon->xe - $minimalFrameSizeWidth;

        $step = 1;

        for ($i = $startPosition; $i < $endPosition; $i = $i + $step) {
            if ($this->isDotColorEqualsDelimeter($i, $lineY)) {

                try {
                    $this->filterVerticalLine($i, $polygon->y + 50, $polygon->ye - 50);
                } catch (LineBreakException) {
                    continue;
                }

                $this->splitPolygon($i, $polygon, Direction::Vertical);
                return;
            }
        }
    }

    private function addPolygonsToSearchList(array|FramePolygon $polygons, Direction $direction): void
    {
        if (!is_array($polygons)) {
            $polygons = [$polygons];
        }

        foreach ($polygons as $polygon) {
            switch ($direction) {
                case Direction::Vertical:
                    $this->verticalSearchQueue->addTask($polygon);
                    break;
                case Direction::Horisontal:
                    $this->horisontalSearchQueue->addTask($polygon);
                    break;
                default:
                    return;
            }
        }
    }

    private function splitPolygon(
        int          $delimeterCenter,
        FramePolygon $parentPolygon,
        Direction    $direction = Direction::NotExist
    ): void
    {
        switch ($direction) {
            case Direction::Horisontal:

                $first = new FramePolygon($parentPolygon->x, $parentPolygon->y, $parentPolygon->xe, $delimeterCenter);
                $second = new FramePolygon($parentPolygon->x, $delimeterCenter, $parentPolygon->xe, $parentPolygon->ye);

                $this->addPolygonsToSearchList($first, Direction::Vertical);
                $this->addPolygonsToSearchList($second, Direction::Vertical);

                $this->addPolygonsToSearchList($second, Direction::Horisontal);

                break;
            case Direction::Vertical:

                $first = new FramePolygon($parentPolygon->x, $parentPolygon->y, $delimeterCenter, $parentPolygon->ye);
                $second = new FramePolygon($delimeterCenter, $parentPolygon->y, $parentPolygon->xe, $parentPolygon->ye);

                $this->addPolygonsToSearchList($first, Direction::Horisontal);
                $this->addPolygonsToSearchList($second, Direction::Horisontal);

                $this->addPolygonsToSearchList($second, Direction::Vertical);

                break;
            default:
                return;
        }

        $this->polygonList->add($first, $parentPolygon->id);
        $this->polygonList->add($second, $parentPolygon->id);
    }

    private function isDotColorEqualsDelimeter(int $x, int $y): bool
    {
        $dotColorArray = $this->getDotColor($this->image, $x, $y);

        return (
            $dotColorArray->red == $this->image->getDelimeterColor() &&
            $dotColorArray->green == $this->image->getDelimeterColor() &&
            $dotColorArray->blue == $this->image->getDelimeterColor()
        );
    }

    private function getDotColor(Image $image, int $x, int $y): Color
    {
        $gdImage = $image->getGdImage();

        $color = imagecolorat($gdImage, $x, $y);
        $colors = imagecolorsforindex($gdImage, $color);

        $appColor = new Color();
        $appColor->red = $colors['red'];
        $appColor->green = $colors['green'];
        $appColor->blue = $colors['blue'];

        return $appColor;
    }

    /**
     * @throws LineBreakException
     */
    private function filterHorizontalLine(int $horizontalLine, int $x, int $xn): void
    {
        $searchInterval = 10;
        $wrongDotsCounter = 0;

        $subVerticalLines = [];
        for ($i = $x; $i < $xn; $i = $i + $searchInterval) {
            if ($this->isDotColorEqualsDelimeter($i, $horizontalLine)) {
                $subVerticalLines[] = $i;
                $wrongDotsCounter = 0;
            } else {
                $wrongDotsCounter++;

                if ($wrongDotsCounter > 20) {
                    throw new LineBreakException();
                }

            }
        }

        if (count($subVerticalLines) < ((($xn - $x) / $searchInterval) - 10)) {
            throw new LineBreakException();
        }
    }

    /**
     * @throws LineBreakException
     */
    private function filterVerticalLine(int $verticalLine, int $y, int $yn): void
    {
        $every = 20;

        $subHorizontalLines = [];
        for ($i = $y; $i < $yn; $i = $i + $every) {
            if ($this->isDotColorEqualsDelimeter($verticalLine, $i)) {
                $subHorizontalLines[] = $i;
            }
        }

        if (count($subHorizontalLines) < (($yn - $y) / $every - 10)) {
            throw new LineBreakException();
        }
    }
}
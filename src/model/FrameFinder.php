<?php
namespace Model;

use stdClass;

class FrameFinder
{

    private $image;
    private $width;
    private $height;

    const HORISONTAL_DIRECTION = 1;
    const VERTICAL_DIRECTION = 2;
    const INIT_DIRECTION = 3;

    const ACTION_SPLIT_HORISONTAL = 1;
    const ACTION_SPLIT_VERTICAL = 2;
    const ACTION_COMBITE_TO_NEXT = 4;
    const ACTION_FULLY_REMOVE = 5;


    private $delimiterColor = 255;
    private $minimalFrameSizeWidth = 250;
    private $minimalFrameSizeHeight = 250;

    private $verticalSearchQueue = [];
    private $horisontalSearchQueue = [];

    private $paintDotsQueue = [];

    private $DEBUG = 0;
    private $DEBUG_PARAMS = [
        'alpha' => 75,
        'show_checkers_dot' => 0,
    ];
    private $startTimer;

    /**
     *
     */
    public function createStartPolygon()
    {
        $polygon = $this->createNewPolygon(5, 5, $this->width - 5, $this->height - 5);
        $this->polygons[$polygon->id] = $polygon;

        $this->addPolygonsToSearchList($polygon, self::HORISONTAL_DIRECTION);
        $this->addPolygonsToSearchList($polygon, self::VERTICAL_DIRECTION);
    }

    /**
     * Set image to grayscale
     * Heavy method (work 0.06s)
     * @param $img
     * @param int $dither Smoothing
     */
    private function imageBlackWhiteScale(&$img, $dither = 0)
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

    /**
     * @param $img
     */
    private function imageFullColoredScale(&$img)
    {
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
    }

    /**
     * @return array
     */
    public function getPageSize()
    {
        return ['width' => $this->width, 'height' => $this->height];
    }

    /**
     * @param array $framesCoordinates
     * @return array
     */
    public function setFrameCoordinates(Array $framesCoordinates)
    {
        foreach ($framesCoordinates as $currentCoordinate) {
            $polygon = $this->createNewPolygon($currentCoordinate['x'], $currentCoordinate['y'], $currentCoordinate['xe'], $currentCoordinate['ye']);
            $this->polygons[$polygon->id] = $polygon;
        }
        return $this->getListOfPolygons($this->polygons);
    }

    /**
     * @return array
     */
    public function getFrameCoordinates()
    {
//        $this->getDelimiterTone();
        $this->createStartPolygon();

        $count = 0;
        while ($this->verticalSearchQueue || $this->horisontalSearchQueue) {
            foreach ($this->horisontalSearchQueue as $polygon) {
                $this->findHorizontalLinesInPolygon($polygon);
            }

            if (count($this->horisontalSearchQueue) === 0) {
                foreach ($this->verticalSearchQueue as $polygon) {
                    $this->findVerticalLinesInPolygon($polygon);
                }
            }

            $count++;
            if ($count > 500) { // Debug
                var_dump('Err. So much polygons', $this->verticalSearchQueue, $this->horisontalSearchQueue);
                exit;

            }
        }

        return $this->getListOfPolygons($this->polygons);
    }

    /**
     * Paint all finded polygons over image
     * Used for debug.
     * @param int $paintPolygons
     * @param int $paintPolygonNumber
     * @param int $paintAllDotInQueue
     * @param int $alpha
     */
    private function paintPolygonsOverImage($paintPolygons = 1, $paintPolygonNumber = 1, $paintAllDotInQueue = 1, $alpha = 75)
    {
        if ($paintPolygons) {
            foreach ($this->getListOfPolygons($this->polygons) as $polygon) {
                imagefilledrectangle($this->image, $polygon->x, $polygon->y, $polygon->xe, $polygon->ye, imagecolorallocatealpha($this->image, rand(0, 255), rand(0, 255), rand(0, 255), $alpha));
            }
        }

        if ($paintPolygonNumber) {
            imagefttext($this->image, 20, 0, 10, 30, imagecolorallocate($this->image, 0, 0, 0), 'C:/Windows/Fonts/tahoma.ttf', count($this->getListOfPolygons($this->polygons)));
        }

        if ($paintAllDotInQueue) {
            $this->paintAllDotInQueue();
        }
    }

    /**
     *
     */
    private function setDefaultValues()
    {
        $verticalLongRead = 0;
        if ($this->width * 3 < $this->height) {
            $verticalLongRead = 1;
        }

        $this->minimalFrameSizeWidth = $this->width / 6;
        $this->minimalFrameSizeHeight = $verticalLongRead ? $this->width / 8 : $this->height / 8;

    }

    /**
     * @param $image
     */
    private function setImageSize($image)
    {
        $this->width = imagesx($image);
        $this->height = imagesy($image);
    }

    /**
     * FrameFinder constructor.
     */
    public function __construct()
    {
        $this->startTimer = microtime(true);
    }

    /**
     * Used for debug
     * @return mixed
     */
    public function getScriptTime()
    {
        return microtime(true) - $this->startTimer;
    }

    /**
     * @param $path
     * @param int $useBlackWhite
     * @return resource
     */
    public function getImageByPath($path, $useBlackWhite = 1)
    {
        $this->image = imagecreatefromjpeg($path);

        if ($useBlackWhite) {
            $this->imageBlackWhiteScale($this->image);
        }

        $this->setImageSize($this->image);

        if ($useBlackWhite) {
            $this->imageFullColoredScale($this->image);
        }

        $this->setDefaultValues();

        return $this->image;
    }

    /**
     *
     */
    private function getDelimiterTone()
    {
        $colorArray = $this->getImageColor2($this->image, 2, 2);
//        0-255 |
        $red = round($colorArray->red / 20, 0);
        $green = round($colorArray->green / 20, 0);
        $blue = round($colorArray->blue / 20, 0);

        if ($red == $green && $green == $blue) {
            $this->delimiterColor = array_sum([$colorArray->red, $colorArray->green, $colorArray->blue]) / 3;
        }
    }

    /**
     * @var array
     */
    private $polygons = [];

    /**
     * Найти горизонтальные линии
     * @param FramePolygon $polygon
     */
    public function findHorizontalLinesInPolygon($polygon)
    {
        $lineX = $polygon->x + 10;

        for ($i = $polygon->y + $this->minimalFrameSizeHeight; $i < $polygon->ye - $this->minimalFrameSizeHeight; $i = $i + 1) {

            if ($this->checkDotTone($lineX, $i)) {
//                $horizontalDelimiterCenter = $this->findHorizontalDelimiterCenter($lineX, $i);

                if ($i - $polygon->y < ($this->minimalFrameSizeHeight) || $polygon->ye - $i < ($this->minimalFrameSizeHeight)) {
                    continue;
                }

                $horizontalDelimiterCenter = $this->filterHorizontalLine($i, $polygon->x + 50, $polygon->xe - 50);

                if ($horizontalDelimiterCenter) {
                    $this->splitPolygon($horizontalDelimiterCenter, $polygon, self::HORISONTAL_DIRECTION);
                    unset($this->horisontalSearchQueue[$polygon->id]);

                    return;
                }
            }
        }
        unset($this->horisontalSearchQueue[$polygon->id]);
    }

    /**
     * @param $polygons
     * @param int $direction
     */
    private function removePolygonsFromSearchList($polygons, $direction = self::INIT_DIRECTION)
    {
        if (!is_array($polygons)) {
            $polygons = [$polygons];
        }

        foreach ($polygons as $polygon) {

            switch ($direction) {

                case self::VERTICAL_DIRECTION:
                    unset($this->verticalSearchQueue[$polygon->id]);
                    break;
                case self::HORISONTAL_DIRECTION:
                    unset($this->horisontalSearchQueue[$polygon->id]);
                    break;
                default:
                    return;
            }
        }
    }

    /**
     * @param $polygons
     * @param int $direction
     */
    private function addPolygonsToSearchList($polygons, $direction = self::INIT_DIRECTION)
    {
        if (!is_array($polygons)) {
            $polygons = [$polygons];
        }

        foreach ($polygons as $polygon) {

            switch ($direction) {

                case self::VERTICAL_DIRECTION:
                    $this->verticalSearchQueue[$polygon->id] = $polygon;
                    break;
                case self::HORISONTAL_DIRECTION:
                    $this->horisontalSearchQueue[$polygon->id] = $polygon;
                    break;
                default:
                    return;
            }
        }
    }

    /**
     * @param $polygon
     * @return array|boolean
     */
    public function combinePolygonWithParent($polygon)
    {
        $polygons = $this->polygons;

        foreach ($polygons as $key => $currentPolygon) {

            $nextPolygon = next($polygons);

            $directon = self::INIT_DIRECTION;
            if ($polygon->id === $key) {

                if ($currentPolygon->x === $nextPolygon->x && $currentPolygon->xe === $nextPolygon->xe) {
                    $directon = self::VERTICAL_DIRECTION;
                }

                if ($currentPolygon->y === $nextPolygon->y && $currentPolygon->ye === $nextPolygon->ye) {
                    $directon = self::HORISONTAL_DIRECTION;
                }

                if ($directon === self::VERTICAL_DIRECTION || $directon === self::HORISONTAL_DIRECTION) {
                    $newPolygon = $this->createNewPolygon($currentPolygon->x, $currentPolygon->y, $nextPolygon->xe, $nextPolygon->ye);
                    $this->searchAndIncertPolygon($this->polygons, $key, $newPolygon);
                    $this->searchAndIncertPolygon($this->polygons, $nextPolygon->id, false);
                }
            }
        }

        return $this->getListOfPolygons($this->polygons);
    }

    /**
     * @param $polygon
     * @return bool|FramePolygon
     */
    public function fullyRemoveFrame($polygon)
    {
        return $this->searchAndIncertPolygon($this->polygons, $polygon->id, false);
    }
    
    /**
     * @param $delimeterCenter
     * @param FramePolygon|boolean $parentPolygon
     * @param int $direction
     * @return void
     */
    private function splitPolygon($delimeterCenter, $parentPolygon = false, $direction = self::INIT_DIRECTION)
    {
        switch ($direction) {
            case self::HORISONTAL_DIRECTION:

                $first = $this->createNewPolygon($parentPolygon->x, $parentPolygon->y, $parentPolygon->xe, $delimeterCenter); // верхний px, py, xn, y
                $second = $this->createNewPolygon($parentPolygon->x, $delimeterCenter, $parentPolygon->xe, $parentPolygon->ye); // нижний

                $this->addPolygonsToSearchList($first, self::VERTICAL_DIRECTION);
                $this->addPolygonsToSearchList($second, self::VERTICAL_DIRECTION);

                $this->addPolygonsToSearchList($second, self::HORISONTAL_DIRECTION);

                break;
            case self::VERTICAL_DIRECTION:

                $first = $this->createNewPolygon($parentPolygon->x, $parentPolygon->y, $delimeterCenter, $parentPolygon->ye); // левый
                $second = $this->createNewPolygon($delimeterCenter, $parentPolygon->y, $parentPolygon->xe, $parentPolygon->ye); // правый

                $this->addPolygonsToSearchList($first, self::HORISONTAL_DIRECTION);
                $this->addPolygonsToSearchList($second, self::HORISONTAL_DIRECTION);

                $this->addPolygonsToSearchList($second, self::VERTICAL_DIRECTION);

                break;
            default:
                return;
        }
        $this->searchAndIncertPolygon($this->polygons, $parentPolygon->id, [$first->id => $first, $second->id => $second]);

    }

    /**
     * @param $dotArray array of percent of x and y dot
     */
    public function manuallySplitPolygons($dotArray)
    {
        if (!$dotArray || !count($dotArray)) {
            return;
        }

        foreach ($dotArray as $dot) {
                $xCoord = ($dot['x'] * $this->width) / 100;
                $yCoord = ($dot['y'] * $this->height) / 100;

                $polygon = $this->searchPolygonByDot($xCoord, $yCoord);

                if (!$polygon) {
                    return;
                }

                $direction = $dot['direction'];
                switch ($direction) {
                    case self::ACTION_SPLIT_HORISONTAL:
                        $this->splitPolygon($yCoord, $polygon, self::HORISONTAL_DIRECTION);
                        break;
                    case self::ACTION_SPLIT_VERTICAL:
                        $this->splitPolygon($xCoord, $polygon, self::VERTICAL_DIRECTION);
                        break;
                    case self::ACTION_COMBITE_TO_NEXT:
                        $this->combinePolygonWithParent($polygon);
                        break;
                    case self::ACTION_FULLY_REMOVE:
                        $this->fullyRemoveFrame($polygon);
                        break;

                }
        }
    }

    /**
     * @param $x
     * @param $y
     * @return bool|FramePolygon
     */
    private function searchPolygonByDot($x, $y)
    {
        $currentPolugonsList = $this->getListOfPolygons($this->polygons, 0);
        /** @var FramePolygon $polygon */
        foreach ($currentPolugonsList as $polygon) {
            if ($polygon->x < $x && $polygon->xe > $x && $polygon->y < $y && $polygon->ye > $y) {
                return $polygon;
            }
        }

        return false;
    }


    /**
     * @param array $polygons
     * @param int $searchId
     * @param FramePolygon[]|FramePolygon $insertedData
     * @return bool|FramePolygon
     */
    public function searchAndIncertPolygon(array &$polygons, $searchId, $insertedData = null)
    {
        if (!$searchId) {
//            die ('No search id');
            return false; // TODO throw exception
        }


        foreach ($polygons as $key => $polygon) {
            if (is_array($polygon)) {
                $result = $this->searchAndIncertPolygon($polygon, $searchId, $insertedData);

                if ($result) {
                    $polygons[$key] = $polygon;
                    return $result;
                }

            } else {
                if ($polygon->id == $searchId) {
                    if ($insertedData) {
                        $polygons[$key] = $insertedData;
                    } else {
                        if ($insertedData === false) {
                            unset($polygons[$key]);
                        }
                    }

                    //success
                    return $polygon;
                }
            }
        }
        return false;
    }

    /**
     *
     * todo make private
     * @param $polygons
     * @param int $unsetId
     * @return array
     */
    public function getListOfPolygons($polygons, $unsetId = 1)
    {
        $list = [];

        if ($polygons instanceof FramePolygon) {
            return [$polygons];
        }

        foreach ($polygons as $key => $polygon) {
            if (is_array($polygon)) {
                $innerList = $this->getListOfPolygons($polygon, $unsetId);

                if ($innerList) {
                    $list = array_merge($list, $innerList);
                }
            } else {

                $polygonClone = clone $polygon;

                if ($unsetId) {
                    unset($polygonClone->id);
                }

                $list[] = $polygonClone;
            }
        }
        return $list;
    }

    /**
     * @return array
     */
    public function getReadyToDbPolygons()
    {
        return $this->getListOfPolygons($this->polygons);
    }

    /**
     * @param $x
     * @param $xe
     * @param $y
     * @param $ye
     * @return FramePolygon
     */
    private function createNewPolygon($x, $y, $xe, $ye)
    {

        $currentPolygon = new FramePolygon();

        $currentPolygon->id = rand(1, 9999);
        if ($this->polygons && $this->searchAndIncertPolygon($this->polygons, $currentPolygon->id)) {
            return $this->createNewPolygon($x, $y, $xe, $ye);
        }

        $currentPolygon->x = $x;
        $currentPolygon->xe = $xe;
        $currentPolygon->y = $y;
        $currentPolygon->ye = $ye;
        return $currentPolygon;
    }

    /**
     * @param $x
     * @param $y
     * @return mixed
     */
    private function findHorizontalDelimiterCenter($x, $y)
    {
        $window = (int)$this->height / 60;
        $delimiterDots = [];

        for ($i = $y - $window; $i < $y + $window; $i = $i + 1) {

            if ($this->checkDotTone($x, $i)) {
                $delimiterDots[] = $i;
            }
        }

        // Возвращаем точку из середины
        return $delimiterDots[(int)count($delimiterDots) / 2];
    }

    /**
     * @param $x
     * @param $y
     * @return mixed
     */
    private function findVerticalDelimiterCenter($x, $y)
    {
        $window = (int)$this->width / 20;
        $delimiterDots = [];

        $from = $x - $window > 0 ? $x - $window : 0;
        $to = $x + $window < $this->width ? $x + $window : $this->width;

        for ($i = $from; $i < $to; $i = $i + 1) {
            if ($this->checkDotTone($i, $y)) {
                $delimiterDots[] = $i;
            }
        }
        return $delimiterDots[(int)count($delimiterDots) / 2];
    }

    /**
     * @param $x
     * @param $y
     * @return bool
     */
    private function checkDotTone($x, $y)
    {
        $dotColorArray = $this->getImageColor($this->image, $x, $y);
        if (
            $dotColorArray['red'] == $this->delimiterColor &&
            $dotColorArray['green'] == $this->delimiterColor &&
            $dotColorArray['blue'] == $this->delimiterColor
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $debug
     * @param array $userParams
     */
    public function setDebug($debug = 0, $userParams = [])
    {
        $this->DEBUG_PARAMS = array_replace($this->DEBUG_PARAMS, $userParams);
        $this->DEBUG = $debug;
    }

    /**
     * @param $image
     */
    public function paintImage($image)
    {
        $this->paintPolygonsOverImage($this->DEBUG, $this->DEBUG, $this->DEBUG_PARAMS['show_checkers_dot'], $this->DEBUG_PARAMS['alpha']);

        header('Content-type: image/jpeg');
        imagejpeg($image);
    }

    /**
     * @param $image
     * @param $width
     * @param $height
     * @return array
     */
    private function getImageColor($image, $width, $height)
    {
        $color = imagecolorat($image, $width, $height);
        $colors = imagecolorsforindex($image, $color);
        return $colors;
    }

    /**
     * @param $image
     * @param $width
     * @param $height
     * @return array|stdClass
     */
    private function getImageColor2($image, $width, $height)
    {
        $outColors = [];
        for ($i = -2; $i <= 2; $i = $i + 1) {
            $color = imagecolorat($image, $width + $i, $height + $i);
            $colors = imagecolorsforindex($image, $color);

            $outColors = new stdClass();
            $outColors->red = $outColors->red ? ($colors['red'] + $outColors->red) / 2 : $colors['red'];
            $outColors->green = $outColors->green ? ($colors['green'] + $outColors->green) / 2 : $colors['green'];
            $outColors->blue = $outColors->blue ? ($colors['blue'] + $outColors->blue) / 2 : $colors['blue'];
        }

        return $outColors;
    }


    /**
     * @param $horizontalLine
     * @param $x
     * @param $xn
     * @return null
     */
    private function filterHorizontalLine($horizontalLine, $x, $xn)
    {
        $searchInterval = 10;
        $wrongDotsCounter = 0;

        $this->addDotToQueue($x, $horizontalLine, 255, 0, 0);

        $subVerticalLines = [];
        for ($i = $x; $i < $xn; $i = $i + $searchInterval) {
            if ($tone = $this->checkDotTone($i, $horizontalLine)) {
                $subVerticalLines[] = $i;
                $this->addDotToQueue($i, $horizontalLine, 0, 255, 0);
                $wrongDotsCounter = 0;
            } else {
                $wrongDotsCounter++;

                if ($wrongDotsCounter > 20) {
                    return null;
                }

            }
        }
        $horizontalLinesCount[$horizontalLine] = count($subVerticalLines);

        if (count($subVerticalLines) < ((($xn - $x) / $searchInterval) - 10)) {
            return null;
        }

        return $horizontalLine;
    }

    /**
     * @param $verticalLine
     * @param $y
     * @param $yn
     * @return null
     */
    private function filterVerticalLine($verticalLine, $y, $yn)
    {
        $every = 20;

        $subHorizontalLines = [];
        for ($i = $y; $i < $yn; $i = $i + $every) {
            if ($this->checkDotTone($verticalLine, $i)) {
                $subHorizontalLines[] = $i;
            }
        }
        $verticalLinesCount[$verticalLine] = count($subHorizontalLines);

        if (count($subHorizontalLines) < (($yn - $y) / $every - 10)) {
            $verticalLine = null;
        }

        return $verticalLine;
    }

    /**
     * @param FramePolygon $polygon
     */
    private function findVerticalLinesInPolygon(FramePolygon $polygon)
    {
        $frameHeight = $polygon->ye - $polygon->y; // высота поиска
        $lineY = $polygon->y + 2 * ($frameHeight / 3);

        $every = 1;
        for ($i = $polygon->x + $this->minimalFrameSizeWidth; $i < $polygon->xe - $this->minimalFrameSizeWidth; $i = $i + $every) {
            if ($this->checkDotTone($i, $lineY)) {

                if ($i - $polygon->x < ($this->height / 30) || $polygon->xe - $i < ($this->height / 30)) {
                    continue;
                }

//                $verticalDelimiterCenter = $this->findVerticalDelimiterCenter($i, $lineY);
                $verticalDelimiterCenter = $i;
                $verticalDelimiterCenter = $this->filterVerticalLine($verticalDelimiterCenter, $polygon->y + 50, $polygon->ye - 50);
                if ($verticalDelimiterCenter) {

                    $this->splitPolygon($verticalDelimiterCenter, $polygon, self::VERTICAL_DIRECTION);
                    unset($this->verticalSearchQueue[$polygon->id]);
                    return;

                }
            }
        }

        unset($this->verticalSearchQueue[$polygon->id]);
    }

    /**
     * Debug method
     * @param $x
     * @param $y
     * @param $r
     * @param $g
     * @param $b
     */
    private function addDotToQueue($x, $y, $r, $g, $b)
    {
        $dotObject = new stdClass();
        $dotObject->x = $x;
        $dotObject->y = $y;
        $dotObject->color = imagecolorallocate($this->image, $r, $g, $b);

        $this->paintDotsQueue[] = $dotObject;
    }

    /**
     *
     */
    private function paintAllDotInQueue()
    {
        foreach ($this->paintDotsQueue as $dot) {
            imagefilledellipse($this->image, $dot->x, $dot->y, 10, 10, $dot->color);
        }
    }
}
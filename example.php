<?php

include_once "vendor/autoload.php";

use FrameFinder\FrameFinder;
use FrameFinder\ImageGrayScaler;
use FrameFinder\Painter;

$path = 'data/image.jpg';

$imagePreparer = new ImageGrayScaler($path);
$image = $imagePreparer->getGrayScaledImage();

$frameFinder = new FrameFinder($image);
$frameArray = $frameFinder->getFramesCoordinates();

$painter = new Painter($image, $frameArray);
$painter->paintImage();
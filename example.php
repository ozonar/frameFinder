<?php

use Model\FrameFinder;

$path = 'path/to/comix/page.webp';

$frameFinder = new FrameFinder();
$image = $frameFinder->getImageByPath($path);

$frameArray = $frameFinder->getFrameCoordinates();

$frameFinder->paintImage($image);
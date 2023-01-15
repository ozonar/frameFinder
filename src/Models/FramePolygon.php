<?php

namespace FrameFinder\Models;

class FramePolygon
{
    public int $id;

    public function __construct(
        public $x,
        public $y,
        public $xe,
        public $ye,
    )
    {
    }
}
<?php

namespace FrameFinder\Repositories;

use FrameFinder\Models\FramePolygon;

class PolygonRepository
{
    private array $polygons = [];

    public function add(FramePolygon $polygon, $parentId = 0): void
    {
        if (!isset($polygon->id)) {
            $polygon->id = $this->getNotExistId();
        }

        $polygon->parentId = $parentId;

        if ($parentId) {
            $this->removeById($parentId);
        }

        $this->polygons[$polygon->id] = $polygon;
    }

    public function removeById(int $id): void
    {
        unset($this->polygons[$id]);
    }

    public function getPolygons(): array
    {
        return $this->polygons;
    }

    public function getNotExistId(): int
    {
        $newId = rand(1000, 9999);
        if (!$this->polygons[$newId]) {
            return $newId;
        } else {
            return $this->getNotExistId();
        }
    }
}
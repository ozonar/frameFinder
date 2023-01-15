<?php

namespace FrameFinder\Models;

class Queue
{
    private array $queue = [];

    public function getTasks(): array
    {
        return $this->queue;
    }

    public function addTask(FramePolygon $polygon): void
    {
        $this->queue[$polygon->id] = $polygon;
    }

    public function clearTaskById(int $id): void
    {
        unset($this->queue[$id]);
    }

    public function hasTasks():bool
    {
        return (bool)count($this->queue);
    }
}
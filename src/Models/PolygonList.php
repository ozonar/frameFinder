<?php

namespace FrameFinder\Models;

class PolygonList
{
    private array $polygons = [];

    public function add(FramePolygon $polygon): void
    {
        if (!isset($polygon->id)) {
            $polygon->id = $this->getNotExistId();
        }

        $this->polygons[$polygon->id] = $polygon;
    }

    public function addGroup(array $polygons, $parentId = null): void
    {
        foreach ($polygons as $polygon) {
            $polygon->id = $this->getNotExistId();
            $polygons[$polygon->id] = $polygon;
        }

        $this->searchAndInsertPolygon($parentId, $polygons, $this->polygons);
    }

    public function getPolygons(): array
    {
        return $this->polygons;
    }

    public function getNotExistId(): int
    {
        $newId = rand(1, 999999);
        if (!$this->polygons[$newId]) {
            return $newId;
        } else {
            return $this->getNotExistId();
        }
    }

    public function getList($unsetId = 1): array
    {
        return $this->getListOfPolygons($this->polygons, $unsetId);
    }
    /**
     * @param $polygons
     * @param int $unsetId
     * @return FramePolygon[]
     */
    private function getListOfPolygons($polygons, int $unsetId = 1): array
    {
        $list = [];

        if ($polygons instanceof FramePolygon) {
            return [$polygons];
        }

        foreach ($polygons as $polygon) {
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

    private function searchAndInsertPolygon(int $searchId, $insertedData = null, &$polygons = null)
    {
        foreach ($polygons as $key => $polygon) {
            if (is_array($polygon)) {
                $result = $this->searchAndInsertPolygon($searchId, $insertedData, $polygon);

                $polygons[$key] = $polygon;
                return $result;
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

//        throw new NoIdException();
    }
}
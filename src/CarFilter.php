<?php

namespace Page\Analyzer;

class CarFilter
{
    public function filterCarsByMark(array $cars, string $term): array
    {
        return array_filter($cars, fn($car) => str_contains(strtolower($car->getMake()), strtolower($term)) !== false);
    }
}

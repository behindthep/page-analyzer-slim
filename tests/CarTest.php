<?php

namespace Php\Package\Tests;

use PHPUnit\Framework\TestCase;
use Page\Analyzer\Car;

class UserTest extends TestCase
{
    private Car $car;

    public function setUp(): void
    {
        $this->car = new Car();
    }

    public function testGetMake(): void
    {
        $make = 'Lada';
        $this->car->setMake($make);

        $this->assertEquals($make, $this->car->getMake());
    }
}

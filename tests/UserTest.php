<?php

namespace Php\Package\Tests;

use PHPUnit\Framework\TestCase;
use Page\Analyzer\User;

class UserTest extends TestCase
{
    public function testGetUsername(): void
    {
        $name = 'john';
        $user = new User($name, '12345');

        $this->assertEquals($name, $user->getUsername());
    }
}

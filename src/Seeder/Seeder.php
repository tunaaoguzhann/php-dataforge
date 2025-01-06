<?php

namespace PhpDataforge\Seeder;

use PhpDataforge\Database\Connection;

abstract class Seeder
{
    protected Factory $factory;

    public function __construct(protected Connection $connection)
    {
        $this->factory = new Factory($connection);
    }

    abstract public function run(): void;
} 
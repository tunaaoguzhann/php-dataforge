<?php

namespace PhpDataforge\Seeder;

use Faker\Factory as FakerFactory;
use PhpDataforge\Database\Connection;

class Factory
{
    private $faker;
    
    public function __construct(private Connection $connection)
    {
        $this->faker = FakerFactory::create();
    }

    public function create(string $table, callable $definition, int $count = 1): void
    {
        $records = [];
        for ($i = 0; $i < $count; $i++) {
            $records[] = $definition($this->faker);
        }

        $this->connection->getQueryBuilder()
            ->table($table)
            ->insertMultiple($records);
    }
} 
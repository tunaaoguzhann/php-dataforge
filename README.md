# PHP DataForge

PHP DataForge is a powerful and flexible database migration and seeding tool designed for PHP applications. It provides a robust solution for managing database schemas and populating databases with test data across multiple database systems.

## Features

- **Database Migration System**
  - Schema creation and modification
  - Support for multiple database systems
  - Intuitive table creation and modification API
  - Rollback capabilities
  - Version control for database changes

- **Database Seeding**
  - Factory pattern for generating test data
  - Integration with Faker for realistic data generation
  - Flexible seeding configurations
  - Support for relationships and complex data structures

- **Query Builder**
  - Fluent interface for database operations
  - Support for complex queries
  - Database-agnostic query construction

## Requirements

- PHP 8.1 or higher
- PDO Extension
- Supported Databases:
  - MySQL
  - PostgreSQL
  - (More databases coming soon)

## Installation

```bash
composer require tunaaoguzhann/php-dataforge
```

## Basic Usage

### Creating a Migration

```php
use PhpDataforge\Migration\Schema;
use PhpDataforge\Migration\TableBuilder;

class CreateUsersTable
{
    public function up(Schema $schema)
    {
        $schema->create('users', function (TableBuilder $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
        });
    }

    public function down(Schema $schema)
    {
        $schema->dropIfExists('users');
    }
}
```

### Creating a Seeder

```php
use PhpDataforge\Seeder\Seeder;
use PhpDataforge\Seeder\Factory;

class UserSeeder extends Seeder
{
    public function run()
    {
        Factory::times(10)->create('users', function ($faker) {
            return [
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        });
    }
}
```

### Using Query Builder

```php
use PhpDataforge\Database\Connection;

// Create a connection instance
$config = [
    'driver' => 'mysql', // or 'pgsql' for PostgreSQL
    'host' => 'localhost',
    'dbname' => 'your_database',
    'user' => 'your_username',
    'pass' => 'your_password'
];

$connection = new Connection($config);
$query = $connection->getQueryBuilder();

// SELECT Operations
$users = $query->table('users')
    ->select(['id', 'name', 'email'])
    ->where('age', '>', 18)
    ->orderBy('name', 'asc')
    ->limit(10)
    ->get();

// INSERT Operations
$query->table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 25
]);

// UPDATE Operations
$query->table('users')
    ->where('id', 1)
    ->update([
        'name' => 'Jane Doe',
        'age' => 26
    ]);

// DELETE Operations
$query->table('users')
    ->where('id', 1)
    ->delete();

// Complex Queries
$result = $query->table('users')
    ->select(['users.name', 'orders.total'])
    ->join('orders', 'users.id', '=', 'orders.user_id')
    ->where('orders.total', '>', 1000)
    ->groupBy('users.id')
    ->having('orders.total', '>', 5000)
    ->get();
```

## Configuration

Create a `.env` file in your project root:

```env
DB_DRIVER=mysql # or pgsql for PostgreSQL
DB_HOST=localhost
DB_NAME=your_database
DB_USER=your_username
DB_PASS=your_password
```

## License

This project is licensed under the MIT License.

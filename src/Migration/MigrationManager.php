<?php

namespace PhpDataforge\Migration;

use PhpDataforge\Database\Connection;

class MigrationManager
{
    public function __construct(private Connection $connection)
    {
    }

    public function migrate(array $migrations): void
    {
        foreach ($migrations as $migration) {
            $this->runMigration($migration);
        }
    }

    private function runMigration($migration): void
    {
        if (!method_exists($migration, 'getTableName')) {
            throw new \Exception('Migration sınıfı getTableName metodunu içermelidir.');
        }

        $tableName = $migration->getTableName();
        $schema = new Schema($tableName);
        $migration->up($schema);
        
        $builder = new TableBuilder(
            $tableName,
            $this->connection->getQueryBuilder()
        );

        try {

            if (!empty($schema->getStructure())) {
                $builder->build($schema);
                echo "\033[32m✓ '{$tableName}' tablosu başarıyla oluşturuldu.\033[0m\n";
            }

            if (!empty($schema->getModifications())) {
                $modifications = $schema->getModifications();
                $builder->modify($modifications);
                
                foreach ($modifications as $mod) {
                    $message = match($mod['type']) {
                        'add' => "'{$mod['column']}' kolonu eklendi",
                        'change' => "'{$mod['column']}' kolonu güncellendi",
                        'drop' => "'{$mod['column']}' kolonu silindi",
                        'rename' => "'{$mod['from']}' kolonu '{$mod['to']}' olarak yeniden adlandırıldı",
                        'index' => "İndeks oluşturuldu: " . implode(', ', (array)$mod['columns']),
                        default => "Değişiklik uygulandı: {$mod['type']}"
                    };
                    echo "\033[32m✓ '{$tableName}' tablosunda {$message}.\033[0m\n";
                }
            }


            if (method_exists($migration, 'seed')) {
                try {
                    $initialCount = $this->connection->getQueryBuilder()
                        ->table($tableName)
                        ->count();

                    $migration->seed($this->connection->getQueryBuilder());

                    $finalCount = $this->connection->getQueryBuilder()
                        ->table($tableName)
                        ->count();

                    $insertedCount = $finalCount - $initialCount;
                    if ($insertedCount > 0) {
                        echo "\033[32m✓ '{$tableName}' tablosuna {$insertedCount} adet test verisi eklendi.\033[0m\n";
                    }
                } catch (\Exception $e) {
                    echo "\033[31m✗ Test verisi eklenirken hata: {$e->getMessage()}\033[0m\n";
                    throw $e;
                }
            }

        } catch (\Exception $e) {
            echo "\033[31m✗ Hata: {$e->getMessage()}\033[0m\n";
            throw $e;
        }
    }
} 
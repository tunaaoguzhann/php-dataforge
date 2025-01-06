<?php

namespace PhpDataforge\Migration;

use PhpDataforge\Database\QueryBuilder;

class TableBuilder
{
    public function __construct(
        private string $tableName,
        private QueryBuilder $queryBuilder
    ) {
    }

    public function build(Schema $schema): bool
    {
        return $this->queryBuilder->create($this->tableName, $schema->getStructure());
    }

    public function modify(array $modifications): void
    {
        foreach ($modifications as $modification) {
            $this->applyModification($modification);
        }
    }

    private function applyModification(array $modification): void
    {
        match($modification['type']) {
            'add' => $this->addColumn($modification['column'], $modification['definition'], $modification['after'] ?? null),
            'change' => $this->alterColumn($modification['column'], $modification['definition']),
            'drop' => $this->dropColumn($modification['column']),
            'rename' => $this->renameColumn($modification['from'], $modification['to']),
            'index' => $this->createIndex($modification['columns']),
            default => throw new \Exception("Desteklenmeyen modifikasyon tipi: {$modification['type']}")
        };
    }

    private function alterColumn(string $column, array $definition): void
    {
        $sql = sprintf(
            'ALTER TABLE %s MODIFY COLUMN %s',
            $this->tableName,
            $this->buildColumnDefinition($column, $definition)
        );

        if (isset($definition['after'])) {
            $sql .= " AFTER {$definition['after']}";
        }

        $this->queryBuilder->raw($sql);
    }

    private function dropColumn(string $column): void
    {
        $sql = sprintf(
            'ALTER TABLE %s DROP COLUMN %s',
            $this->tableName,
            $column
        );
        $this->queryBuilder->raw($sql);
    }

    private function renameColumn(string $from, string $to): void
    {
        if ($this->queryBuilder->getConnection()->getDriver() === 'pgsql') {
            $sql = sprintf(
                'ALTER TABLE %s RENAME COLUMN %s TO %s',
                $this->tableName,
                $from,
                $to
            );
        } else {
            $sql = sprintf(
                "SHOW COLUMNS FROM %s WHERE Field = ?",
                $this->tableName
            );
            
            $stmt = $this->queryBuilder->getConnection()->getPdo()->prepare($sql);
            $stmt->execute([$from]);
            $column = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$column) {
                throw new \Exception("Kolon bulunamadı: {$from}");
            }

            $definition = $column['Type'];
            if ($column['Null'] === 'NO') {
                $definition .= ' NOT NULL';
            }
            if ($column['Default'] !== null) {
                $definition .= ' DEFAULT ' . (is_numeric($column['Default']) ? $column['Default'] : "'" . $column['Default'] . "'");
            }
            if ($column['Extra']) {
                $definition .= ' ' . strtoupper($column['Extra']);
            }

            $sql = sprintf(
                'ALTER TABLE %s CHANGE %s %s %s',
                $this->tableName,
                $from,
                $to,
                $definition
            );
        }

        try {
            $this->queryBuilder->raw($sql);
        } catch (\PDOException $e) {
            throw new \Exception("Kolon yeniden adlandırma hatası: " . $e->getMessage());
        }
    }

    private function createIndex(array $columns): void
    {
        $indexName = $this->tableName . '_' . implode('_', $columns) . '_idx';
        $sql = sprintf(
            'CREATE INDEX %s ON %s (%s)',
            $indexName,
            $this->tableName,
            implode(', ', $columns)
        );
        $this->queryBuilder->raw($sql);
    }

    private function addColumn(string $column, array $definition, ?string $after = null): void
    {
        $sql = sprintf(
            'ALTER TABLE %s ADD COLUMN %s',
            $this->tableName,
            $this->buildColumnDefinition($column, $definition)
        );

        if ($after !== null) {
            $sql .= " AFTER {$after}";
        }

        try {
            $this->queryBuilder->raw($sql);
        } catch (\PDOException $e) {
            throw new \Exception("Kolon ekleme hatası: " . $e->getMessage());
        }
    }

    private function buildColumnDefinition(string $name, array $definition): string
    {
        $type = match($this->queryBuilder->getConnection()->getDriver()) {
            'pgsql' => $this->buildPostgresColumnType($definition),
            'mysql' => $this->buildMysqlColumnType($definition),
            default => throw new \Exception("Desteklenmeyen veritabanı: {$this->queryBuilder->getConnection()->getDriver()}")
        };

        $parts = [$name, $type];

        if ($this->queryBuilder->getConnection()->getDriver() === 'pgsql') {
            // PostgreSQL için
            if (isset($definition['primaryKey']) && $definition['primaryKey']) {
                if (isset($definition['autoIncrement']) && $definition['autoIncrement']) {
                    $parts = [$name, 'SERIAL PRIMARY KEY'];
                    return implode(' ', $parts);
                }
                $parts[] = 'PRIMARY KEY';
            }
        } else {
            if (isset($definition['primaryKey']) && $definition['primaryKey']) {
                $parts[] = 'PRIMARY KEY';
            }
            if (isset($definition['autoIncrement']) && $definition['autoIncrement']) {
                $parts[] = 'AUTO_INCREMENT';
            }
        }

        if (isset($definition['nullable'])) {
            $parts[] = $definition['nullable'] ? 'NULL' : 'NOT NULL';
        }

        if (isset($definition['default'])) {
            $default = is_string($definition['default']) 
                ? "'{$definition['default']}'" 
                : $definition['default'];
            $parts[] = "DEFAULT {$default}";
        }

        if (isset($definition['unique']) && $definition['unique']) {
            $parts[] = 'UNIQUE';
        }

        return implode(' ', $parts);
    }

    private function buildPostgresColumnType(array $definition): string
    {
        return match($definition['type']) {
            'integer' => 'INTEGER',
            'varchar' => isset($definition['length']) 
                ? "VARCHAR({$definition['length']})" 
                : "VARCHAR(255)",
            'text' => 'TEXT',
            'timestamp' => 'TIMESTAMP',
            'boolean' => 'BOOLEAN',
            'decimal' => isset($definition['precision']) && isset($definition['scale'])
                ? "DECIMAL({$definition['precision']},{$definition['scale']})"
                : 'DECIMAL(10,2)',
            'json' => 'JSONB',
            default => throw new \Exception("Desteklenmeyen kolon tipi: {$definition['type']}")
        };
    }

    private function buildMysqlColumnType(array $definition): string
    {
        return match($definition['type']) {
            'integer' => 'INT',
            'varchar' => isset($definition['length']) 
                ? "VARCHAR({$definition['length']})" 
                : "VARCHAR(255)",
            'text' => 'TEXT',
            'timestamp' => 'TIMESTAMP',
            'boolean' => 'TINYINT(1)',
            'decimal' => isset($definition['precision']) && isset($definition['scale'])
                ? "DECIMAL({$definition['precision']},{$definition['scale']})"
                : 'DECIMAL(10,2)',
            'json' => 'JSON',
            default => throw new \Exception("Desteklenmeyen kolon tipi: {$definition['type']}")
        };
    }
} 
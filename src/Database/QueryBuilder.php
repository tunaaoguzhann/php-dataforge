<?php

namespace PhpDataforge\Database;

class QueryBuilder
{
    private string $table;
    private array $wheres = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $joins = [];
    private array $selects = ['*'];
    
    public function __construct(private Connection $connection)
    {
    }

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function select(string|array $columns = '*'): self
    {
        $this->selects = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];
        return $this;
    }

    public function orWhere(string $column, string $operator, mixed $value): self
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'column' => $column,
            'operator' => 'IN',
            'value' => $values,
            'boolean' => 'AND'
        ];
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function get(): array
    {
        $sql = $this->buildSelectQuery();
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute($this->getBindings());
        return $stmt->fetchAll();
    }

    public function first(): ?array
    {
        $this->limit(1);
        $result = $this->get();
        return $result[0] ?? null;
    }

    public function count(): int
    {
        $this->selects = ['COUNT(*) as count'];
        $result = $this->first();
        return (int) ($result['count'] ?? 0);
    }

    public function update(array $data): bool
    {
        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $sets[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s %s',
            $this->table,
            implode(', ', $sets),
            $this->buildWhereClause()
        );

        $stmt = $this->connection->getPdo()->prepare($sql);
        return $stmt->execute(array_merge($bindings, $this->getBindings()));
    }

    public function delete(): bool
    {
        $sql = sprintf(
            'DELETE FROM %s %s',
            $this->table,
            $this->buildWhereClause()
        );

        $stmt = $this->connection->getPdo()->prepare($sql);
        return $stmt->execute($this->getBindings());
    }

    // CREATE ve INSERT işlemleri (mevcut kodlar)
    // ... 

    public function create(string $table, array $columns): bool
    {
        $columnDefinitions = [];
        foreach ($columns as $name => $definition) {
            $columnDefinitions[] = $this->buildColumnDefinition($name, $definition);
        }

        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (%s)',
            $table,
            implode(', ', $columnDefinitions)
        );

        return $this->connection->getPdo()->exec($sql) !== false;
    }

    private function buildColumnDefinition(string $name, array $definition): string
    {
        $type = match($definition['type']) {
            'integer' => 'INTEGER',
            'varchar' => isset($definition['length']) 
                ? "VARCHAR({$definition['length']})" 
                : "VARCHAR(255)",
            'timestamp' => 'TIMESTAMP',
            'text' => 'TEXT',
            'boolean' => 'BOOLEAN',
            'decimal' => 'DECIMAL',
            default => throw new \Exception("Desteklenmeyen kolon tipi: {$definition['type']}")
        };

        $parts = [$name, $type];

        if (isset($definition['nullable']) && !$definition['nullable']) {
            $parts[] = 'NOT NULL';
        }

        if (isset($definition['primaryKey']) && $definition['primaryKey']) {
            $parts[] = 'PRIMARY KEY';
        }

        if (isset($definition['autoIncrement']) && $definition['autoIncrement']) {
            $parts[] = 'AUTO_INCREMENT';
        }

        if (isset($definition['unique']) && $definition['unique']) {
            $parts[] = 'UNIQUE';
        }

        return implode(' ', $parts);
    }

    private function buildSelectQuery(): string
    {
        $query = sprintf(
            'SELECT %s FROM %s',
            implode(', ', $this->selects),
            $this->table
        );

        if (!empty($this->joins)) {
            $query .= ' ' . $this->buildJoinClause();
        }

        if (!empty($this->wheres)) {
            $query .= ' ' . $this->buildWhereClause();
        }

        if (!empty($this->orders)) {
            $query .= ' ' . $this->buildOrderClause();
        }

        if ($this->limit !== null) {
            $query .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $query .= " OFFSET {$this->offset}";
        }

        return $query;
    }

    private function buildJoinClause(): string
    {
        return implode(' ', array_map(function($join) {
            return sprintf(
                '%s JOIN %s ON %s %s %s',
                $join['type'],
                $join['table'],
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }, $this->joins));
    }

    private function buildWhereClause(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $conditions = [];
        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? 'WHERE' : $where['boolean'];
            
            if ($where['operator'] === 'IN') {
                $placeholders = rtrim(str_repeat('?,', count($where['value'])), ',');
                $conditions[] = sprintf(
                    '%s %s IN (%s)',
                    $boolean,
                    $where['column'],
                    $placeholders
                );
            } else {
                $conditions[] = sprintf(
                    '%s %s %s ?',
                    $boolean,
                    $where['column'],
                    $where['operator']
                );
            }
        }

        return implode(' ', $conditions);
    }

    private function buildOrderClause(): string
    {
        return 'ORDER BY ' . implode(', ', array_map(function($order) {
            return "{$order['column']} {$order['direction']}";
        }, $this->orders));
    }

    private function getBindings(): array
    {
        $bindings = [];
        foreach ($this->wheres as $where) {
            if ($where['operator'] === 'IN') {
                $bindings = array_merge($bindings, $where['value']);
            } else {
                $bindings[] = $where['value'];
            }
        }
        return $bindings;
    }

    public function insert(array $data): bool
    {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = rtrim(str_repeat('?,', count($values)), ',');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            $placeholders
        );

        $stmt = $this->connection->getPdo()->prepare($sql);
        return $stmt->execute($values);
    }

    public function quickInsert(...$values): self
    {
        // Tablo sütunlarını al (id ve timestamp sütunları hariç)
        $columns = array_values(array_filter($this->getTableColumns(), function($column) {
            return !in_array($column, ['id', 'created_at', 'updated_at']);
        }));

        // Değerleri ilgili sütunlarla eşleştir
        $data = array_combine($columns, $values);

        // Timestamp değerlerini ekle
        if (in_array('created_at', $this->getTableColumns())) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        $this->insert($data);
        return $this;
    }
    private function getTableColumns(): array
    {
        $sql = "SHOW COLUMNS FROM {$this->table}";
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute();
        $columns = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Auto increment sütununu çıkar (genelde id)
        return array_values(array_filter($columns, function($column) {
            $sql = "SHOW COLUMNS FROM {$this->table} WHERE Field = ?";
            $stmt = $this->connection->getPdo()->prepare($sql);
            $stmt->execute([$column]);
            $info = $stmt->fetch(\PDO::FETCH_ASSOC);
            return !str_contains(strtoupper($info['Extra']), 'AUTO_INCREMENT');
        }));
    }
    public function insertMultiple(array $records): bool
    {
        if (empty($records)) {
            return false;
        }

        $columns = array_keys($records[0]);
        $placeholders = '(' . rtrim(str_repeat('?,', count($columns)), ',') . ')';
        $allPlaceholders = rtrim(str_repeat($placeholders . ',', count($records)), ',');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $this->table,
            implode(', ', $columns),
            $allPlaceholders
        );

        $values = [];
        foreach ($records as $record) {
            foreach ($columns as $column) {
                $values[] = $record[$column] ?? null;
            }
        }

        try {
            $stmt = $this->connection->getPdo()->prepare($sql);
            return $stmt->execute($values);
        } catch (\PDOException $e) {
            throw new \Exception("Toplu veri eklerken hata: " . $e->getMessage());
        }
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function raw(string $sql, array $bindings = []): bool
    {
        $stmt = $this->connection->getPdo()->prepare($sql);
        return $stmt->execute($bindings);
    }
} 
<?php

namespace PhpDataforge\Migration;

class Schema
{
    private array $structure = [];
    private array $modifications = [];
    private string $currentColumn;
    
    public function __construct(private string $tableName)
    {
    }


    public function integer(string $columnName): self
    {
        $this->currentColumn = $columnName;
        $this->structure[$columnName] = [
            'type' => 'integer',
            'nullable' => false
        ];
        return $this;
    }

    public function string(string $columnName, int $length = 255): self
    {
        $this->currentColumn = $columnName;
        $this->structure[$columnName] = [
            'type' => 'varchar',
            'length' => $length,
            'nullable' => false
        ];
        return $this;
    }

    public function text(string $columnName): self
    {
        $this->currentColumn = $columnName;
        $this->structure[$columnName] = [
            'type' => 'text',
            'nullable' => false
        ];
        return $this;
    }

    public function timestamp(string $columnName): self
    {
        $this->currentColumn = $columnName;
        $this->structure[$columnName] = [
            'type' => 'timestamp',
            'nullable' => false
        ];
        return $this;
    }

    public function datetime(string $columnName): self
    {
        $this->currentColumn = $columnName;
        $this->structure[$columnName] = [
            'type' => 'datetime',
            'nullable' => false
        ];
        return $this;
    }

    public function date(string $columnName): self
    {
        $this->currentColumn = $columnName;
        $this->structure[$columnName] = [
            'type' => 'date',
            'nullable' => false
        ];
        return $this;
    }

    public function decimal(string $columnName, int $precision = 10, int $scale = 2): self
    {
        $this->currentColumn = $columnName;
        $this->structure[$columnName] = [
            'type' => 'decimal',
            'precision' => $precision,
            'scale' => $scale,
            'nullable' => false
        ];
        return $this;
    }

    public function boolean(string $columnName): self
    {
        $this->currentColumn = $columnName;
        $this->structure[$columnName] = [
            'type' => 'boolean',
            'nullable' => false
        ];
        return $this;
    }

    public function json(string $columnName): self
    {
        $this->currentColumn = $columnName;
        $this->structure[$columnName] = [
            'type' => 'json',
            'nullable' => false
        ];
        return $this;
    }

    public function enum(string $columnName, array $values): self
    {
        $this->currentColumn = $columnName;
        $this->structure[$columnName] = [
            'type' => 'enum',
            'values' => $values,
            'nullable' => false
        ];
        return $this;
    }

    // Kolon modifikasyon metodları
    public function after(string $columnName): self
    {
        $this->modifications[] = [
            'type' => 'add',
            'column' => $this->currentColumn,
            'definition' => $this->structure[$this->currentColumn],
            'after' => $columnName
        ];
        
        unset($this->structure[$this->currentColumn]);
        
        return $this;
    }

    public function change(): self
    {
        $this->modifications[] = [
            'type' => 'change',
            'column' => $this->currentColumn,
            'definition' => $this->structure[$this->currentColumn]
        ];
        return $this;
    }

    public function dropColumn(string $columnName): self
    {
        $this->modifications[] = [
            'type' => 'drop',
            'column' => $columnName
        ];
        return $this;
    }

    public function renameColumn(string $from, string $to): self
    {
        $this->modifications[] = [
            'type' => 'rename',
            'from' => $from,
            'to' => $to
        ];
        return $this;
    }

    // İndeks metodları
    public function index(string|array $columns): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $this->modifications[] = [
            'type' => 'index',
            'columns' => $columns
        ];
        return $this;
    }

    public function nullable(): self
    {
        $this->structure[$this->currentColumn]['nullable'] = true;
        return $this;
    }

    public function unique(): self
    {
        $this->structure[$this->currentColumn]['unique'] = true;
        return $this;
    }

    public function default($value): self
    {
        $this->structure[$this->currentColumn]['default'] = $value;
        return $this;
    }

    public function primaryKey(): self
    {
        $this->structure[$this->currentColumn]['primaryKey'] = true;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->structure[$this->currentColumn]['autoIncrement'] = true;
        return $this;
    }

    public function foreignKey(string $referenceTable, string $referenceColumn = 'id'): self
    {
        $this->structure[$this->currentColumn]['foreign'] = [
            'table' => $referenceTable,
            'column' => $referenceColumn
        ];
        return $this;
    }

    public function getStructure(): array
    {
        return $this->structure;
    }

    public function getModifications(): array
    {
        return $this->modifications;
    }
} 
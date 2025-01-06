<?php

namespace PhpDataforge\Database;

class Connection
{
    private \PDO $pdo;
    private string $driver;

    public function __construct(array $config)
    {
        $this->driver = $config['driver'] ?? 'mysql';
        $dsn = $this->createDsn($config);
        
        try {
            $this->pdo = new \PDO(
                $dsn,
                $config['user'] ?? null,
                $config['pass'] ?? null,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
                ]
            );
        } catch (\PDOException $e) {
            throw new \Exception("Veritabanı bağlantısı başarısız: " . $e->getMessage());
        }
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this);
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    private function createDsn(array $config): string
    {
        return match($this->driver) {
            'mysql' => sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['name']
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;dbname=%s',
                $config['host'],
                $config['name']
            ),
            'sqlite' => sprintf('sqlite:%s', $config['name']),
            'sqlsrv' => sprintf(
                'sqlsrv:Server=%s;Database=%s',
                $config['host'],
                $config['name']
            ),
            default => throw new \Exception("Desteklenmeyen veritabanı sürücüsü: {$this->driver}")
        };
    }
} 
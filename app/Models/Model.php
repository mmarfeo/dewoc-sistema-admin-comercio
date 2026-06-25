<?php
abstract class Model
{
    protected PDO $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── CRUD genérico ────────────────────────────────────────────────────────

    public function findAll(string $orderBy = ''): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($orderBy) $sql .= " ORDER BY {$orderBy}";
        return $this->db->query($sql)->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findWhere(array $conditions, string $orderBy = ''): array
    {
        $where  = implode(' AND ', array_map(fn($k) => "{$k} = :{$k}", array_keys($conditions)));
        $sql    = "SELECT * FROM {$this->table} WHERE {$where}";
        if ($orderBy) $sql .= " ORDER BY {$orderBy}";
        $stmt   = $this->db->prepare($sql);
        $stmt->execute($conditions);
        return $stmt->fetchAll();
    }

    public function findOneWhere(array $conditions): array|false
    {
        $rows = $this->findWhere($conditions);
        return $rows[0] ?? false;
    }

    public function insert(array $data): int
    {
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
        $stmt   = $this->db->prepare("INSERT INTO {$this->table} ({$cols}) VALUES ({$places})");
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sets   = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $stmt   = $this->db->prepare("UPDATE {$this->table} SET {$sets}, updated_at = NOW() WHERE {$this->primaryKey} = :_pk_id");
        $params = $data;
        $params[':_pk_id'] = $id;
        return $stmt->execute($params);
    }

    /** Baja lógica (campo 'eliminado' = 1) */
    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET eliminado = 1, updated_at = NOW() WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }

    public function count(array $conditions = []): int
    {
        if (empty($conditions)) {
            return (int)$this->db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
        }
        $where = implode(' AND ', array_map(fn($k) => "{$k} = :{$k}", array_keys($conditions)));
        $stmt  = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where}");
        $stmt->execute($conditions);
        return (int)$stmt->fetchColumn();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    protected function queryAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    protected function queryOne(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    protected function queryScalar(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }
}

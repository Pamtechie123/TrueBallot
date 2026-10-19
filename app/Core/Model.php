<?php
declare(strict_types=1);

namespace App\Core;

use PDOStatement;

/**
 * ABSTRACT base for every model. Each model object represents one row of one table.
 * Child classes only say which table they use (and add their own methods).
 */
abstract class Model
{
    /** Table name. Every child model must set this. */
    protected static string $table = '';

    public function __construct(protected array $attributes = [])
    {
    }

    public function id(): int
    {
        return (int) ($this->attributes['id'] ?? 0);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public static function find(int $id): ?Model
    {
        $row = static::run('SELECT * FROM `' . static::$table . '` WHERE id = ? LIMIT 1', [$id])->fetch();
        return $row ? static::hydrate($row) : null;
    }

    /** @return Model[] */
    public static function all(): array
    {
        $rows = static::run('SELECT * FROM `' . static::$table . '` ORDER BY id')->fetchAll();
        return array_map(fn (array $row) => static::hydrate($row), $rows);
    }

    /** Turn a database row into a model object. Children may choose a different class. */
    protected static function hydrate(array $row): Model
    {
        return new static($row);
    }

    /** Run a prepared statement (values are never placed directly in the SQL). */
    protected static function run(string $sql, array $params = []): PDOStatement
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }
}

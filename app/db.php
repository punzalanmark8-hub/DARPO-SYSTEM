<?php
function cfg(): array { static $c; return $c ??= require __DIR__ . '/config.php'; }

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = cfg()['db'];
        try {
            $pdo = new PDO("mysql:host={$c['host']};port=" . ($c['port'] ?? 3306) . ";dbname={$c['name']};charset={$c['charset']}", $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException("Cannot connect to database '{$c['name']}' on {$c['host']}:" . ($c['port'] ?? 3306) . ". Check the user and password in app/config.php, and make sure database/schema.sql was imported. (" . $e->getMessage() . ')');
        }
    }
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function one(string $sql, array $p = []): ?array { return q($sql, $p)->fetch() ?: null; }
function val(string $sql, array $p = []) { return q($sql, $p)->fetchColumn(); }
function insert(string $table, array $data): int {
    $cols = implode(',', array_keys($data));
    $ph = implode(',', array_fill(0, count($data), '?'));
    q("INSERT INTO $table($cols) VALUES($ph)", array_values($data));
    return (int)db()->lastInsertId();
}
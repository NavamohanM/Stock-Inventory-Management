<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

function get_db(): mysqli {
    static $conn = null;
    if ($conn !== null) return $conn;

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);

    if ($conn->connect_error) {
        error_log('DB Connection failed: ' . $conn->connect_error);
        die(json_encode(['error' => 'Database connection failed. Please try again later.']));
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

// Safe query with prepared statements
// Usage: db_query("SELECT * FROM users WHERE id = ?", "i", [$id])
function db_query(string $sql, string $types = '', array $params = []): mysqli_result|bool {
    $conn = get_db();
    if (empty($params)) {
        return $conn->query($sql);
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Prepare failed: ' . $conn->error . ' | SQL: ' . $sql);
        return false;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result ?: true;
}

// Returns all rows as associative array
function db_fetch_all(string $sql, string $types = '', array $params = []): array {
    $result = db_query($sql, $types, $params);
    if (!$result || $result === true) return [];
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

// Returns single row
function db_fetch_one(string $sql, string $types = '', array $params = []): ?array {
    $result = db_query($sql, $types, $params);
    if (!$result || $result === true) return null;
    return $result->fetch_assoc() ?: null;
}

// Returns last insert id
function db_insert(string $sql, string $types = '', array $params = []): int|false {
    $conn = get_db();
    if (empty($params)) {
        $conn->query($sql);
        return $conn->insert_id ?: false;
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
}

// Returns affected rows count
function db_execute(string $sql, string $types = '', array $params = []): int {
    $conn = get_db();
    if (empty($params)) {
        $conn->query($sql);
        return $conn->affected_rows;
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

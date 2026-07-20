<?php
header('Content-Type: application/json');

$DB_HOST = getenv('DB_HOST') ?: 'y11ovnrne4yk4p9zbhe39tti';
$DB_USER = getenv('DB_USER') ?: 'mysql';
$DB_PASS = getenv('DB_PASS') ?: '23ns613Dyo1vgiAOQCt2ABFZzujOsxuyROvqNk4unUoZxWpwN9nIPrMNTt4QFkzG';

try {
    $pdo = new PDO("mysql:host=$DB_HOST", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `demos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Created database 'demos'\n";

    $pdo->exec("USE `demo`");
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($tables) . " tables in 'demo'\n";

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $createStmt = $row[1];

        $pdo->exec("USE `demos`");
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        $pdo->exec($createStmt);

        $pdo->exec("INSERT INTO `demos`.`$table` SELECT * FROM `demo`.`$table`");
        echo "Copied: $table\n";
    }

    echo json_encode(['status' => 'ok', 'tables' => count($tables)]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

<?php
header('Content-Type: application/json');

$DB_HOST = getenv('DB_HOST') ?: 'y11ovnrne4yk4p9zbhe39tti';
$DB_USER = 'root';
$DB_PASS = getenv('DB_ROOT_PASS') ?: 'Q4WzNdQFT4aREwZ6GdlDOLe0pAaRdqP64Sq4zsVDqxshq2aBIJIvuX0kMeGkUYRO';

try {
    $pdo = new PDO("mysql:host=$DB_HOST", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `demos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $tables = $pdo->query("SHOW TABLES FROM `demo`")->fetchAll(PDO::FETCH_COLUMN);
    $results = [];

    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `demos`.`$table`");

        $cols = $pdo->query("SHOW COLUMNS FROM `demo`.`$table`")->fetchAll(PDO::FETCH_ASSOC);
        $nonGenerated = array_filter($cols, fn($c) => $c['Extra'] !== 'STORED GENERATED');
        $colNames = array_map(fn($c) => '`' . $c['Field'] . '`', $nonGenerated);
        $colList = implode(', ', $colNames);

        $create = $pdo->query("SHOW CREATE TABLE `demo`.`$table`")->fetch(PDO::FETCH_NUM);
        $createSQL = $create[1];

        $pdo->exec($createSQL);
        $pdo->exec("INSERT INTO `demos`.`$table` ($colList) SELECT $colList FROM `demo`.`$table`");
        $count = $pdo->query("SELECT COUNT(*) FROM `demos`.`$table`")->fetchColumn();
        $results[] = "$table ($count rows)";
    }

    echo json_encode(['status' => 'ok', 'database' => 'demos', 'tables' => $results]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

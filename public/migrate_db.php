<?php
header('Content-Type: application/json');

$DB_HOST = getenv('DB_HOST') ?: 'y11ovnrne4yk4p9zbhe39tti';
$DB_USER = 'root';
$DB_PASS = getenv('DB_ROOT_PASS') ?: 'Q4WzNdQFT4aREwZ6GdlDOLe0pAaRdqP64Sq4zsVDqxshq2aBIJIvuX0kMeGkUYRO';

try {
    $pdo = new PDO("mysql:host=$DB_HOST", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `demos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `demo`");

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $results = [];

    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `demos`.`$table`");

        $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $nonGenerated = array_filter($cols, fn($c) => strpos($c['Extra'], 'STORED') === false);
        $colNames = array_map(fn($c) => '`' . $c['Field'] . '`', $nonGenerated);
        $colList = implode(', ', $colNames);

        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $createSQL = $create[1];

        $pdo->exec("USE `demos`");
        $pdo->exec($createSQL);
        $pdo->exec("INSERT INTO `$table` ($colList) SELECT $colList FROM `demo`.`$table`");
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $pdo->exec("USE `demo`");
        $results[] = "$table ($count rows)";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    $pdo->exec("GRANT ALL PRIVILEGES ON `demos`.* TO 'mysql'@'%'");
    $pdo->exec("FLUSH PRIVILEGES");

    echo json_encode(['status' => 'ok', 'database' => 'demos', 'tables' => $results, 'grant' => 'mysql@% -> demos']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

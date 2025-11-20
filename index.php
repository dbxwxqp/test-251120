<?php

$word = mb_strtolower(trim($_GET['q']), 'UTF-8') ?? '';


#< db
$host = 'localhost';
$db = 'db';
$user = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к БД: ' . $e->getMessage()]);
    exit;
}
#> db

#< search word
$stmt = $pdo->prepare("SELECT Word, Length, AccentPosition FROM Words WHERE Word = ?");
$stmt->execute([$word]);
$response = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$response) {
    http_response_code(404);
    echo json_encode(['error' => 'Слово не найдено в базе данных']);
    exit;
} else {
    $length = (int)$response['Length'];
    $accentPos = (int)$response['AccentPosition'];
}
#> search word

#< условия
if ($accentPos == $length) { // ..."должны совпадать предыдущая буква и ударная, если ударная гласная последняя"...
    $lastChar = mb_substr($response['Word'], -1, 1, 'UTF-8');
    $prevChar = mb_substr($response['Word'], -2, 1, 'UTF-8');

    $pattern = '%' . $prevChar . $lastChar;
    $sql = "SELECT Word FROM Words WHERE Word LIKE ? AND Word != ? AND Length >= 2 AND AccentPosition = Length";
    $params = [$pattern, $word];
} else { // ..."окончания слов должны полностью совпадать, начиная с ударной гласной, если ударная гласная не последняя"...
    $suffix = mb_substr($response['Word'], $accentPos - 1, null, 'UTF-8');
    $pattern = '%' . $suffix;

    $sql = "SELECT Word FROM Words WHERE Word LIKE ? AND Word != ? AND AccentPosition = ?";
    $params = [$pattern, $word, $accentPos];
}
#> условия

#< result
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rhymes = $stmt->fetchAll(PDO::FETCH_COLUMN, 0); //all поскольку "ХОТЯБЫ одной"

http_response_code(200);
echo json_encode([
    'word' => $word,
    'accentPosition' => $accentPos,
    'rhymes' => $rhymes,
    'count' => count($rhymes)
]);
#> result

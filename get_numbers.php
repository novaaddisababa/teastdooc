<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['game_id'])) {
    echo json_encode([]);
    exit();
}

$game_id = intval($_GET['game_id']);
$db = new Database();
$conn = $db->getConnection();

// Get game max players
$stmt = $conn->prepare("SELECT max_players FROM games WHERE id = ?");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    echo json_encode([]);
    exit();
}

$max_players = $game['max_players'];
$all_numbers = range(1, $max_players);

// Get taken numbers
$stmt = $conn->prepare("SELECT lucky_number FROM game_players WHERE game_id = ?");
$stmt->execute([$game_id]);
$taken_numbers = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

$available_numbers = array_diff($all_numbers, $taken_numbers);
echo json_encode(array_values($available_numbers));
?>
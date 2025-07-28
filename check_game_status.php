<?php
require_once 'db_connect.php';
require_once 'security.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $game_id = filter_var($_GET['game_id'], FILTER_VALIDATE_INT);
    if ($game_id === false) {
        throw new Exception("Invalid game ID");
    }
    
    $stmt = $conn->prepare("
        SELECT g.status, 
               COUNT(gp.id) as current_players,
               g.max_players
        FROM games g
        LEFT JOIN game_players gp ON g.id = gp.game_id
        WHERE g.id = ?
        GROUP BY g.id
    ");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        throw new Exception("Game not found");
    }
    
    echo json_encode([
        'status' => $game['status'],
        'current_players' => (int)$game['current_players'],
        'max_players' => (int)$game['max_players'],
        'can_join' => ($game['status'] === 'waiting' && 
                      $game['current_players'] < $game['max_players'])
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
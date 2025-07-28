<?php
require_once 'db_connect.php';
require_once 'security.php';

// Initialize secure session
Security::secureSessionStart();

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Generate CSRF token for forms
$csrf_token = Security::generateCSRFToken();

// Handle form submissions with CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception("Invalid CSRF token");
        }

        $action = Security::sanitizeInput($_POST['action'] ?? '');
        
        switch ($action) {
            case 'login':
                handleLogin();
                break;
            case 'register':
                handleRegistration();
                break;
            case 'create_game':
                handleGameCreation();
                break;
            case 'join_game':
                handleGameJoin();
                break;
            case 'draw_winner':
                handleDrawWinner();
                break;
            case 'cancel_game':
                handleCancelGame();
                break;
            case 'deposit':
                handleDeposit();
                break;
            case 'withdraw':
                handleWithdraw();
                break;
            default:
                throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        error_log("Action error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header("Location: index.php");
        exit();
    }
}

// Login handler with rate limiting
function handleLogin() {
    global $conn;
    
    if (!Security::checkRateLimit('login')) {
        throw new Exception("Too many login attempts. Please try again later.");
    }
    
    $username = Security::sanitizeInput($_POST['username']);
    $password = $_POST['password']; // Don't sanitize passwords
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Successful login - reset rate limit
        unset($_SESSION['rate_limit_login']);
        
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['balance'] = $user['balance'];
        $_SESSION['last_activity'] = time();
        
        // Log login
        $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], 'login', $_SERVER['REMOTE_ADDR']]);
        
        header("Location: index.php");
        exit();
    } else {
        throw new Exception("Invalid username or password");
    }
}

// Registration handler with password complexity check
function handleRegistration() {
    global $conn;
    
    $username = Security::sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate password
    $passwordCheck = Security::validatePassword($password);
    if ($passwordCheck !== true) {
        throw new Exception($passwordCheck);
    }
    
    if ($password !== $confirm_password) {
        throw new Exception("Passwords don't match");
    }
    
    // Check if username exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        throw new Exception("Username already taken");
    }
    
    // Create new user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->execute([$username, $hashed_password]);
    
    // Log registration
    $user_id = $conn->lastInsertId();
    $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, 'register', $_SERVER['REMOTE_ADDR']]);
    
    $_SESSION['success'] = "Registration successful! Please login.";
    header("Location: index.php");
    exit();
}

// Game creation with validation
function handleGameCreation() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("You must be logged in to create a game");
    }
    
    $title = Security::sanitizeInput($_POST['title']);
    $bet_amount = filter_var($_POST['bet_amount'], FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.01, 'max_range' => 1000]]);
    $max_players = filter_var($_POST['max_players'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 2, 'max_range' => 100]]);
    $lucky_number = filter_var($_POST['lucky_number'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max_players]]);
    
    if ($bet_amount === false || $max_players === false || $lucky_number === false) {
        throw new Exception("Invalid game parameters");
    }
    
    // Check user balance
    if ($_SESSION['balance'] < $bet_amount) {
        throw new Exception("Insufficient balance to create game");
    }
    
    try {
        $conn->beginTransaction();
        
        // Create the game
        $stmt = $conn->prepare("INSERT INTO games (title, created_by, bet_amount, max_players) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $_SESSION['user_id'], $bet_amount, $max_players]);
        $game_id = $conn->lastInsertId();
        
        // Add creator as first player
        $stmt = $conn->prepare("INSERT INTO game_players (game_id, user_id, lucky_number) VALUES (?, ?, ?)");
        $stmt->execute([$game_id, $_SESSION['user_id'], $lucky_number]);
        
        // Deduct bet amount from creator
        $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$bet_amount, $_SESSION['user_id']]);
        
        // Record transaction
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, game_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], -$bet_amount, 'game_creation', $game_id]);
        
        // Update session balance
        $_SESSION['balance'] -= $bet_amount;
        
        // Log game creation
        $stmt = $conn->prepare("INSERT INTO game_logs (game_id, user_id, action) VALUES (?, ?, ?)");
        $stmt->execute([$game_id, $_SESSION['user_id'], 'create']);
        
        $conn->commit();
        $_SESSION['success'] = "Game created successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Game creation error: " . $e->getMessage());
        throw new Exception("Error creating game: " . $e->getMessage());
    }
    
    header("Location: index.php");
    exit();
}

// Join game with proper validation
function handleGameJoin() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("You must be logged in to join a game");
    }
    
    $game_id = filter_var($_POST['game_id'], FILTER_VALIDATE_INT);
    $lucky_number = filter_var($_POST['lucky_number'], FILTER_VALIDATE_INT);
    
    if ($game_id === false || $lucky_number === false) {
        throw new Exception("Invalid game parameters");
    }
    
    try {
        $conn->beginTransaction();
        
        // Get game details with lock and include status check
        $stmt = $conn->prepare("
            SELECT g.*, 
                   (SELECT COUNT(*) FROM game_players WHERE game_id = g.id) as current_players
            FROM games g 
            WHERE g.id = ? AND g.status = 'waiting'
            FOR UPDATE
        ");
        $stmt->execute([$game_id]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$game) {
            // More specific error message
            $stmt = $conn->prepare("SELECT status FROM games WHERE id = ?");
            $stmt->execute([$game_id]);
            $status = $stmt->fetchColumn();
            
            if ($status === false) {
                throw new Exception("Game not found");
            } elseif ($status === 'started') {
                throw new Exception("Game has already started");
            } elseif ($status === 'completed') {
                throw new Exception("Game has already completed");
            } elseif ($status === 'canceled') {
                throw new Exception("Game was canceled");
            } else {
                throw new Exception("Game not available for joining");
            }
        }
        
        // Check if user is already in this game
        $stmt = $conn->prepare("SELECT id FROM game_players WHERE game_id = ? AND user_id = ?");
        $stmt->execute([$game_id, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("You're already in this game");
        }
        
        // Check if number is taken
        $stmt = $conn->prepare("SELECT id FROM game_players WHERE game_id = ? AND lucky_number = ?");
        $stmt->execute([$game_id, $lucky_number]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Number already taken");
        }
        
        // Validate number range
        if ($lucky_number < 1 || $lucky_number > $game['max_players']) {
            throw new Exception("Number must be between 1 and " . $game['max_players']);
        }
        
        // Check user balance
        if ($_SESSION['balance'] < $game['bet_amount']) {
            throw new Exception("Insufficient balance to join game");
        }
        
        // Add player to game
        $stmt = $conn->prepare("INSERT INTO game_players (game_id, user_id, lucky_number) VALUES (?, ?, ?)");
        $stmt->execute([$game_id, $_SESSION['user_id'], $lucky_number]);
        
        // Deduct bet amount
        $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$game['bet_amount'], $_SESSION['user_id']]);
        
        // Record transaction
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, game_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], -$game['bet_amount'], 'game_join', $game_id]);
        
        // Update session balance
        $_SESSION['balance'] -= $game['bet_amount'];
        
        // Log game join
        $stmt = $conn->prepare("INSERT INTO game_logs (game_id, user_id, action) VALUES (?, ?, ?)");
        $stmt->execute([$game_id, $_SESSION['user_id'], 'join']);
        
        // Check if game is now full
        $stmt = $conn->prepare("SELECT COUNT(*) as player_count FROM game_players WHERE game_id = ?");
        $stmt->execute([$game_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['player_count'] >= $game['max_players']) {
            // Update game status to started
            $stmt = $conn->prepare("UPDATE games SET status = 'started' WHERE id = ?");
            $stmt->execute([$game_id]);
            
            // Log game start
            $stmt = $conn->prepare("INSERT INTO game_logs (game_id, user_id, action) VALUES (?, ?, ?)");
            $stmt->execute([$game_id, null, 'game_full']);
            
            // Automatically draw winner
            if (!autoDrawWinner($game_id)) {
                throw new Exception("Failed to draw winner automatically.");
            }
        }
        
        if (!$inTransaction) {
            $conn->commit();
        }
        $_SESSION['success'] = "Successfully joined the game!";
    } catch (Exception $e) {
        if (!$inTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Game join error: " . $e->getMessage());
        throw $e;
    }
    
    header("Location: index.php");
    exit();
}

// Cancel game with proper validation
function handleCancelGame() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("You must be logged in to cancel a game");
    }
    
    $game_id = filter_var($_POST['game_id'], FILTER_VALIDATE_INT);
    if ($game_id === false) {
        throw new Exception("Invalid game ID");
    }
    
    try {
        $conn->beginTransaction();
        
        // Lock and fetch the game
        $stmt = $conn->prepare("SELECT * FROM games WHERE id = ? FOR UPDATE");
        $stmt->execute([$game_id]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$game) {
            throw new Exception("Game not found");
        }
        
        if ($game['created_by'] != $_SESSION['user_id']) {
            throw new Exception("Only the creator can cancel this game");
        }
        
        if ($game['status'] === 'completed' || $game['status'] === 'canceled') {
            throw new Exception("Game already completed or canceled");
        }
        
        // Get all players and refund
        $stmt = $conn->prepare("SELECT user_id FROM game_players WHERE game_id = ?");
        $stmt->execute([$game_id]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($players as $player) {
            $stmt2 = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt2->execute([$game['bet_amount'], $player['user_id']]);
            
            // Record refund transaction
            $stmt3 = $conn->prepare("INSERT INTO transactions (user_id, amount, type, game_id) VALUES (?, ?, ?, ?)");
            $stmt3->execute([$player['user_id'], $game['bet_amount'], 'game_refund', $game_id]);
            
            // Update session balance if current user
            if ($player['user_id'] == $_SESSION['user_id']) {
                $_SESSION['balance'] += $game['bet_amount'];
            }
        }
        
        // Set game status to canceled
        $stmt = $conn->prepare("UPDATE games SET status = 'canceled' WHERE id = ?");
        $stmt->execute([$game_id]);
        
        // Log game cancellation
        $stmt = $conn->prepare("INSERT INTO game_logs (game_id, user_id, action) VALUES (?, ?, ?)");
        $stmt->execute([$game_id, $_SESSION['user_id'], 'cancel']);
        
        $conn->commit();
        $_SESSION['success'] = "Game canceled and all bets refunded.";
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Game cancel error: " . $e->getMessage());
        throw new Exception("Error canceling game: " . $e->getMessage());
    }
    
    header("Location: index.php");
    exit();
}

// Draw winner securely
function autoDrawWinner($game_id) {
    global $conn;
    
    try {
        // Check if we're already in a transaction
        $inTransaction = $conn->inTransaction();
        
        if (!$inTransaction) {
            $conn->beginTransaction();
        }
        
        // Lock game row
        $stmt = $conn->prepare("SELECT * FROM games WHERE id = ? FOR UPDATE");
        $stmt->execute([$game_id]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$game || $game['status'] !== 'started') {
            throw new Exception("Game not available for drawing");
        }
        
        // Get all players and their numbers
        $stmt = $conn->prepare("SELECT * FROM game_players WHERE game_id = ?");
        $stmt->execute([$game_id]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($players) < $game['max_players']) {
            throw new Exception("Not enough players to draw winner");
        }
        
        // Generate random winning number using cryptographically secure function
        $winning_number = random_int(1, $game['max_players']);
        
        // Find winning player
        $winner = null;
        foreach ($players as $player) {
            if ($player['lucky_number'] == $winning_number) {
                $winner = $player;
                break;
            }
        }
        
        if (!$winner) {
            throw new Exception("No winner found for the drawn number");
        }
        
        // Calculate total win
        $total_win = $game['bet_amount'] * $game['max_players'];
        
        // Update winner's balance
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$total_win, $winner['user_id']]);
        
        // Record winning transaction
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, game_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$winner['user_id'], $total_win, 'game_win', $game_id]);
        
        // Record game result
        $stmt = $conn->prepare("INSERT INTO game_results (game_id, winning_number, winning_user_id, total_win) VALUES (?, ?, ?, ?)");
        $stmt->execute([$game_id, $winning_number, $winner['user_id'], $total_win]);
        
        // Update game status to completed
        $stmt = $conn->prepare("UPDATE games SET status = 'completed' WHERE id = ?");
        $stmt->execute([$game_id]);
        
        // Log game completion
        $stmt = $conn->prepare("INSERT INTO game_logs (game_id, user_id, action) VALUES (?, ?, ?)");
        $stmt->execute([$game_id, $winner['user_id'], 'win']);
        
        if (!$inTransaction) {
            $conn->commit();
        }
        return true;
    } catch (Exception $e) {
        if (!$inTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Auto draw winner error: " . $e->getMessage());
        return false;
    }
}

// Handle manual winner drawing
function handleDrawWinner() {
    global $conn;
    
    // Add a small delay to match the progress bar
    sleep(1000); // This matches the 10-second progress bar
    
    $game_id = filter_var($_POST['game_id'], FILTER_VALIDATE_INT);
    if ($game_id === false) {
        throw new Exception("Invalid game ID");
    }
    
    try {
        $conn->beginTransaction();
        $success = autoDrawWinner($game_id);
        
        if ($success) {
            $conn->commit();
            $_SESSION['success'] = "Winner drawn successfully!";
        } else {
            $conn->rollBack();
            $_SESSION['error'] = "Could not draw winner (game may not be full or already completed).";
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Draw winner error: " . $e->getMessage());
        $_SESSION['error'] = "Error drawing winner: " . $e->getMessage();
    }
    
    header("Location: index.php");
    exit();
}

// Handle deposit with Chapa payment gateway
function handleDeposit() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("You must be logged in to make a deposit");
    }
    
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 1, 'max_range' => 50000]]);
    if ($amount === false || !ChapaConfig::validateAmount($amount)) {
        throw new Exception("Invalid deposit amount. Amount must be between 1 and 50,000 ETB");
    }
    
    // Get user information
    $stmt = $conn->prepare("SELECT email, username, phone FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    try {
        $conn->beginTransaction();
        
        // Generate unique transaction reference
        $transactionRef = ChapaConfig::generateTransactionRef('DEP');
        
        // Store transaction in database
        $stmt = $conn->prepare("INSERT INTO chapa_transactions (user_id, transaction_ref, amount, type, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $transactionRef, $amount, 'deposit', 'pending']);
        
        // Initialize Chapa payment
        $chapa = ChapaConfig::getChapa();
        
        // Prepare payment data using setter methods
        $postData = new \Chapa\Model\PostData();
        $postData->setAmount(ChapaConfig::formatAmount($amount));
        $postData->setCurrency('ETB');
        $postData->setEmail($user['email']);
        $postData->setFirstName($user['username']);
        $postData->setLastName('User');
        $postData->setPhoneNumber($user['phone'] ?? '');
        $postData->setTxRef($transactionRef);
        $postData->setCallbackUrl(ChapaConfig::getCallbackUrl());
        $postData->setReturnUrl(ChapaConfig::getReturnUrl());
        $postData->setCustomization([
            'title' => 'Toady Game Deposit',
            'description' => 'Deposit funds to your Toady Game account'
        ]);
        
        // Initialize payment with Chapa
        $response = $chapa->initializePayment($postData);
        
        if ($response->getStatus() === 'success') {
            $responseData = $response->getData();
            $checkoutUrl = $responseData['checkout_url'];
            
            // Update transaction with Chapa response
            $stmt = $conn->prepare("UPDATE chapa_transactions SET chapa_response = ?, updated_at = NOW() WHERE transaction_ref = ?");
            $stmt->execute([json_encode($responseData), $transactionRef]);
            
            // Log deposit attempt
            $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'], 
                'deposit_initiated', 
                $_SERVER['REMOTE_ADDR'],
                json_encode(['transaction_ref' => $transactionRef, 'amount' => $amount])
            ]);
            
            $conn->commit();
            
            // Redirect to Chapa checkout
            header("Location: " . $checkoutUrl);
            exit();
            
        } else {
            throw new Exception("Failed to initialize payment with Chapa: " . ($response->getMessage() ?? 'Unknown error'));
        }
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Deposit error: " . $e->getMessage());
        
        // Update transaction status if it exists
        if (isset($transactionRef)) {
            try {
                $stmt = $conn->prepare("UPDATE chapa_transactions SET status = 'failed', error_message = ?, updated_at = NOW() WHERE transaction_ref = ?");
                $stmt->execute([$e->getMessage(), $transactionRef]);
            } catch (Exception $dbError) {
                error_log("Failed to update transaction status: " . $dbError->getMessage());
            }
        }
        
        throw new Exception("Error processing deposit: " . $e->getMessage());
    }
}

// Handle withdrawal with Chapa payment gateway
function handleWithdraw() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("You must be logged in to withdraw");
    }
    
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 1, 'max_range' => 50000]]);
    if ($amount === false || !ChapaConfig::validateAmount($amount)) {
        throw new Exception("Invalid withdrawal amount. Amount must be between 1 and 50,000 ETB");
    }
    
    // Check user balance
    if ($_SESSION['balance'] < $amount) {
        throw new Exception("Insufficient balance for withdrawal");
    }
    
    // Get user information including withdrawal details
    $stmt = $conn->prepare("SELECT email, username, phone FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Get withdrawal method and account details from form
    $withdrawMethod = Security::sanitizeInput($_POST['withdraw_method'] ?? '');
    $accountNumber = Security::sanitizeInput($_POST['account_number'] ?? '');
    $bankCode = Security::sanitizeInput($_POST['bank_code'] ?? '');
    
    if (empty($withdrawMethod) || empty($accountNumber)) {
        throw new Exception("Withdrawal method and account details are required");
    }
    
    try {
        $conn->beginTransaction();
        
        // Generate unique transaction reference
        $transactionRef = ChapaConfig::generateTransactionRef('WTH');
        
        // Deduct amount from user balance first (security measure)
        $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
        $stmt->execute([$amount, $_SESSION['user_id'], $amount]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Insufficient balance or user not found");
        }
        
        // Update session balance
        $_SESSION['balance'] -= $amount;
        
        // Store withdrawal request in database
        $stmt = $conn->prepare("INSERT INTO chapa_transactions (user_id, transaction_ref, amount, type, status, withdrawal_method, account_number, bank_code, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $transactionRef, $amount, 'withdraw', 'pending', $withdrawMethod, $accountNumber, $bankCode]);
        
        // For withdrawals, we'll use Chapa's transfer API or create a manual process
        // Since Chapa primarily handles incoming payments, withdrawals typically require manual processing
        // or integration with bank transfer APIs
        
        if ($withdrawMethod === 'bank_transfer') {
            // Process bank transfer withdrawal
            $withdrawalData = [
                'transaction_ref' => $transactionRef,
                'amount' => $amount,
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'user_email' => $user['email'],
                'user_name' => $user['username']
            ];
            
            // In a real implementation, you would integrate with a bank transfer API
            // For now, we'll mark it as pending manual processing
            $stmt = $conn->prepare("UPDATE chapa_transactions SET status = 'processing', processing_details = ?, updated_at = NOW() WHERE transaction_ref = ?");
            $stmt->execute([json_encode($withdrawalData), $transactionRef]);
            
        } elseif ($withdrawMethod === 'mobile_money') {
            // Process mobile money withdrawal
            $withdrawalData = [
                'transaction_ref' => $transactionRef,
                'amount' => $amount,
                'phone_number' => $accountNumber,
                'user_email' => $user['email'],
                'user_name' => $user['username']
            ];
            
            // In a real implementation, you would integrate with mobile money APIs
            // For now, we'll mark it as pending manual processing
            $stmt = $conn->prepare("UPDATE chapa_transactions SET status = 'processing', processing_details = ?, updated_at = NOW() WHERE transaction_ref = ?");
            $stmt->execute([json_encode($withdrawalData), $transactionRef]);
            
        } else {
            throw new Exception("Invalid withdrawal method");
        }
        
        // Record transaction in transactions table
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, reference) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], -$amount, 'withdrawal_request', $transactionRef]);
        
        // Log withdrawal request
        $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'], 
            'withdrawal_requested', 
            $_SERVER['REMOTE_ADDR'],
            json_encode([
                'transaction_ref' => $transactionRef, 
                'amount' => $amount,
                'method' => $withdrawMethod,
                'account' => substr($accountNumber, 0, 4) . '****' // Mask account number for security
            ])
        ]);
        
        $conn->commit();
        $_SESSION['success'] = "Withdrawal request of " . number_format($amount, 2) . " ETB submitted successfully! Processing time: 1-3 business days.";
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Withdrawal error: " . $e->getMessage());
        
        // Update transaction status if it exists
        if (isset($transactionRef)) {
            try {
                $stmt = $conn->prepare("UPDATE chapa_transactions SET status = 'failed', error_message = ?, updated_at = NOW() WHERE transaction_ref = ?");
                $stmt->execute([$e->getMessage(), $transactionRef]);
            } catch (Exception $dbError) {
                error_log("Failed to update transaction status: " . $dbError->getMessage());
            }
        }
        
        throw new Exception("Error processing withdrawal: " . $e->getMessage());
    }
    
    header("Location: index.php");
    exit();
}

// Helper function to get available numbers for a game
function getAvailableNumbers($game_id) {
    global $conn;
    
    $game_id = filter_var($game_id, FILTER_VALIDATE_INT);
    if ($game_id === false) {
        return [];
    }
    
    // Get game max players
    $stmt = $conn->prepare("SELECT max_players FROM games WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) return [];
    
    $max_players = $game['max_players'];
    $all_numbers = range(1, $max_players);
    
    // Get taken numbers
    $stmt = $conn->prepare("SELECT lucky_number FROM game_players WHERE game_id = ?");
    $stmt->execute([$game_id]);
    $taken_numbers = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    return array_diff($all_numbers, $taken_numbers);
}

// Get active games
function getActiveGames() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT g.*, u.username as creator_name, 
               COUNT(gp.id) as current_players
        FROM games g
        JOIN users u ON g.created_by = u.id
        LEFT JOIN game_players gp ON g.id = gp.game_id
        WHERE g.status IN ('waiting', 'started')
        GROUP BY g.id
        ORDER BY g.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get game history with pagination
function getGameHistory($page = 1, $per_page = 10) {
    global $conn;
    
    $page = filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $per_page = filter_var($per_page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
    
    $offset = ($page - 1) * $per_page;
    
    $stmt = $conn->prepare("
        SELECT gr.*, g.title as game_title, g.bet_amount, g.max_players,
               u.username as winner_name, gr.created_at as draw_time
        FROM game_results gr
        JOIN games g ON gr.game_id = g.id
        JOIN users u ON gr.winning_user_id = u.id
        ORDER BY gr.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get transaction history for current user
function getUserTransactions($user_id, $page = 1, $per_page = 10) {
    global $conn;
    
    $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
    $page = filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $per_page = filter_var($per_page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
    
    $offset = ($page - 1) * $per_page;
    
    $stmt = $conn->prepare("
        SELECT t.*, g.title as game_title
        FROM transactions t
        LEFT JOIN games g ON t.game_id = g.id
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get total game history count for pagination
function getTotalGameHistoryCount() {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM game_results");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'];
}

// Get total transaction count for pagination
function getTotalTransactionCount($user_id) {
    global $conn;
    
    $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM transactions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'];
}

// Check session timeout (15 minutes inactivity)
function checkSessionTimeout() {
    $timeout = 900; // 15 minutes in seconds
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header("Location: index.php?timeout=1");
        exit();
    }
    
    $_SESSION['last_activity'] = time();
}

// Check for session timeout
checkSessionTimeout();

// Get active games for display
$active_games = getActiveGames();

// Get game history for display
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$total_games = getTotalGameHistoryCount();
$total_pages = ceil($total_games / $per_page);
$game_history = getGameHistory($page, $per_page);

// Get user transactions if logged in
$user_transactions = [];
$transactions_page = isset($_GET['tpage']) ? max(1, intval($_GET['tpage'])) : 1;
$total_transactions = 0;

if (isset($_SESSION['user_id'])) {
    $user_transactions = getUserTransactions($_SESSION['user_id'], $transactions_page, $per_page);
    $total_transactions = getTotalTransactionCount($_SESSION['user_id']);
    $total_transactions_pages = ceil($total_transactions / $per_page);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <style>
.progress {
    position: relative;
    border-radius: 10px;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
}

.progress-bar {
    transition: width 1s linear;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

#progressText {
    color: white;
    text-shadow: 1px 1px 1px rgba(0,0,0,0.5);
    font-size: 0.9rem;
}

.progress-bar-animated {
    animation: progress-bar-stripes 1s linear infinite;
}

@keyframes progress-bar-stripes {
    from { background-position-x: 0; }
    to { background-position-x: 40px; }
}
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Lottery Game</title>
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 20px;
            background-color: #f8f9fa;
        }
        .balance-display {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .game-card {
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .progress {
            height: 10px;
        }
        .nav-tabs {
            margin-bottom: 20px;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
        }
        .weak {
            background-color: #dc3545;
            width: 33%;
        }
        .medium {
            background-color: #ffc107;
            width: 66%;
        }
        .strong {
            background-color: #28a745;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['timeout'])): ?>
            <div class="alert alert-warning">
                Your session has timed out due to inactivity. Please login again.
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-8">
                <h1>Secure Lottery Game</h1>
            </div>
            <div class="col-md-4 text-end">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="balance-display">
                        <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
                        <div class="fs-4">Balance: $<?= number_format($_SESSION['balance'], 2) ?></div>
                        <form method="post" action="logout.php" class="mt-2">
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
    <button type="submit" class="btn btn-sm btn-danger">Logout</button>
</form>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Guest User</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Login</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="login">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Login</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Register</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" id="registerForm">
                                <input type="hidden" name="action" value="register">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <div class="mb-3">
                                    <label for="reg_username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="reg_username" name="username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="reg_password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="reg_password" name="password" required>
                                    <div id="password-strength" class="password-strength"></div>
                                    <small class="text-muted">Password must be at least 12 characters with uppercase, lowercase, number, and special character</small>
                                </div>
                                <div class="mb-3">
                                    <label for="reg_confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="reg_confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" class="btn btn-success w-100">Register</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row mb-4">
                <div class="col">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <button class="btn btn-primary me-md-2" data-bs-toggle="modal" data-bs-target="#createGameModal">
                            Create Game
                        </button>
                        <button class="btn btn-success me-md-2" data-bs-toggle="modal" data-bs-target="#depositModal">
                            Deposit
                        </button>
                        <button class="btn btn-warning me-md-2" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                            Withdraw
                        </button>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                        Active Games
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                        Game History
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">
                        My Transactions
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="active" role="tabpanel">
                    <?php if (count($active_games) > 0): ?>
                        <div class="row row-cols-1 row-cols-md-2 g-4">
                            <?php foreach ($active_games as $game): ?>
                                <div class="col">
                                    <div class="card game-card">
                                        <div class="card-header">
                                            <h5 class="card-title"><?= htmlspecialchars($game['title']) ?></h5>
                                            <h6 class="card-subtitle mb-2 text-muted">
                                                Created by: <?= htmlspecialchars($game['creator_name']) ?>
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text">
                                                <strong>Bet Amount:</strong> $<?= number_format($game['bet_amount'], 2) ?><br>
                                                <strong>Players:</strong> <?= $game['current_players'] ?> / <?= $game['max_players'] ?>
                                            </p>
                                            
                                            <?php 
                                                $stmt = $conn->prepare("SELECT id FROM game_players WHERE game_id = ? AND user_id = ?");
                                                $stmt->execute([$game['id'], $_SESSION['user_id']]);
                                                $is_player = $stmt->rowCount() > 0;
                                                $is_creator = ($game['created_by'] == $_SESSION['user_id']);
                                                $can_cancel = ($is_creator && $game['status'] !== 'completed' && $game['status'] !== 'canceled');
                                            ?>
                                            <div class="d-grid gap-2 d-md-flex">
                                                <?php
    $can_join = ($game['status'] === 'waiting') && 
                ($game['current_players'] < $game['max_players']) &&
                !$is_player;
    if ($can_join) {
        echo '<button class="btn btn-primary me-md-2" 
                onclick="showJoinModal('.$game['id'].', '.$game['max_players'].')">
                Join
              </button>';
    }
    // Show Cancel button for creator if allowed (always, not just with Join)
    if ($can_cancel) {
        echo '<form method="post" style="display:inline-block; margin-left:8px; vertical-align:middle;">
                <input type="hidden" name="action" value="cancel_game">
                <input type="hidden" name="csrf_token" value="' . $csrf_token . '">
                <input type="hidden" name="game_id" value="' . $game['id'] . '">
                <button type="submit" class="btn btn-danger">Cancel</button>
              </form>';
    }
    // Show circular countdown spinner next to Cancel when drawing
    if ($game['status'] === 'drawing') {
        echo '<span id="draw-spinner-'.$game['id'].'" style="display:inline-block; margin-left:12px; vertical-align:middle;">
            <div class="spinner-border text-info" role="status" style="width: 3rem; height: 3rem; position:relative;">
                <span class="visually-hidden">Drawing...</span>
                <span id="draw-count-'.$game['id'].'" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:1.2rem;font-weight:bold;color:#17a2b8;">1</span>
            </div>
        </span>';
    }

    elseif ($game['status'] !== 'waiting') {
        echo '<span class="badge bg-';
        switch($game['status']) {
            case 'started': echo 'warning'; break;
            case 'completed': echo 'success'; break;
            case 'canceled': echo 'danger'; break;
            default: echo 'secondary';
        }
        echo '">'.ucfirst($game['status']).'</span>';
    } elseif ($is_player) {
        echo '<span class="text-success">Already joined</span>';
    } elseif ($game['current_players'] >= $game['max_players']) {
        echo '<span class="text-danger">Game full</span>';
    }
?>                                                
                                                <?php if ($can_cancel): ?>
<?php if (
    $is_creator &&
    $game['status'] !== 'completed' &&
    $game['status'] !== 'canceled'
): ?>
    <?php if ($game['current_players'] == $game['max_players']): ?>
        <form method="post" id="drawForm-<?= $game['id'] ?>">
            <input type="hidden" name="action" value="draw_winner">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="game_id" value="<?= $game['id'] ?>">
            <button type="button" onclick="showDrawProgress(<?= $game['id'] ?>)" 
                    class="btn btn-success draw-btn">
                Draw Winner
            </button>
        </form>
    <?php else: ?>
        <div class="alert alert-info mt-2">
            Waiting for all players to join before you can draw a winner.
        </div>
    <?php endif; ?>
<?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No active games available. Create one to get started!
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="tab-pane fade" id="history" role="tabpanel">
                    <?php if (count($game_history) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Game Title</th>
                                        <th>Winner</th>
                                        <th>Amount</th>
                                        <th>Lucky Number</th>
                                        <th>Date & Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($game_history as $index => $game): ?>
                                        <tr>
                                            <td><?= ($page - 1) * $per_page + $index + 1 ?></td>
                                            <td><?= htmlspecialchars($game['game_title']) ?></td>
                                            <td><?= htmlspecialchars($game['winner_name']) ?></td>
                                            <td>$<?= number_format($game['total_win'], 2) ?></td>
                                            <td><?= $game['winning_number'] ?></td>
                                            <td><?= date('M j, Y g:i A', strtotime($game['draw_time'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <nav aria-label="Game history pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No game history yet.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="tab-pane fade" id="transactions" role="tabpanel">
                    <?php if (count($user_transactions) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Game</th>
                                        <th>Date & Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_transactions as $index => $tx): ?>
                                        <tr>
                                            <td><?= ($transactions_page - 1) * $per_page + $index + 1 ?></td>
                                            <td><?= ucfirst(htmlspecialchars($tx['type'])) ?></td>
                                            <td class="<?= $tx['amount'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                $<?= number_format($tx['amount'], 2) ?>
                                            </td>
                                            <td>
                                                <?= $tx['game_title'] ? htmlspecialchars($tx['game_title']) : 'N/A' ?>
                                            </td>
                                            <td><?= date('M j, Y g:i A', strtotime($tx['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <nav aria-label="Transaction history pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($transactions_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?tpage=<?= $transactions_page - 1 ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_transactions_pages; $i++): ?>
                                    <li class="page-item <?= $i == $transactions_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?tpage=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($transactions_page < $total_transactions_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?tpage=<?= $transactions_page + 1 ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No transaction history yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create Game Modal -->
    <div class="modal fade" id="createGameModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Game</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_game">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <div class="mb-3">
                            <label for="game_title" class="form-label">Game Title</label>
                            <input type="text" class="form-control" id="game_title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="bet_amount" class="form-label">Bet Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="bet_amount" name="bet_amount" 
                                       min="0.01" max="1000" step="0.01" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="max_players" class="form-label">Max Players</label>
                            <input type="number" class="form-control" id="max_players" name="max_players" 
                                   min="2" max="100" required>
                        </div>
                        <div class="mb-3">
                            <label for="lucky_number" class="form-label">Your Lucky Number</label>
                            <input type="number" class="form-control" id="lucky_number" name="lucky_number" 
                                   min="1" required>
                            <small class="text-muted">Number must be between 1 and max players</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Create Game</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Join Game Modal -->
    <div class="modal fade" id="joinGameModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Join Game</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="join_game">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" id="join_game_id" name="game_id" value="">
                        <div class="mb-3">
                            <label for="join_lucky_number" class="form-label">Choose Your Lucky Number</label>
                            <select class="form-select" id="join_lucky_number" name="lucky_number" required>
                                <option value="">Select a number</option>
                                <!-- Numbers will be populated by JavaScript -->
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Join Game</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Deposit Modal -->
    <div class="modal fade" id="depositModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Deposit Funds via Chapa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="depositForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="deposit">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            You will be redirected to Chapa's secure payment page to complete your deposit.
                        </div>
                        
                        <div class="mb-3">
                            <label for="deposit_amount" class="form-label">Amount (ETB)</label>
                            <div class="input-group">
                                <span class="input-group-text">ETB</span>
                                <input type="number" class="form-control" id="deposit_amount" name="amount" 
                                       min="1" max="50000" step="0.01" required 
                                       placeholder="Enter amount in Ethiopian Birr">
                            </div>
                            <div class="form-text">
                                Minimum: 1 ETB | Maximum: 50,000 ETB
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="deposit_method" class="form-label">
                                <i class="fas fa-credit-card"></i> Select Payment Method
                            </label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="deposit_method" id="mobile_money_deposit" value="mobile_money" required>
                                    <label class="btn btn-outline-primary w-100 h-100" for="mobile_money_deposit">
                                        <div class="d-flex flex-column align-items-center py-2">
                                            <i class="fas fa-mobile-alt fa-2x mb-2"></i>
                                            <strong>Mobile Money</strong>
                                            <small class="text-muted">TeleBirr, M-Pesa, CBE Pay</small>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="deposit_method" id="bank_transfer_deposit" value="bank_transfer" required>
                                    <label class="btn btn-outline-success w-100 h-100" for="bank_transfer_deposit">
                                        <div class="d-flex flex-column align-items-center py-2">
                                            <i class="fas fa-university fa-2x mb-2"></i>
                                            <strong>Bank Transfer</strong>
                                            <small class="text-muted">CBE, DBE, AIB & More</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mobile Money Details -->
                        <div id="mobile_money_deposit_fields" style="display: none;">
                            <div class="card border-primary mb-3">
                                <div class="card-header bg-primary text-white">
                                    <i class="fas fa-mobile-alt"></i> Mobile Money Details
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="mobile_provider_deposit" class="form-label">Mobile Money Provider</label>
                                        <select class="form-select" id="mobile_provider_deposit" name="mobile_provider">
                                            <option value="">Select provider</option>
                                            <option value="telebirr">TeleBirr</option>
                                            <option value="mpesa">M-Pesa</option>
                                            <option value="cbepay">CBE Pay</option>
                                            <option value="hellocash">HelloCash</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="mobile_number_deposit" class="form-label">Mobile Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text">+251</span>
                                            <input type="tel" class="form-control" id="mobile_number_deposit" name="mobile_number"
                                                   placeholder="9xxxxxxxx" pattern="[0-9]{9}" maxlength="9">
                                        </div>
                                        <div class="form-text">Enter your 9-digit mobile number without country code</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bank Transfer Details -->
                        <div id="bank_transfer_deposit_fields" style="display: none;">
                            <div class="card border-success mb-3">
                                <div class="card-header bg-success text-white">
                                    <i class="fas fa-university"></i> Bank Transfer Details
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="bank_name_deposit" class="form-label">Bank Name</label>
                                        <select class="form-select" id="bank_name_deposit" name="bank_name">
                                            <option value="">Select your bank</option>
                                            <option value="CBE">Commercial Bank of Ethiopia</option>
                                            <option value="DBE">Development Bank of Ethiopia</option>
                                            <option value="AIB">Awash International Bank</option>
                                            <option value="BOA">Bank of Abyssinia</option>
                                            <option value="UB">United Bank</option>
                                            <option value="NIB">Nib International Bank</option>
                                            <option value="CBO">Cooperative Bank of Oromia</option>
                                            <option value="LIB">Lion International Bank</option>
                                            <option value="ZB">Zemen Bank</option>
                                        </select>
                                    </div>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Note:</strong> You will be redirected to Chapa's secure payment gateway to complete your bank transfer.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-credit-card"></i> Proceed to Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Withdraw Modal -->
    <div class="modal fade" id="withdrawModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-money-bill-wave text-warning"></i>
                        Withdraw Funds
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="withdrawForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="withdraw">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-clock"></i>
                            <strong>Processing Time:</strong> Withdrawal requests are processed manually within 1-3 business days.
                            You will receive a confirmation email once processed.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Current Balance</h6>
                                        <h4 class="text-success">ETB <?= number_format($_SESSION['balance'] ?? 0, 2) ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Minimum Withdrawal</h6>
                                        <h4 class="text-info">ETB 10.00</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <label for="withdraw_amount" class="form-label">
                                <i class="fas fa-coins"></i> Withdrawal Amount (ETB)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">ETB</span>
                                <input type="number" class="form-control" id="withdraw_amount" name="amount" 
                                       min="10" max="50000" step="0.01" required
                                       placeholder="Enter amount to withdraw">
                                <button type="button" class="btn btn-outline-secondary" onclick="setMaxWithdraw()">
                                    Max
                                </button>
                            </div>
                            <div class="form-text">
                                Minimum: ETB 10.00 | Maximum: ETB 50,000.00 | Available: ETB <?= number_format($_SESSION['balance'] ?? 0, 2) ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="withdraw_method" class="form-label">
                                <i class="fas fa-university"></i> Withdrawal Method
                            </label>
                            <select class="form-select" id="withdraw_method" name="withdraw_method" required onchange="toggleWithdrawFields()">
                                <option value="">Select withdrawal method</option>
                                <option value="bank_transfer">🏦 Bank Transfer</option>
                                <option value="mobile_money">📱 Mobile Money (M-Birr, HelloCash)</option>
                            </select>
                        </div>
                        
                        <div id="bank_transfer_fields" style="display: none;">
                            <div class="card border-primary mb-3">
                                <div class="card-header bg-primary text-white">
                                    <i class="fas fa-university"></i> Bank Transfer Details
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="bank_name" class="form-label">Bank Name</label>
                                        <select class="form-select" id="bank_name" name="bank_name">
                                            <option value="">Select your bank</option>
                                            <option value="CBE">Commercial Bank of Ethiopia</option>
                                            <option value="DBE">Development Bank of Ethiopia</option>
                                            <option value="AIB">Awash International Bank</option>
                                            <option value="BOA">Bank of Abyssinia</option>
                                            <option value="UB">United Bank</option>
                                            <option value="NIB">Nib International Bank</option>
                                            <option value="CBO">Cooperative Bank of Oromia</option>
                                            <option value="LIB">Lion International Bank</option>
                                            <option value="ZB">Zemen Bank</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="account_number" class="form-label">Account Number</label>
                                        <input type="text" class="form-control" id="account_number" name="account_number"
                                               placeholder="Enter your bank account number" maxlength="20">
                                    </div>
                                    <div class="mb-3">
                                        <label for="account_holder_name" class="form-label">Account Holder Name</label>
                                        <input type="text" class="form-control" id="account_holder_name" name="account_holder_name"
                                               placeholder="Full name as on bank account" maxlength="100">
                                    </div>
                                    <div class="mb-3">
                                        <label for="bank_code" class="form-label">Bank Code/Swift Code (Optional)</label>
                                        <input type="text" class="form-control" id="bank_code" name="bank_code"
                                               placeholder="Enter bank code if available" maxlength="20">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="mobile_money_fields" style="display: none;">
                            <div class="card border-success mb-3">
                                <div class="card-header bg-success text-white">
                                    <i class="fas fa-mobile-alt"></i> Mobile Money Details
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="mobile_provider" class="form-label">Mobile Money Provider</label>
                                        <select class="form-select" id="mobile_provider" name="mobile_provider">
                                            <option value="">Select provider</option>
                                            <option value="mbirr">M-Birr</option>
                                            <option value="hellocash">HelloCash</option>
                                            <option value="telebirr">TeleBirr</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="mobile_number" class="form-label">Mobile Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text">+251</span>
                                            <input type="tel" class="form-control" id="mobile_number" name="mobile_number"
                                                   placeholder="9xxxxxxxx" pattern="[0-9]{9}" maxlength="9">
                                        </div>
                                        <div class="form-text">Enter your 9-digit mobile number without country code</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="mobile_account_name" class="form-label">Account Holder Name</label>
                                        <input type="text" class="form-control" id="mobile_account_name" name="mobile_account_name"
                                               placeholder="Full name as registered with mobile money" maxlength="100">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="withdrawal_reason" class="form-label">
                                <i class="fas fa-comment"></i> Withdrawal Reason (Optional)
                            </label>
                            <select class="form-select" id="withdrawal_reason" name="withdrawal_reason">
                                <option value="">Select reason (optional)</option>
                                <option value="personal_use">Personal Use</option>
                                <option value="business_expense">Business Expense</option>
                                <option value="emergency">Emergency</option>
                                <option value="investment">Investment</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-shield-alt"></i>
                            <strong>Security Note:</strong> All withdrawal requests are verified manually for security.
                            Ensure your details are accurate to avoid delays.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-paper-plane"></i> Submit Withdrawal Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show join game modal with client-side validation of game status
        function showJoinModal(gameId, maxPlayers) {
            // First check game status via AJAX
            fetch('check_game_status.php?game_id=' + gameId)
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'waiting') {
                        alert('This game is no longer available for joining (Status: ' + 
                              data.status + ')');
                        return;
                    }
                    
                    if (data.current_players >= data.max_players) {
                        alert('This game is already full');
                        return;
                    }
                    // Proceed with showing the join modal (existing logic)
                    document.getElementById('join_game_id').value = gameId;
                    // Fetch available numbers via AJAX
                    fetch('get_numbers.php?game_id=' + gameId)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(numbers => {
                            const select = document.getElementById('join_lucky_number');
                            select.innerHTML = '<option value="">Select a number</option>';
                            numbers.forEach(number => {
                                const option = document.createElement('option');
                                option.value = number;
                                option.textContent = number;
                                select.appendChild(option);
                            });
                            // Show modal
                            const modal = new bootstrap.Modal(document.getElementById('joinGameModal'));
                            modal.show();
                        })
                        .catch(error => {
                            console.error('Error fetching available numbers:', error);
                            alert('Error loading available numbers. Please try again.');
                        });
                })
                .catch(error => {
                    console.error('Error checking game status:', error);
                    alert('Error checking game status');
                });
        }
        
        // Update lucky number max when max players changes
        document.getElementById('max_players').addEventListener('change', function() {
            const max = parseInt(this.value);
            const luckyNumberInput = document.getElementById('lucky_number');
            luckyNumberInput.max = max;
            luckyNumberInput.placeholder = `Choose between 1 and ${max}`;
        });
        
        // Password strength indicator
        document.getElementById('reg_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('password-strength');
            
            // Reset
            strengthBar.className = 'password-strength';
            strengthBar.style.width = '0%';
            
            if (password.length === 0) return;
            
            // Calculate strength
            let strength = 0;
            if (password.length >= 12) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[a-z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[\W]/.test(password)) strength += 1;
            
            // Update UI
            if (strength <= 2) {
                strengthBar.classList.add('weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        });
        
        // Confirmations for destructive actions
        document.querySelectorAll('.cancel-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to cancel this game? All players will be refunded.')) {
                    e.preventDefault();
                }
            });
        });
        
        // Initialize tooltips

        // Add this function to your existing JavaScript
        function showDrawProgress(gameId) {
    // Create modal for progress
    const modalHTML = `
    <div class="modal fade" id="drawProgressModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Drawing Winner</h5>
                </div>
                <div class="modal-body text-center">
                    <p>Please wait while we determine the winner...</p>
                    <div class="progress" style="height: 30px;">
                        <div id="drawProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                             role="progressbar" style="width: 0%">
                            <span id="progressText" class="fw-bold">0%</span>
                        </div>
                    </div>
                    <p id="countdownText" class="mt-2">10 seconds remaining</p>
                </div>
            </div>
        </div>
    </div>`;
    
    // Add to DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('drawProgressModal'));
    modal.show();
    
    // Start progress bar
    let seconds = 10;
    const progressBar = document.getElementById('drawProgressBar');
    const progressText = document.getElementById('progressText');
    const countdownText = document.getElementById('countdownText');
    
    const interval = setInterval(() => {
        seconds--;
        const progress = 100 - (seconds * 10);
        
        // Update progress bar
        progressBar.style.width = `${progress}%`;
        progressText.textContent = `${progress}%`;
        
        // Update countdown text
        countdownText.textContent = `${seconds} second${seconds !== 1 ? 's' : ''} remaining`;
        
        // Change color as progress completes
        if (progress > 70) {
            progressBar.classList.remove('bg-success');
            progressBar.classList.add('bg-warning');
        }
        if (progress > 90) {
            progressBar.classList.remove('bg-warning');
            progressBar.classList.add('bg-danger');
        }
        
        if (seconds <= 0) {
            clearInterval(interval);
            // Submit the draw form after completion
            document.getElementById(`drawForm-${gameId}`).submit();
            // Hide modal after a brief delay to show completion
            setTimeout(() => modal.hide(), 500);
        }
    }, 1000);
}

        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });


    </script>
    
    <script>
        // Global functions for withdrawal form functionality
        function toggleWithdrawFields() {
            const withdrawMethod = document.getElementById('withdraw_method').value;
            const bankFields = document.getElementById('bank_transfer_fields');
            const mobileFields = document.getElementById('mobile_money_fields');
            
            // Hide both field sets initially
            bankFields.style.display = 'none';
            mobileFields.style.display = 'none';
            
            // Show appropriate fields based on selection
            if (withdrawMethod === 'bank_transfer') {
                bankFields.style.display = 'block';
                // Make bank fields required
                document.getElementById('bank_name').required = true;
                document.getElementById('account_number').required = true;
                document.getElementById('account_holder_name').required = true;
                // Make mobile fields optional
                document.getElementById('mobile_provider').required = false;
                document.getElementById('mobile_number').required = false;
            } else if (withdrawMethod === 'mobile_money') {
                mobileFields.style.display = 'block';
                // Make mobile fields required
                document.getElementById('mobile_provider').required = true;
                document.getElementById('mobile_number').required = true;
                // Make bank fields optional
                document.getElementById('bank_name').required = false;
                document.getElementById('account_number').required = false;
                document.getElementById('account_holder_name').required = false;
            } else {
                // No method selected - make all fields optional
                document.getElementById('bank_name').required = false;
                document.getElementById('account_number').required = false;
                document.getElementById('account_holder_name').required = false;
                document.getElementById('mobile_provider').required = false;
                document.getElementById('mobile_number').required = false;
            }
        }

        // Function to set maximum withdrawal amount
        function setMaxWithdraw() {
            const balance = <?= $_SESSION['balance'] ?? 0 ?>;
            const maxAmount = Math.min(balance, 50000); // Max withdrawal limit
            document.getElementById('withdraw_amount').value = maxAmount.toFixed(2);
        }

        // Function to toggle deposit method fields
        function toggleDepositFields() {
            const mobileMoneyRadio = document.getElementById('mobile_money_deposit');
            const bankTransferRadio = document.getElementById('bank_transfer_deposit');
            const mobileFields = document.getElementById('mobile_money_deposit_fields');
            const bankFields = document.getElementById('bank_transfer_deposit_fields');
            
            // Hide both field sets initially
            mobileFields.style.display = 'none';
            bankFields.style.display = 'none';
            
            // Show appropriate fields based on selection
            if (mobileMoneyRadio.checked) {
                mobileFields.style.display = 'block';
                // Make mobile fields required
                document.getElementById('mobile_provider_deposit').required = true;
                document.getElementById('mobile_number_deposit').required = true;
                // Make bank fields optional
                document.getElementById('bank_name_deposit').required = false;
            } else if (bankTransferRadio.checked) {
                bankFields.style.display = 'block';
                // Make bank fields required
                document.getElementById('bank_name_deposit').required = true;
                // Make mobile fields optional
                document.getElementById('mobile_provider_deposit').required = false;
                document.getElementById('mobile_number_deposit').required = false;
            }
        }

        // Add event listeners for deposit method radio buttons
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMoneyRadio = document.getElementById('mobile_money_deposit');
            const bankTransferRadio = document.getElementById('bank_transfer_deposit');
            
            if (mobileMoneyRadio && bankTransferRadio) {
                mobileMoneyRadio.addEventListener('change', toggleDepositFields);
                bankTransferRadio.addEventListener('change', toggleDepositFields);
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($active_games)) foreach ($active_games as $game): ?>
            <?php if ($game['status'] === 'drawing'): ?>
                (function() {
                    var gameId = <?= json_encode($game['id']) ?>;
                    var winnerName = <?= isset($game['winner_name']) ? json_encode($game['winner_name']) : 'null' ?>;
                    var winnerLucky = <?= isset($game['winner_lucky_number']) ? json_encode($game['winner_lucky_number']) : 'null' ?>;
                    var spinner = document.getElementById('draw-spinner-' + gameId);
                    var countSpan = document.getElementById('draw-count-' + gameId);
                    var count = 1;
                    var interval = setInterval(function() {
                        if (countSpan) countSpan.textContent = count;
                        if (count >= 10) {
                            clearInterval(interval);
                            if (spinner) spinner.innerHTML = '';
                            if (spinner && winnerName && winnerLucky) {
                                spinner.innerHTML = '<div class="alert alert-success text-center my-3" style="min-width:180px;">'+
                                    '<strong>Winner:</strong> ' + winnerName + '<br>' +
                                    '<strong>Lucky Number:</strong> ' + winnerLucky +
                                    '</div>';
                            }
                        }
                        count++;
                    }, 600); // 600ms * 10 = 6 seconds
                })();
            <?php endif; ?>
        <?php endforeach; ?>
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
class Security {
    // Generate CSRF token
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Validate CSRF token
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            error_log("CSRF token validation failed");
            throw new Exception("CSRF token validation failed");
        }
        return true;
    }

    // Secure session start
    public static function secureSessionStart() {
        $session_name = 'secure_lottery_sess';
        $secure = true;
        $httponly = true;
        $samesite = 'Strict';

        if (ini_set('session.use_only_cookies', 1) === false) {
            error_log("Could not initiate a safe session");
            die("Session initialization failed");
        }

        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $cookieParams["lifetime"],
            'path' => '/',
            'domain' => $cookieParams["domain"],
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite
        ]);

        session_name($session_name);
        session_start();
        session_regenerate_id(true);
    }

    // Validate password complexity
    public static function validatePassword($password) {
        if (strlen($password) < 12) {
            return "Password must be at least 12 characters long";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return "Password must contain at least one uppercase letter";
        }
        if (!preg_match('/[a-z]/', $password)) {
            return "Password must contain at least one lowercase letter";
        }
        if (!preg_match('/[0-9]/', $password)) {
            return "Password must contain at least one number";
        }
        if (!preg_match('/[\W]/', $password)) {
            return "Password must contain at least one special character";
        }
        return true;
    }

    // Sanitize input
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    // Rate limiting
    public static function checkRateLimit($action, $maxAttempts = 5, $timeFrame = 300) {
        $key = 'rate_limit_' . $action;
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 0,
                'last_attempt' => time()
            ];
        }

        $current = $_SESSION[$key];
        if ((time() - $current['last_attempt']) > $timeFrame) {
            $_SESSION[$key] = [
                'attempts' => 1,
                'last_attempt' => time()
            ];
            return true;
        }

        if ($current['attempts'] >= $maxAttempts) {
            error_log("Rate limit exceeded for action: $action");
            return false;
        }

        $_SESSION[$key]['attempts']++;
        $_SESSION[$key]['last_attempt'] = time();
        return true;
    }
}
?>
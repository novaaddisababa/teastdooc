<?php
/**
 * Simple Environment Variable Loader
 * Loads environment variables from .env file
 */

class EnvLoader {
    /**
     * Load environment variables from .env file
     */
    public static function load($path = null) {
        $envFile = $path ?: __DIR__ . '/.env';
        
        if (!file_exists($envFile)) {
            // Try .env.example as fallback
            $envFile = __DIR__ . '/.env.example';
            if (!file_exists($envFile)) {
                return false;
            }
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (strpos($line, '#') === 0 || trim($line) === '') {
                continue;
            }
            
            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }
                
                // Set environment variable
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
        
        return true;
    }
    
    /**
     * Get environment variable with fallback
     */
    public static function get($key, $default = null) {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}

// Auto-load environment variables when this file is included
EnvLoader::load();
?>

<?php
/**
 * CSRF Protection Class
 * Handles CSRF token generation and validation
 */

class CSRF {
    
    /**
     * Generate and store CSRF token
     */
    public static function generateToken() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Only generate if token doesn't exist - don't regenerate on every page load
        // This ensures the token in the form matches the token in the session
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Get current CSRF token
     */
    public static function getToken() {
        return self::generateToken();
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateToken($token) {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Generate CSRF token field for forms
     */
    public static function tokenField() {
        $token = self::getToken();
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Validate POST request CSRF token
     */
    public static function validatePost() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }
        
        $token = $_POST[CSRF_TOKEN_NAME] ?? '';
        return self::validateToken($token);
    }
}


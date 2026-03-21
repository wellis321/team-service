<?php
/**
 * Authentication Class
 * Handles user authentication, sessions, and password management
 */

class Auth {
    
    /**
     * Check if user is logged in
     * Security: Uses "fail closed" approach - if we can't verify, deny access
     */
    public static function isLoggedIn() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if user_id exists in session
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            return false;
        }

        // Verify user still exists and is active in database
        $userId = $_SESSION['user_id'];

        $db = getDbConnection();
        if (!$db) {
            // Cannot verify user - fail closed for security
            error_log('Database connection failed during user verification for user_id: ' . $userId);
            return false;
        }

        try {
            $stmt = $db->prepare("SELECT id, is_active, email_verified FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            // User not found - clear session
            if ($user === false) {
                self::clearSessionPreserveCsrf();
                return false;
            }

            // User is inactive - clear session
            if (!$user['is_active']) {
                self::clearSessionPreserveCsrf();
                return false;
            }

            return true;
        } catch (Exception $e) {
            // Database error - fail closed for security
            // Log the error but don't grant access without verification
            error_log('Database error during user verification for user_id ' . $userId . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear session data while preserving CSRF token
     */
    private static function clearSessionPreserveCsrf() {
        $csrfToken = $_SESSION[CSRF_TOKEN_NAME] ?? null;
        $_SESSION = [];
        if ($csrfToken !== null) {
            $_SESSION[CSRF_TOKEN_NAME] = $csrfToken;
        }
    }
    
    /**
     * Get current user ID
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user organisation ID
     */
    public static function getOrganisationId() {
        return $_SESSION['organisation_id'] ?? null;
    }
    
    /**
     * Get current user data
     */
    public static function getUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        if (!isset($_SESSION['user_data'])) {
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([self::getUserId()]);
            $_SESSION['user_data'] = $stmt->fetch();
        }
        
        return $_SESSION['user_data'];
    }
    
    /**
     * Login user
     */
    public static function login($email, $password) {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT u.*, o.id as organisation_id, o.name as organisation_name 
            FROM users u 
            LEFT JOIN organisations o ON u.organisation_id = o.id
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if email is verified (only if email_verified column exists)
            // For superadmin users created before email verification migration, email_verified may not exist
            $emailVerified = $user['email_verified'] ?? true; // Default to true if column doesn't exist
            if (!$emailVerified) {
                return ['error' => 'email_not_verified', 'message' => 'Please verify your email address before logging in. Check your inbox for the verification link.'];
            }
            
            // Check if account is active
            if (!$user['is_active']) {
                return ['error' => 'account_inactive', 'message' => 'Your account has been deactivated. Please contact your administrator.'];
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['organisation_id'] = $user['organisation_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_data'] = $user;
            
            // Update last login
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Verify email address with token
     */
    public static function verifyEmail($token) {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT id, email, first_name, verification_token_expires_at 
            FROM users 
            WHERE verification_token = ? AND email_verified = FALSE
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or expired verification token.'];
        }
        
        // Check if token has expired
        if (strtotime($user['verification_token_expires_at']) < time()) {
            return ['success' => false, 'message' => 'Verification token has expired. Please request a new verification email.'];
        }
        
        // Get organisation ID before updating
        $stmt = $db->prepare("SELECT organisation_id FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $userOrg = $stmt->fetch();
        
        if (!$userOrg) {
            return ['success' => false, 'message' => 'User organisation not found.'];
        }
        
        // Check if seats are available before verifying
        $stmt = $db->prepare("
            SELECT seats_allocated, 
                   (SELECT COUNT(*) FROM users WHERE organisation_id = ? AND email_verified = TRUE AND is_active = TRUE) as verified_active_count
            FROM organisations 
            WHERE id = ?
        ");
        $stmt->execute([$userOrg['organisation_id'], $userOrg['organisation_id']]);
        $org = $stmt->fetch();
        
        if ($org && $org['verified_active_count'] >= $org['seats_allocated']) {
            return ['success' => false, 'message' => 'No available seats for this organisation. Please contact your administrator.'];
        }
        
        // Verify email and activate account
        $stmt = $db->prepare("
            UPDATE users 
            SET email_verified = TRUE, 
                is_active = TRUE, 
                verification_token = NULL, 
                verification_token_expires_at = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        
        // Update seats_used count (only verified and active users count)
        $stmt = $db->prepare("
            UPDATE organisations 
            SET seats_used = (
                SELECT COUNT(*) 
                FROM users 
                WHERE organisation_id = ? AND email_verified = TRUE AND is_active = TRUE
            )
            WHERE id = ?
        ");
        $stmt->execute([$userOrg['organisation_id'], $userOrg['organisation_id']]);
        
        return ['success' => true, 'message' => 'Email verified successfully! You can now log in.'];
    }
    
    /**
     * Resend verification email
     */
    public static function resendVerificationEmail($email) {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT id, first_name, email_verified 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Email not found.'];
        }
        
        if ($user['email_verified']) {
            return ['success' => false, 'message' => 'Email is already verified.'];
        }
        
        // Generate new verification token
        $verificationToken = Email::generateVerificationToken();
        $tokenExpires = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_TOKEN_EXPIRY_HOURS . ' hours'));
        
        $stmt = $db->prepare("
            UPDATE users 
            SET verification_token = ?, verification_token_expires_at = ? 
            WHERE id = ?
        ");
        $stmt->execute([$verificationToken, $tokenExpires, $user['id']]);
        
        // Send verification email
        $emailSent = Email::sendVerificationEmail($email, $user['first_name'], $verificationToken);
        
        if ($emailSent) {
            return ['success' => true, 'message' => 'Verification email sent. Please check your inbox.'];
        } else {
            return ['success' => false, 'message' => 'Failed to send verification email. Please try again later.'];
        }
    }
    
    /**
     * Validate password strength
     */
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
        }
        
        if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        
        if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        
        if (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        
        if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }
        
        return $errors;
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Preserve CSRF token
        $csrfToken = $_SESSION[CSRF_TOKEN_NAME] ?? null;
        
        // Clear all session data
        $_SESSION = [];
        
        // Destroy the session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destroy the session
        session_destroy();
        
        // Start a new clean session
        session_start();
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        // Restore CSRF token in new session
        if ($csrfToken !== null) {
            $_SESSION[CSRF_TOKEN_NAME] = $csrfToken;
        }
    }
    
    /**
     * Hash password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Register new user with domain-based organisation association
     * Domain is automatically extracted from email address
     */
    public static function register($email, $password, $firstName, $lastName, $domain = null) {
        $db = getDbConnection();

        // Extract domain from email if not provided
        if ($domain === null) {
            $domain = substr(strrchr($email, '@'), 1);
            if (empty($domain)) {
                return ['success' => false, 'message' => 'Invalid email address format.'];
            }
        }

        // Find organisation by domain
        $stmt = $db->prepare("SELECT id, seats_allocated, seats_used FROM organisations WHERE domain = ?");
        $stmt->execute([$domain]);
        $organisation = $stmt->fetch();

        if (!$organisation) {
            return ['success' => false, 'message' => 'No organisation found for email domain "' . htmlspecialchars($domain) . '". Your organisation needs to be set up before you can register. Please <a href="' . url('request-access.php') . '">request access</a> for your organisation first.'];
        }

        // Check if seats are available (only count verified and active users)
        $stmt = $db->prepare("
            SELECT COUNT(*) as verified_active_count
            FROM users
            WHERE organisation_id = ? AND email_verified = TRUE AND is_active = TRUE
        ");
        $stmt->execute([$organisation['id']]);
        $verifiedActiveCount = $stmt->fetch()['verified_active_count'];

        if ($verifiedActiveCount >= $organisation['seats_allocated']) {
            return ['success' => false, 'message' => 'No available seats for this organisation.'];
        }

        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already registered.'];
        }

        try {
            $db->beginTransaction();
            
            // Generate verification token
            $verificationToken = Email::generateVerificationToken();
            $tokenExpires = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_TOKEN_EXPIRY_HOURS . ' hours'));
            
            // Create user (inactive until email verified)
            $passwordHash = self::hashPassword($password);
            $stmt = $db->prepare("
                INSERT INTO users (organisation_id, email, password_hash, first_name, last_name, email_verified, verification_token, verification_token_expires_at, is_active)
                VALUES (?, ?, ?, ?, ?, FALSE, ?, ?, FALSE)
            ");
            $stmt->execute([
                $organisation['id'],
                $email,
                $passwordHash,
                $firstName,
                $lastName,
                $verificationToken,
                $tokenExpires
            ]);
            
            $userId = $db->lastInsertId();

            // Assign default staff role
            $stmt = $db->prepare("SELECT id FROM roles WHERE name = 'staff'");
            $stmt->execute();
            $role = $stmt->fetch();
            
            if ($role) {
                $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                $stmt->execute([$userId, $role['id']]);
            }
            
            // Don't update seats_used here - seats are only counted when email is verified
            // Seats will be updated when verifyEmail() is called
            
            $db->commit();

            // Send verification email
            $emailSent = Email::sendVerificationEmail($email, $firstName, $verificationToken);
            
            if (!$emailSent) {
                // Log error but don't fail registration - user can request resend
                error_log("Failed to send verification email to: $email");
            }

            return [
                'success' => true, 
                'message' => 'Registration successful! Please check your email to verify your account. The verification link will expire in ' . VERIFICATION_TOKEN_EXPIRY_HOURS . ' hours.'
            ];
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Registration failed for $email: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }
    
    /**
     * Require login - redirect if not logged in
     */
    public static function requireLogin() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        if (!self::isLoggedIn()) {
            // Preserve CSRF token when clearing session
            $csrfToken = $_SESSION[CSRF_TOKEN_NAME] ?? null;
            $_SESSION = [];
            if ($csrfToken !== null) {
                $_SESSION[CSRF_TOKEN_NAME] = $csrfToken;
            }
            
            // Use url() helper if available, otherwise fallback to relative path
            if (function_exists('url')) {
                $loginUrl = url('login.php');
            } else {
                $baseUrl = function_exists('getBaseUrl') ? getBaseUrl() : '';
                $loginUrl = ($baseUrl ?: '') . '/login.php';
            }
            
            // Ensure no output has been sent
            if (!headers_sent()) {
                header('Location: ' . $loginUrl);
            }
            exit;
        }
        
        // Double-check: verify user actually exists and is active
        $userId = self::getUserId();
        if ($userId) {
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT id, is_active, email_verified FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user || !$user['is_active']) {
                // User doesn't exist or is inactive - force logout
                self::logout();
                if (function_exists('url')) {
                    $loginUrl = url('login.php');
                } else {
                    $baseUrl = function_exists('getBaseUrl') ? getBaseUrl() : '';
                    $loginUrl = ($baseUrl ?: '') . '/login.php';
                }
                if (!headers_sent()) {
                    header('Location: ' . $loginUrl);
                }
                exit;
            }
        }
    }
}


<?php
/**
 * Role-Based Access Control Class
 * Handles permissions and role checking
 */

class RBAC {
    
    /**
     * Check if user has a specific role
     */
    public static function hasRole($roleName) {
        if (!Auth::isLoggedIn()) {
            return false;
        }
        
        $userId = Auth::getUserId();
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT r.name 
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ? AND r.name = ?
        ");
        $stmt->execute([$userId, $roleName]);
        
        return $stmt->fetch() !== false;
    }
    
    /**
     * Check if user is superadmin
     */
    public static function isSuperAdmin() {
        return self::hasRole('superadmin');
    }
    
    /**
     * Check if user is organisation admin
     */
    public static function isOrganisationAdmin() {
        return self::hasRole('organisation_admin');
    }
    
    /**
     * Check if user is staff
     */
    public static function isStaff() {
        return self::hasRole('staff');
    }
    
    /**
     * Check if user is admin (either superadmin or organisation admin)
     */
    public static function isAdmin() {
        return self::isSuperAdmin() || self::isOrganisationAdmin();
    }
    
    /**
     * Get all roles for current user
     */
    public static function getUserRoles() {
        if (!Auth::isLoggedIn()) {
            return [];
        }
        
        $userId = Auth::getUserId();
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT r.name 
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Helper to get redirect URL
     */
    private static function getRedirectUrl($path) {
        if (function_exists('url')) {
            return url($path);
        }
        $baseUrl = function_exists('getBaseUrl') ? getBaseUrl() : '';
        return ($baseUrl ?: '') . '/' . ltrim($path, '/');
    }
    
    /**
     * Require specific role - redirect if user doesn't have it
     */
    public static function requireRole($roleName) {
        Auth::requireLogin();
        
        if (!self::hasRole($roleName)) {
            header('Location: ' . self::getRedirectUrl('index.php?error=access_denied'));
            exit;
        }
    }
    
    /**
     * Require admin access
     */
    public static function requireAdmin() {
        Auth::requireLogin();
        
        if (!self::isAdmin()) {
            header('Location: ' . self::getRedirectUrl('index.php?error=access_denied'));
            exit;
        }
    }
    
    /**
     * Require superadmin access
     */
    public static function requireSuperAdmin() {
        Auth::requireLogin();
        
        if (!self::isSuperAdmin()) {
            header('Location: ' . self::getRedirectUrl('index.php?error=access_denied'));
            exit;
        }
    }
    
    /**
     * Require organisation admin access (not superadmin)
     */
    public static function requireOrganisationAdmin() {
        Auth::requireLogin();
        
        if (!self::isOrganisationAdmin()) {
            // If superadmin, redirect to superadmin panel
            if (self::isSuperAdmin()) {
                header('Location: ' . self::getRedirectUrl('superadmin.php'));
            } else {
                header('Location: ' . self::getRedirectUrl('index.php?error=access_denied'));
            }
            exit;
        }
    }
    
    /**
     * Check if user can access organisation data
     */
    public static function canAccessOrganisation($organisationId) {
        if (self::isSuperAdmin()) {
            return true; // Superadmin can access all
        }
        
        $userOrgId = Auth::getOrganisationId();
        return $userOrgId == $organisationId;
    }
    
    /**
     * Require organisation access
     */
    public static function requireOrganisationAccess($organisationId) {
        Auth::requireLogin();
        
        if (!self::canAccessOrganisation($organisationId)) {
            header('Location: ' . self::getRedirectUrl('index.php?error=access_denied'));
            exit;
        }
    }
    
    /**
     * Assign a role to a user
     * 
     * @param int $userId The user ID to assign the role to
     * @param string $roleName The role name to assign
     * @param int|null $assignedBy The user ID who is assigning the role (optional, defaults to current user)
     * @return array ['success' => bool, 'message' => string]
     */
    public static function assignRole($userId, $roleName, $assignedBy = null) {
        if ($assignedBy === null) {
            $assignedBy = Auth::getUserId();
        }
        
        $db = getDbConnection();
        
        // Get role ID
        $stmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->execute([$roleName]);
        $role = $stmt->fetch();
        
        if (!$role) {
            return ['success' => false, 'message' => 'Role not found.'];
        }
        
        // Check if role already assigned
        $stmt = $db->prepare("SELECT id FROM user_roles WHERE user_id = ? AND role_id = ?");
        $stmt->execute([$userId, $role['id']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'User already has this role.'];
        }
        
        // Assign role
        try {
            $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $role['id'], $assignedBy]);
            return ['success' => true, 'message' => 'Role assigned successfully.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to assign role: ' . $e->getMessage()];
        }
    }
    
    /**
     * Remove a role from a user
     * 
     * @param int $userId The user ID to remove the role from
     * @param string $roleName The role name to remove
     * @return array ['success' => bool, 'message' => string]
     */
    public static function removeRole($userId, $roleName) {
        $db = getDbConnection();
        
        // Get role ID
        $stmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->execute([$roleName]);
        $role = $stmt->fetch();
        
        if (!$role) {
            return ['success' => false, 'message' => 'Role not found.'];
        }
        
        // Remove role
        try {
            $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?");
            $stmt->execute([$userId, $role['id']]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Role removed successfully.'];
            } else {
                return ['success' => false, 'message' => 'User does not have this role.'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to remove role: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all roles for a specific user
     * 
     * @param int $userId The user ID
     * @return array Array of role names
     */
    public static function getUserRolesById($userId) {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT r.name 
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Get all users in an organisation with their roles
     * 
     * @param int $organisationId The organisation ID
     * @return array Array of users with their roles
     */
    public static function getUsersByOrganisation($organisationId) {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT u.*, 
                   GROUP_CONCAT(r.name SEPARATOR ', ') as roles,
                   CASE WHEN EXISTS (
                       SELECT 1 FROM user_roles ur2 
                       JOIN roles r2 ON ur2.role_id = r2.id 
                       WHERE ur2.user_id = u.id AND r2.name = 'organisation_admin'
                   ) THEN 1 ELSE 0 END as is_organisation_admin
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.organisation_id = ?
            GROUP BY u.id
            ORDER BY is_organisation_admin DESC, u.last_name, u.first_name
        ");
        $stmt->execute([$organisationId]);
        
        return $stmt->fetchAll();
    }
}


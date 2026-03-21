<?php
/**
 * Organisational Units Management Class
 * Handles flexible organisational hierarchy structures
 * Simplified approach matching php-app pattern
 * UK English spelling used throughout
 */

class OrganisationalUnits {
    
    /**
     * Create an organisational unit
     * 
     * @param int $organisationId
     * @param string $name
     * @param string|null $unitType Optional type (e.g., 'team', 'area', 'region')
     * @param int|null $parentUnitId Parent unit ID
     * @param string|null $description Description
     * @param int|null $managerUserId Manager user ID
     * @param array|null $metadata Additional metadata as JSON
     * @return array ['success' => bool, 'message' => string, 'id' => int|null]
     */
    public static function create($organisationId, $name, $unitType = null, $parentUnitId = null, $description = null, $managerUserId = null, $metadata = null) {
        $db = getDbConnection();
        
        try {
            $metadataJson = $metadata ? json_encode($metadata) : null;
            
            $stmt = $db->prepare("
                INSERT INTO organisational_units 
                (organisation_id, name, unit_type, parent_unit_id, description, manager_user_id, metadata) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$organisationId, $name, $unitType, $parentUnitId, $description, $managerUserId, $metadataJson]);
            
            return [
                'success' => true,
                'message' => 'Organisational unit created successfully.',
                'id' => $db->lastInsertId()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create unit: ' . $e->getMessage(),
                'id' => null
            ];
        }
    }
    
    /**
     * Get unit by ID
     * 
     * @param int $unitId
     * @return array|null
     */
    public static function findById($unitId) {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT u.*, 
                   p.name as parent_name,
                   (SELECT COUNT(*) FROM organisational_unit_members WHERE unit_id = u.id) as member_count
            FROM organisational_units u
            LEFT JOIN organisational_units p ON p.id = u.parent_unit_id
            WHERE u.id = ?
        ");
        $stmt->execute([$unitId]);
        $unit = $stmt->fetch();
        
        if ($unit && $unit['metadata']) {
            $unit['metadata'] = json_decode($unit['metadata'], true);
        }
        
        return $unit ?: null;
    }
    
    /**
     * Get all units for an organisation
     * 
     * @param int $organisationId
     * @return array
     */
    public static function getAllByOrganisation($organisationId) {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT u.*, 
                   p.name as parent_name,
                   (SELECT COUNT(*) FROM organisational_unit_members WHERE unit_id = u.id) as member_count
            FROM organisational_units u
            LEFT JOIN organisational_units p ON p.id = u.parent_unit_id
            WHERE u.organisation_id = ? AND u.is_active = TRUE
            ORDER BY u.parent_unit_id IS NULL DESC, u.name ASC
        ");
        $stmt->execute([$organisationId]);
        $results = $stmt->fetchAll();
        
        foreach ($results as &$result) {
            if ($result['metadata']) {
                $result['metadata'] = json_decode($result['metadata'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get hierarchy tree (recursive structure)
     * 
     * @param int $organisationId
     * @return array
     */
    public static function getHierarchyTree($organisationId) {
        $allUnits = self::getAllByOrganisation($organisationId);
        
        // Build tree structure
        $tree = [];
        $lookup = [];
        
        // First pass: create lookup and initialize children arrays
        foreach ($allUnits as $unit) {
            $unit['children'] = [];
            $lookup[$unit['id']] = $unit;
        }
        
        // Second pass: build tree
        foreach ($lookup as $id => $unit) {
            if ($unit['parent_unit_id'] === null) {
                $tree[] = &$lookup[$id];
            } else {
                if (isset($lookup[$unit['parent_unit_id']])) {
                    $lookup[$unit['parent_unit_id']]['children'][] = &$lookup[$id];
                }
            }
        }
        
        return $tree;
    }
    
    /**
     * Update unit
     * 
     * @param int $unitId
     * @param array $data Fields to update
     * @return array ['success' => bool, 'message' => string]
     */
    public static function update($unitId, $data) {
        $db = getDbConnection();
        
        $allowed = ['name', 'description', 'unit_type', 'parent_unit_id', 'manager_user_id', 'metadata', 'display_order', 'is_active'];
        $updates = [];
        $params = ['id' => $unitId];
        
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = :$field";
                if ($field === 'metadata' && $data[$field] !== null) {
                    $params[$field] = json_encode($data[$field]);
                } else {
                    $params[$field] = $data[$field];
                }
            }
        }
        
        if (empty($updates)) {
            return ['success' => false, 'message' => 'No fields to update.'];
        }
        
        try {
            $sql = 'UPDATE organisational_units SET ' . implode(', ', $updates) . ' WHERE id = :id';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Unit updated successfully.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update unit: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete unit (only if no children and no members)
     * 
     * @param int $unitId
     * @return array ['success' => bool, 'message' => string]
     */
    public static function delete($unitId) {
        $db = getDbConnection();
        
        // Check for children
        $stmt = $db->prepare("SELECT COUNT(*) FROM organisational_units WHERE parent_unit_id = ?");
        $stmt->execute([$unitId]);
        $childCount = $stmt->fetchColumn();
        
        if ($childCount > 0) {
            return ['success' => false, 'message' => 'Cannot delete unit with child units. Please delete or reassign children first.'];
        }
        
        // Check for members
        $stmt = $db->prepare("SELECT COUNT(*) FROM organisational_unit_members WHERE unit_id = ?");
        $stmt->execute([$unitId]);
        $memberCount = $stmt->fetchColumn();
        
        if ($memberCount > 0) {
            return ['success' => false, 'message' => 'Cannot delete unit with members. Please remove members first.'];
        }
        
        try {
            $stmt = $db->prepare('DELETE FROM organisational_units WHERE id = ?');
            $stmt->execute([$unitId]);
            
            return ['success' => true, 'message' => 'Unit deleted successfully.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete unit: ' . $e->getMessage()];
        }
    }
    
    /**
     * Add user to organisational unit
     * 
     * @param int $unitId
     * @param int $userId
     * @param string $role Role in unit (e.g., 'member', 'lead', 'admin')
     * @return array ['success' => bool, 'message' => string]
     */
    public static function addMember($unitId, $userId, $role = 'member') {
        $db = getDbConnection();
        
        try {
            $stmt = $db->prepare("
                INSERT INTO organisational_unit_members (unit_id, user_id, role) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE role = ?
            ");
            $stmt->execute([$unitId, $userId, $role, $role]);
            
            return ['success' => true, 'message' => 'Member added successfully.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to add member: ' . $e->getMessage()];
        }
    }
    
    /**
     * Remove user from organisational unit
     * 
     * @param int $unitId
     * @param int $userId
     * @return array ['success' => bool, 'message' => string]
     */
    public static function removeMember($unitId, $userId) {
        $db = getDbConnection();
        
        try {
            $stmt = $db->prepare("
                DELETE FROM organisational_unit_members 
                WHERE unit_id = ? AND user_id = ?
            ");
            $stmt->execute([$unitId, $userId]);
            
            return ['success' => true, 'message' => 'Member removed successfully.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to remove member: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update member role in organisational unit
     * 
     * @param int $unitId
     * @param int $userId
     * @param string $role
     * @return array ['success' => bool, 'message' => string]
     */
    public static function updateMemberRole($unitId, $userId, $role) {
        $db = getDbConnection();
        
        try {
            $stmt = $db->prepare("
                UPDATE organisational_unit_members 
                SET role = ? 
                WHERE unit_id = ? AND user_id = ?
            ");
            $stmt->execute([$role, $unitId, $userId]);
            
            return ['success' => true, 'message' => 'Member role updated successfully.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to update member role: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get members of a unit
     * 
     * @param int $unitId
     * @return array
     */
    public static function getMembers($unitId) {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT u.id, u.id AS user_id, u.first_name, u.last_name, u.email, um.role, um.joined_at
            FROM organisational_unit_members um
            JOIN users u ON u.id = um.user_id
            WHERE um.unit_id = ?
            ORDER BY um.joined_at ASC
        ");
        $stmt->execute([$unitId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get units a user belongs to
     * 
     * @param int $userId
     * @return array
     */
    public static function getUserUnits($userId) {
        $db = getDbConnection();
        
        $stmt = $db->prepare("
            SELECT u.*, um.role, um.joined_at
            FROM organisational_unit_members um
            JOIN organisational_units u ON u.id = um.unit_id
            WHERE um.user_id = ? AND u.is_active = TRUE
            ORDER BY u.name ASC
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Import from CSV file
     * 
     * @param int $organisationId
     * @param string $filePath
     * @return array ['units_created' => int, 'warnings' => array, 'errors' => array]
     */
    public static function importFromCsv($organisationId, $filePath) {
        $warnings = [];
        $errors = [];
        $unitsCreated = 0;
        
        if (!file_exists($filePath)) {
            return ['units_created' => 0, 'warnings' => [], 'errors' => ['File not found']];
        }
        
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['units_created' => 0, 'warnings' => [], 'errors' => ['Could not open file']];
        }
        
        // Read header
        $header = fgetcsv($handle);
        if (!$header || !in_array('name', $header)) {
            fclose($handle);
            return ['units_created' => 0, 'warnings' => [], 'errors' => ['Invalid CSV format: missing required "name" column']];
        }
        
        $rows = [];
        $lineNumber = 1;
        
        while (($data = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if (count($data) !== count($header)) {
                $warnings[] = "Line {$lineNumber}: Column count mismatch, skipping";
                continue;
            }
            
            $row = array_combine($header, $data);
            if (!empty($row['name'])) {
                $rows[] = $row;
            }
        }
        
        fclose($handle);
        
        // Process units
        $createdUnits = [];
        
        // First pass: create all units without parents
        foreach ($rows as $row) {
            $name = trim($row['name']);
            $unitType = !empty($row['unit_type']) ? trim($row['unit_type']) : null;
            $description = !empty($row['description']) ? trim($row['description']) : null;
            
            $result = self::create($organisationId, $name, $unitType, null, $description);
            
            if ($result['success']) {
                $createdUnits[$name] = [
                    'id' => $result['id'],
                    'parent_name' => !empty($row['parent']) ? trim($row['parent']) : null,
                ];
                $unitsCreated++;
            } else {
                $warnings[] = "Failed to create unit '{$name}': " . $result['message'];
            }
        }
        
        // Second pass: set parent relationships
        foreach ($createdUnits as $name => $unitInfo) {
            if ($unitInfo['parent_name'] && isset($createdUnits[$unitInfo['parent_name']])) {
                $parentId = $createdUnits[$unitInfo['parent_name']]['id'];
                $result = self::update($unitInfo['id'], ['parent_unit_id' => $parentId]);
                if (!$result['success']) {
                    $warnings[] = "Failed to set parent for '{$name}': " . $result['message'];
                }
            } elseif ($unitInfo['parent_name']) {
                $warnings[] = "Parent '{$unitInfo['parent_name']}' not found for unit '{$name}', created as top-level";
            }
        }
        
        return [
            'units_created' => $unitsCreated,
            'warnings' => $warnings,
            'errors' => $errors
        ];
    }
    
    /**
     * Import members from CSV
     * 
     * @param int $organisationId
     * @param string $filePath
     * @return array ['members_assigned' => int, 'members_skipped' => int, 'warnings' => array]
     */
    public static function importMembersFromCsv($organisationId, $filePath) {
        $warnings = [];
        $assigned = 0;
        $skipped = 0;
        
        if (!file_exists($filePath)) {
            return ['members_assigned' => 0, 'members_skipped' => 0, 'warnings' => ['File not found']];
        }
        
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['members_assigned' => 0, 'members_skipped' => 0, 'warnings' => ['Could not open file']];
        }
        
        // Read header
        $header = fgetcsv($handle);
        if (!$header || !in_array('email', $header) || !in_array('unit_name', $header)) {
            fclose($handle);
            return ['members_assigned' => 0, 'members_skipped' => 0, 'warnings' => ['Invalid CSV format: missing required "email" and/or "unit_name" columns']];
        }
        
        // Get all units for this organisation
        $allUnits = self::getAllByOrganisation($organisationId);
        $unitsByName = [];
        foreach ($allUnits as $unit) {
            $unitsByName[$unit['name']] = $unit;
        }
        
        $lineNumber = 1;
        
        while (($data = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if (count($data) !== count($header)) {
                $warnings[] = "Line {$lineNumber}: Column count mismatch, skipping";
                $skipped++;
                continue;
            }
            
            $row = array_combine($header, $data);
            $email = trim($row['email'] ?? '');
            $unitName = trim($row['unit_name'] ?? '');
            $role = trim($row['role'] ?? 'member');
            
            if (empty($email) || empty($unitName)) {
                $warnings[] = "Line {$lineNumber}: Missing email or unit_name, skipping";
                $skipped++;
                continue;
            }
            
            // Find user
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND organisation_id = ?");
            $stmt->execute([$email, $organisationId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $warnings[] = "Line {$lineNumber}: User '{$email}' not found, skipping";
                $skipped++;
                continue;
            }
            
            // Find unit
            if (!isset($unitsByName[$unitName])) {
                $warnings[] = "Line {$lineNumber}: Unit '{$unitName}' not found, skipping";
                $skipped++;
                continue;
            }
            
            $unit = $unitsByName[$unitName];
            
            // Assign member
            $result = self::addMember($unit['id'], $user['id'], $role);
            if ($result['success']) {
                $assigned++;
            } else {
                $warnings[] = "Line {$lineNumber}: Failed to assign {$email} to {$unitName}: " . $result['message'];
                $skipped++;
            }
        }
        
        fclose($handle);
        
        return [
            'members_assigned' => $assigned,
            'members_skipped' => $skipped,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Import from JSON file
     * 
     * @param int $organisationId
     * @param string $filePath
     * @return array ['units_created' => int, 'members_assigned' => int, 'warnings' => array, 'errors' => array]
     */
    public static function importFromJson($organisationId, $filePath) {
        $warnings = [];
        $errors = [];
        
        if (!file_exists($filePath)) {
            return ['units_created' => 0, 'members_assigned' => 0, 'warnings' => [], 'errors' => ['File not found']];
        }
        
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['units_created' => 0, 'members_assigned' => 0, 'warnings' => [], 'errors' => ['Invalid JSON: ' . json_last_error_msg()]];
        }
        
        if (!isset($data['units']) || !is_array($data['units'])) {
            return ['units_created' => 0, 'members_assigned' => 0, 'warnings' => [], 'errors' => ['Invalid JSON format: missing "units" array']];
        }
        
        $unitsCreated = 0;
        $membersAssigned = 0;
        
        foreach ($data['units'] as $unitData) {
            $result = self::processUnitRecursive($organisationId, $unitData, null);
            $unitsCreated += $result['units_created'];
            $membersAssigned += $result['members_assigned'];
            $warnings = array_merge($warnings, $result['warnings']);
        }
        
        return [
            'units_created' => $unitsCreated,
            'members_assigned' => $membersAssigned,
            'warnings' => $warnings,
            'errors' => $errors
        ];
    }
    
    /**
     * Recursively process JSON unit with children
     */
    private static function processUnitRecursive($organisationId, $unitData, $parentId) {
        $unitsCreated = 0;
        $membersAssigned = 0;
        $warnings = [];
        
        if (empty($unitData['name'])) {
            $warnings[] = 'Unit missing name, skipping';
            return ['units_created' => 0, 'members_assigned' => 0, 'warnings' => $warnings];
        }
        
        $name = trim($unitData['name']);
        $unitType = !empty($unitData['unit_type']) ? trim($unitData['unit_type']) : null;
        $description = !empty($unitData['description']) ? trim($unitData['description']) : null;
        
        $result = self::create($organisationId, $name, $unitType, $parentId, $description);
        
        if (!$result['success']) {
            $warnings[] = "Failed to create unit '{$name}': " . $result['message'];
            return ['units_created' => 0, 'members_assigned' => 0, 'warnings' => $warnings];
        }
        
        $unitId = $result['id'];
        $unitsCreated++;
        
        // Assign members
        if (!empty($unitData['members']) && is_array($unitData['members'])) {
            $db = getDbConnection();
            foreach ($unitData['members'] as $memberData) {
                if (empty($memberData['email'])) {
                    continue;
                }
                
                $email = trim($memberData['email']);
                $role = !empty($memberData['role']) ? trim($memberData['role']) : 'member';
                
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND organisation_id = ?");
                $stmt->execute([$email, $organisationId]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $warnings[] = "User '{$email}' not found for unit '{$name}', skipping";
                    continue;
                }
                
                $result = self::addMember($unitId, $user['id'], $role);
                if ($result['success']) {
                    $membersAssigned++;
                } else {
                    $warnings[] = "Failed to assign '{$email}' to '{$name}': " . $result['message'];
                }
            }
        }
        
        // Process children
        if (!empty($unitData['children']) && is_array($unitData['children'])) {
            foreach ($unitData['children'] as $childData) {
                $result = self::processUnitRecursive($organisationId, $childData, $unitId);
                $unitsCreated += $result['units_created'];
                $membersAssigned += $result['members_assigned'];
                $warnings = array_merge($warnings, $result['warnings']);
            }
        }
        
        return ['units_created' => $unitsCreated, 'members_assigned' => $membersAssigned, 'warnings' => $warnings];
    }
    
    /**
     * Import members from JSON file
     * 
     * @param int $organisationId
     * @param string $filePath
     * @return array ['members_assigned' => int, 'members_skipped' => int, 'warnings' => array]
     */
    public static function importMembersFromJson($organisationId, $filePath) {
        $warnings = [];
        $assigned = 0;
        $skipped = 0;
        
        if (!file_exists($filePath)) {
            return ['members_assigned' => 0, 'members_skipped' => 0, 'warnings' => ['File not found']];
        }
        
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['members_assigned' => 0, 'members_skipped' => 0, 'warnings' => ['Invalid JSON: ' . json_last_error_msg()]];
        }
        
        // Support both formats: array of assignments or object with assignments array
        $assignments = [];
        if (isset($data['assignments']) && is_array($data['assignments'])) {
            $assignments = $data['assignments'];
        } elseif (is_array($data) && isset($data[0])) {
            // Assume it's a direct array of assignments
            $assignments = $data;
        } else {
            return ['members_assigned' => 0, 'members_skipped' => 0, 'warnings' => ['Invalid JSON format: expected "assignments" array or array of assignment objects']];
        }
        
        // Get all units for this organisation
        $allUnits = self::getAllByOrganisation($organisationId);
        $unitsByName = [];
        foreach ($allUnits as $unit) {
            $unitsByName[$unit['name']] = $unit;
        }
        
        $db = getDbConnection();
        
        foreach ($assignments as $index => $assignment) {
            if (empty($assignment['email']) || empty($assignment['unit_name'])) {
                $warnings[] = "Assignment " . ($index + 1) . ": Missing email or unit_name, skipping";
                $skipped++;
                continue;
            }
            
            $email = trim($assignment['email']);
            $unitName = trim($assignment['unit_name']);
            $role = !empty($assignment['role']) ? trim($assignment['role']) : 'member';
            
            // Find user
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND organisation_id = ?");
            $stmt->execute([$email, $organisationId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $warnings[] = "Assignment " . ($index + 1) . ": User '{$email}' not found, skipping";
                $skipped++;
                continue;
            }
            
            // Find unit
            if (!isset($unitsByName[$unitName])) {
                $warnings[] = "Assignment " . ($index + 1) . ": Unit '{$unitName}' not found, skipping";
                $skipped++;
                continue;
            }
            
            $unit = $unitsByName[$unitName];
            
            // Assign member
            $result = self::addMember($unit['id'], $user['id'], $role);
            if ($result['success']) {
                $assigned++;
            } else {
                $warnings[] = "Assignment " . ($index + 1) . ": Failed to assign {$email} to {$unitName}: " . $result['message'];
                $skipped++;
            }
        }
        
        return [
            'members_assigned' => $assigned,
            'members_skipped' => $skipped,
            'warnings' => $warnings
        ];
    }
}

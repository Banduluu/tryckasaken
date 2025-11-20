<?php
/**
 * Database Schema Definition
 * Pure PHP schema - no .sql file needed
 */

class DatabaseSchema {
    
    /**
     * Get all table creation statements
     */
    public static function getTables() {
        return [
            'users' => "CREATE TABLE IF NOT EXISTS `users` (
                `user_id` int(11) NOT NULL AUTO_INCREMENT,
                `user_type` enum('passenger','driver','admin') NOT NULL,
                `name` varchar(100) NOT NULL,
                `email` varchar(100) NOT NULL,
                `phone` varchar(20) NOT NULL,
                `password` varchar(255) NOT NULL,
                `license_number` varchar(50) NULL,
                `tricycle_info` varchar(255) NULL,
                `verification_status` enum('pending','verified','rejected') DEFAULT NULL,
                `is_verified` tinyint(1) DEFAULT 0,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `status` enum('active','inactive','suspended') DEFAULT 'active',
                PRIMARY KEY (`user_id`),
                UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            
            'drivers' => "CREATE TABLE IF NOT EXISTS `drivers` (
                `driver_id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `or_cr_path` varchar(255) NOT NULL,
                `license_path` varchar(255) NOT NULL,
                `picture_path` varchar(255) NOT NULL,
                `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
                `is_online` tinyint(1) DEFAULT 1,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`driver_id`),
                KEY `fk_driver_user` (`user_id`),
                CONSTRAINT `fk_driver_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            
            'tricycle_bookings' => "CREATE TABLE IF NOT EXISTS `tricycle_bookings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `name` varchar(100) NOT NULL,
                `location` varchar(255) NOT NULL,
                `destination` varchar(255) NOT NULL,
                `notes` text NULL,
                `fare` decimal(10,2) NULL,
                `booking_time` timestamp NOT NULL DEFAULT current_timestamp(),
                `driver_id` int(11) DEFAULT NULL,
                `status` enum('pending','accepted','completed','cancelled') NOT NULL DEFAULT 'pending',
                PRIMARY KEY (`id`),
                KEY `fk_booking_user` (`user_id`),
                KEY `fk_booking_driver` (`driver_id`),
                KEY `idx_driver_status` (`driver_id`, `status`),
                CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
                CONSTRAINT `fk_booking_driver` FOREIGN KEY (`driver_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        ];
    }
    
    /**
     * Get sample/default data
     */
    public static function getSeedData() {
        return [
            'users' => [
                [
                    'user_type' => 'admin',
                    'name' => 'System Administrator',
                    'email' => 'admin@admin.com',
                    'phone' => '09000000000',
                    'password' => password_hash('password', PASSWORD_DEFAULT),
                    'license_number' => null,
                    'tricycle_info' => null,
                    'verification_status' => null,
                    'status' => 'active'
                ],
                [
                    'user_type' => 'passenger',
                    'name' => 'jc passenger',
                    'email' => 'passenger@test.com',
                    'phone' => '09111111111',
                    'password' => password_hash('password', PASSWORD_DEFAULT),
                    'license_number' => null,
                    'tricycle_info' => null,
                    'verification_status' => null,
                    'status' => 'active'
                ],
                [
                    'user_type' => 'driver',
                    'name' => 'JC Driver',
                    'email' => 'driver@test.com',
                    'phone' => '09222222222',
                    'password' => password_hash('password', PASSWORD_DEFAULT),
                    'license_number' => 'DL-123456',
                    'tricycle_info' => 'Blue Tricycle #101',
                    'verification_status' => 'verified',
                    'status' => 'active'
                ]
            ],
            'drivers' => []
        ];
    }
    
    /**
     * Execute schema creation
     */
    public static function createSchema($conn, $includeSeedData = true) {
        $results = [
            'tables_created' => 0,
            'data_inserted' => 0,
            'errors' => []
        ];
        
        // Create tables in order (respecting foreign keys)
        $tables = self::getTables();
        foreach ($tables as $tableName => $createSQL) {
            try {
                if ($conn->query($createSQL)) {
                    $results['tables_created']++;
                } else {
                    $results['errors'][] = "Table $tableName: " . $conn->error;
                }
            } catch (Exception $e) {
                $results['errors'][] = "Table $tableName: " . $e->getMessage();
            }
        }
        
        // Insert seed data if requested
        if ($includeSeedData) {
            $seedData = self::getSeedData();
            
            // Insert users
            if (isset($seedData['users'])) {
                $stmt = $conn->prepare("INSERT INTO users (user_type, name, email, phone, password, license_number, tricycle_info, verification_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($seedData['users'] as $user) {
                    try {
                        $license_number = $user['license_number'];
                        $tricycle_info = $user['tricycle_info'];
                        $verification_status = $user['verification_status'];
                        
                        $stmt->bind_param(
                            "sssssssss",
                            $user['user_type'],
                            $user['name'],
                            $user['email'],
                            $user['phone'],
                            $user['password'],
                            $license_number,
                            $tricycle_info,
                            $verification_status,
                            $user['status']
                        );
                        
                        if ($stmt->execute()) {
                            $results['data_inserted']++;
                        }
                    } catch (Exception $e) {
                        // Skip duplicates silently
                        if (strpos($e->getMessage(), 'Duplicate') === false) {
                            $results['errors'][] = "User data: " . $e->getMessage();
                        }
                    }
                }
                
                $stmt->close();
            }
            
            // Insert drivers
            if (isset($seedData['drivers'])) {
                $stmt = $conn->prepare("INSERT INTO drivers (user_id, or_cr_path, license_path, picture_path, verification_status) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($seedData['drivers'] as $driver) {
                    try {
                        $stmt->bind_param(
                            "issss",
                            $driver['user_id'],
                            $driver['or_cr_path'],
                            $driver['license_path'],
                            $driver['picture_path'],
                            $driver['verification_status']
                        );
                        
                        if ($stmt->execute()) {
                            $results['data_inserted']++;
                        }
                    } catch (Exception $e) {
                        // Skip duplicates silently
                        if (strpos($e->getMessage(), 'Duplicate') === false) {
                            $results['errors'][] = "Driver data: " . $e->getMessage();
                        }
                    }
                }
                
                $stmt->close();
            }
        }
        
        return $results;
    }
    
    /**
     * Check if schema exists
     */
    public static function schemaExists($conn) {
        $tables = ['users', 'drivers', 'tricycle_bookings'];
        $existingTables = 0;
        
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                $existingTables++;
            }
        }
        
        return $existingTables === count($tables);
    }
    
    /**
     * Verify schema integrity
     */
    public static function verifySchema($conn) {
        $checks = [];
        
        // Check each table
        $tables = array_keys(self::getTables());
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            $checks[$table] = ($result && $result->num_rows > 0);
        }
        
        // Check foreign keys
        $result = $conn->query("
            SELECT COUNT(*) as fk_count 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_TYPE = 'FOREIGN KEY' 
            AND TABLE_SCHEMA = DATABASE()
        ");
        
        if ($result) {
            $row = $result->fetch_assoc();
            $checks['foreign_keys'] = ($row['fk_count'] >= 2);
        }
        
        return $checks;
    }
}

<?php
/**
 * Database Setup Helper
 * Handles automatic database creation and schema setup
 */

function setupDatabase() {
    ob_start();
    error_reporting(0);
    ini_set('display_errors', 0);

    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'tric_db';

    try {
        $conn = new mysqli($db_host, $db_user, $db_pass);
        
        if (!$conn->connect_error) {
            $db_check = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db_name'");
            
            if ($db_check && $db_check->num_rows == 0) {
                $conn->query("CREATE DATABASE $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                $conn->select_db($db_name);
                DatabaseSchema::createSchema($conn, true);
            } else if ($db_check) {
                $conn->select_db($db_name);
                if (!DatabaseSchema::schemaExists($conn)) {
                    DatabaseSchema::createSchema($conn, true);
                }
            }
            $conn->close();
        }
    } catch (Exception $e) {
        error_log("Database setup failed: " . $e->getMessage());
    }
    
    ob_clean();
}

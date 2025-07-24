<?php
/**
 * Database Updates Script for Annual Leave System
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

try {
    $conn = getConnection();
    echo "Connected to database successfully\n";
    
    // Read and execute database updates
    $sql = file_get_contents('database_updates.sql');
    
    // Split queries by semicolon and execute each one
    $queries = explode(';', $sql);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query) && !str_starts_with($query, '--')) {
            echo "Executing: " . substr($query, 0, 50) . "...\n";
            
            if ($conn->query($query)) {
                echo "✓ Query executed successfully\n";
            } else {
                echo "✗ Error: " . $conn->error . "\n";
            }
        }
    }
    
    $conn->close();
    echo "\nDatabase updates completed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
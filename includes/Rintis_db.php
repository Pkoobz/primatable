<?php
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    // Create connection
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected successfully\n";
    
    // Read SQL file
    $sql = file_get_contents('Rintis.sql');
    
    // Execute SQL
    $pdo->exec($sql);
    
    echo "Database and tables created successfully!\n";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
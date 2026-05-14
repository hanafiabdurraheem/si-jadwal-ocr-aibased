<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...\n";

$conn = new mysqli("localhost", "root", "", "userdb");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "✓ Connected successfully to userdb\n";

// Test the table
$result = $conn->query("SHOW TABLES;");
echo "\nTables in userdb:\n";
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    echo "- " . $row[0] . "\n";
}

// Test the users table structure
echo "\nStructure of users table:\n";
$result = $conn->query("DESCRIBE users;");
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

// Test a query
echo "\nUsers in database:\n";
$result = $conn->query("SELECT username FROM users LIMIT 5;");
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['username'] . "\n";
}

echo "\n✓ All tests passed!\n";
$conn->close();
?>

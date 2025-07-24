<?php
$servername = "localhost";     // Don't change unless you're using a remote DB
$username = "root";            // Default for XAMPP
$password = "";                // Default is empty
$dbname = "leaguebook";   // ✅ correct
  // Your DB name

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

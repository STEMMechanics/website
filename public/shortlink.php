<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$dbHost = $_ENV['DB_HOST'];
$dbPort = $_ENV['DB_PORT'];
$dbName = $_ENV['DB_NAME'];
$dbUser = $_ENV['DB_USERNAME'];
$dbPass = $_ENV['DB_PASSWORD'];

// create connection
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

// check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// get code from URL
$code = $_SERVER['REQUEST_URI'];
$code = trim($code, '/');

// lookup code in database
$sql = "SELECT url, used FROM shortlinks WHERE code = '$code'";
$result = $conn->query($sql);

// if code is found, redirect to URL and update 'used' column
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $url = $row["url"];
    $used = $row["used"] + 1;
    $updateSql = "UPDATE shortlinks SET used = $used WHERE code = '$code'";
    $conn->query($updateSql);
    header("Location: " . $url);
    exit();
} else {
    // if code is not found, redirect to default URL
    header("Location: https://www.stemmechanics.com.au/");
    exit();
}

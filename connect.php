<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "leaguebook";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!function_exists('getStatusToken')) {
    function getStatusToken($lastActive) {
        if (!$lastActive) return "<span style='color:gray;'>⚫ Offline</span>";
        $isOnline = (time() - strtotime($lastActive)) <= 120;
        return $isOnline
            ? "<span style='color:green;'>🟢 Online</span>"
            : "<span style='color:gray;'>⚫ Offline</span>";
    }
}

if (!function_exists('isFriend')) {
    function isFriend($conn, $you, $them) {
        $query = "
            SELECT 1 FROM friends 
            WHERE (user1_id = ? AND user2_id = ?) 
               OR (user1_id = ? AND user2_id = ?)
            LIMIT 1
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiii", $you, $them, $them, $you);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
}

if (!function_exists('hasMutualFriend')) {
    function hasMutualFriend($conn, $you, $them) {
        $query = "
            SELECT COUNT(*) AS total 
            FROM (
                SELECT CASE WHEN user1_id = ? THEN user2_id ELSE user1_id END AS friend_id
                FROM friends 
                WHERE ? IN (user1_id, user2_id)
            ) AS your_friends
            INNER JOIN (
                SELECT CASE WHEN user1_id = ? THEN user2_id ELSE user1_id END AS friend_id
                FROM friends 
                WHERE ? IN (user1_id, user2_id)
            ) AS their_friends
            ON your_friends.friend_id = their_friends.friend_id
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiii", $you, $you, $them, $them);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['total'] > 0;
    }
}
?>

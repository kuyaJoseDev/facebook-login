<?php
include("connect.php");

// Fetch video posts with likes and comments count
$sql = "
    SELECT 
        p.id, 
        p.content, 
        p.video_path,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) AS likes_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comments_count
    FROM posts p
    WHERE LOWER(video_path) REGEXP '\\.(mp4|webm|ogg)$'
    ORDER BY created_at DESC
";

$result = $conn->query($sql);

$videos = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $videos[] = [
            'id'      => (int)$row['id'],
            'video'   => htmlspecialchars($row['video_path'], ENT_QUOTES, 'UTF-8'),
            'content' => htmlspecialchars($row['content'] ?? '', ENT_QUOTES, 'UTF-8'),
            'likes'   => (int)$row['likes_count'],
            'comments'=> (int)$row['comments_count']
        ];
    }
}

// Return JSON
header('Content-Type: application/json');
echo json_encode($videos);
exit;

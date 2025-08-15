<?php
session_start();
include("connect.php");

// Make sure this is after session_start() and include("connect.php")
$unreadCount = 0; // Default value in case the query fails

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS unread_count 
        FROM private_messages 
        WHERE receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $unreadCount = $row['unread_count'];
    }
}

$update = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
$update->bind_param("i", $_SESSION['user_id']);
$update->execute();


if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header("Location: LeagueBook.php");
    exit();
}

// 🔔 Count pending friend requests
$pending = 0;
$checkPending = $conn->prepare("SELECT COUNT(*) AS total FROM friend_requests WHERE receiver_id = ? AND status = 'pending'");
$checkPending->bind_param("i", $_SESSION['user_id']);
$checkPending->execute();
$pendingResult = $checkPending->get_result()->fetch_assoc();
$pending = $pendingResult['total'];

$userName = $_SESSION['user_name'] ?? 'Guest';
$userId = $_SESSION['user_id'];

// Handle friend request
if (isset($_GET['receiver_id'])) {
    $receiverId = (int) $_GET['receiver_id'];
    if ($receiverId !== $userId) {
        $check = $conn->prepare("SELECT * FROM friend_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'");
        $check->bind_param("ii", $userId, $receiverId);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows > 0) {
            $friendRequestMessage = "⚠️ You already sent a friend request to this user.";
        } else {
            $insert = $conn->prepare("INSERT INTO friend_requests (sender_id, receiver_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
            $insert->bind_param("ii", $userId, $receiverId);
            $friendRequestMessage = $insert->execute() ? "✅ Friend request sent successfully." : "❌ Failed to send friend request.";
        }
    } else {
        $friendRequestMessage = "⚠️ You cannot send a friend request to yourself.";
    }
}
// Mark offline automatically
$conn->query("UPDATE users SET status='offline' 
             WHERE TIMESTAMPDIFF(SECOND, last_active, NOW()) > 120");

$timeout_seconds = 120; // 2 minutes
$sql = "SELECT * FROM users 
        WHERE TIMESTAMPDIFF(SECOND, last_active, NOW()) <= ?"; // online users
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $timeout_seconds);
$stmt->execute();
$result = $stmt->get_result();
// ✅ Fetch updated online_status for display
// ✅ Get the user's last_active timestamp
$getStatus = $conn->prepare("SELECT last_active FROM users WHERE id = ?");
$getStatus->bind_param("i", $_SESSION['user_id']);
$getStatus->execute();
$result = $getStatus->get_result();
$user = $result->fetch_assoc();

if ($user && (time() - strtotime($user['last_active']) <= 120)) {
    echo "🟢 Online";
} else {
    echo "⚫ Offline";
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>LeagueBook</title>
  <link rel="stylesheet" href="LeagueBook_Page.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <script>
  function copyToClipboard(text) {
      navigator.clipboard.writeText(text).then(() => {
          alert("Post link copied to clipboard!");
      }).catch(err => {
          alert("Failed to copy: " + err);
      });
  }
  </script>
</head>
<div id="loading-screen">
  <div class="loader-content">
    <img src="" alt="" class="loader-logo">
    <div class="spinner"></div>
    <p>Loading LeagueBook...</p>
  </div>
</div>


<body>
 

  <!-- ✅ Stylish Logout Bar -->
  <div class="logout-bar">
    <!-- 🔗 Fixed Top Bar with Homepage Shortcut -->
<div class="top-bar">
 <a href="LeagueBook_Page.php" class="home-link">HOME</a>
</div>

<!-- 🎥 Videos Button -->
<button id="reelsButton" style="
    position: fixed;
    top:5%;
    left:50%;
    transform: translate(-50%, -50%);
    z-index:9999999;
    background:#2196f3;
    color:white;
    border:none;
    padding:12px 20px;
    border-radius:8px;
    font-size:16px;
    font-weight:bold;
    cursor:pointer;
">🎥 Videos</button>

<!-- Reels Container -->
<div id="reelsContainer" style="
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.95);
    z-index:999999;
    overflow:hidden;
    padding:20px;
    display:flex;
    justify-content:center;
    align-items:center;
">

    <!-- Video + Content -->
    <div id="videoWrapper" style="position:relative; max-width:70%; width:100%;">
        <div id="reelContent" style="text-align:center;"></div>

        <!-- Navigation Buttons -->
        <div id="reelNav" style="
            position:absolute;
            right:-60px;
            top:50%;
            transform:translateY(-50%);
            display:flex;
            flex-direction:column;
            gap:10px;
        ">
            <button onclick="prevReel()" style="padding:8px 12px;">⬆️</button>
            <button onclick="nextReel()" style="padding:8px 12px;">⬇️</button>
        </div>

        <!-- Right-side Action Buttons -->
        <div id="reelActions" style="
            position:absolute;
            right:-130px;
            top:50%;
            transform:translateY(-50%);
            display:flex;
            flex-direction:column;
            gap:15px;
            color:white;
            text-align:center;
        ">
            <div>
                <button onclick="likeVideo()" style="width:50px; height:50px; border-radius:50%; font-size:20px;">👍</button>
                <div id="likeCount" style="margin-top:4px;">0</div>
            </div>
            <div>
                <button onclick="toggleCommentPanel()" style="width:50px; height:50px; border-radius:50%; font-size:20px;">💬</button>
                <div id="commentCount" style="margin-top:4px;">0</div>
            </div>
            <div>
                <button onclick="replyVideo()" style="width:50px; height:50px; border-radius:50%; font-size:20px;">↩️</button>
                <div id="replyCount" style="margin-top:4px;">0</div>
            </div>
            <div>
                <button onclick="shareVideo()" style="width:50px; height:50px; border-radius:50%; font-size:20px;">🔗</button>
                <div id="shareCount" style="margin-top:4px;">0</div>
            </div>
        </div>
    </div>

    <!-- Close Button -->
    <button onclick="closeReels()" style="
        position:fixed;
        top:20px;
        right:20px;
        z-index:1000001;
        background:red;
        color:white;
        border:none;
        padding:8px 12px;
        border-radius:6px;
        cursor:pointer;
    ">❌ Close</button>
</div>


<!-- Comment Panel -->
<div id="commentPanel" style="
    display:none;
    position:fixed;
    top:40%;
    right:220px;
    width:320px;
    max-height:80%;
    background:rgba(0,0,0,0.9);
    color:white;
    border-radius:10px 0 0 10px;
    padding:10px;
    z-index:1000002;
    flex-direction:column;
">
    <h3 style="text-align:center; margin-bottom:10px;">Comments</h3>
    
    <div id="commentList" style="overflow-y:auto; max-height:60%; padding-right:5px;"></div>

    <div style="margin-top:10px; display:flex; gap:5px;">
        <input type="text" id="commentInput" placeholder="Add a comment..." style="flex:1; padding:6px; border-radius:5px; border:none;">
        <button id="commentSubmit" style="padding:6px 10px; border-radius:5px; border:none; background:#2196f3; color:white;">Send</button>
    </div>
</div>



<script>
let videos = [];
let currentIndex = 0;
let commentPanelVisible = false;
let scrollTimeout = null;

// =========================
// DOM Elements
// =========================
const container = document.getElementById('reelsContainer');
const reelContent = document.getElementById('reelContent');
const likeCount = document.getElementById('likeCount');
const commentCount = document.getElementById('commentCount');
const replyCount = document.getElementById('replyCount');
const shareCount = document.getElementById('shareCount');
const commentPanel = document.getElementById('commentPanel');
const reelNav = document.getElementById('reelNav');
const reelActions = document.getElementById('reelActions');
const videoWrapper = document.getElementById('videoWrapper');
const reelsButton = document.getElementById('reelsButton');

const commentList = document.getElementById('commentList');
const commentInput = document.getElementById('commentInput');
const commentSubmit = document.getElementById('commentSubmit');

let currentPostId = null;

// =========================
// Helper: Format time ago
// =========================
function timeAgo(dateString) {
    const now = new Date();
    const postDate = new Date(dateString);
    const seconds = Math.floor((now - postDate) / 1000);

    let interval = Math.floor(seconds / 31536000);
    if (interval >= 1) return interval + " year" + (interval > 1 ? "s" : "") + " ago";

    interval = Math.floor(seconds / 2592000);
    if (interval >= 1) return interval + " month" + (interval > 1 ? "s" : "") + " ago";

    interval = Math.floor(seconds / 86400);
    if (interval >= 1) return interval + " day" + (interval > 1 ? "s" : "") + " ago";

    interval = Math.floor(seconds / 3600);
    if (interval >= 1) return interval + " hour" + (interval > 1 ? "s" : "") + " ago";

    interval = Math.floor(seconds / 60);
    if (interval >= 1) return interval + " minute" + (interval > 1 ? "s" : "") + " ago";

    return "Just now";
}

// =========================
// Fetch videos
// =========================
fetch("video_posts.php")
    .then(res => res.json())
    .then(data => {
        videos = data;
        if (videos.length > 0) {
            container.style.display = 'flex';
            loadReel(currentIndex);
        }
    })
    .catch(() => alert("Failed to load videos"));



// =========================
// Prevent background scroll
// =========================
function preventBackgroundScroll(e) {
    if (e.target.closest('#videoWrapper') || e.target.closest('#commentPanel')) return;
    e.preventDefault();
    e.stopPropagation();
}

// =========================
// Open / Close Reels Overlay
// =========================
function openReels() {
    if (!videos.length) return;
    container.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    window.addEventListener('wheel', preventBackgroundScroll, { passive: false });
    window.addEventListener('touchmove', preventBackgroundScroll, { passive: false });
    loadReel(currentIndex);
}

function closeReels() {
    container.style.display = 'none';
    reelContent.innerHTML = '';
    commentPanel.style.display = 'none';
    commentPanelVisible = false;
    document.body.style.overflow = '';
    window.removeEventListener('wheel', preventBackgroundScroll, { passive: false });
    window.removeEventListener('touchmove', preventBackgroundScroll, { passive: false });
}

// Open overlay
reelsButton.addEventListener('click', openReels);

// =========================
// Load a single video
// =========================
function loadReel(index, direction = 'next') {
    const videoData = videos[index];
    const newVideo = document.createElement('video');
    newVideo.src = videoData.video;
    newVideo.autoplay = true;
    newVideo.controls = true;
    newVideo.playsInline = true;
    newVideo.className = 'reel-slide';
    newVideo.classList.add(direction === 'next' ? 'reel-enter-up' : 'reel-enter-down');

    const oldVideo = reelContent.querySelector('video');
    if (oldVideo) {
        oldVideo.classList.add(direction === 'next' ? 'reel-exit-up' : 'reel-exit-down');
        setTimeout(() => oldVideo.remove(), 500);
    }

    reelContent.appendChild(newVideo);
    setTimeout(() => newVideo.style.opacity = 1, 20);
    currentIndex = index;

    likeCount.textContent = videoData.likes || 0;
    commentCount.textContent = videoData.comments || 0;
    replyCount.textContent = videoData.replies || 0;
    shareCount.textContent = videoData.shares || 0;

    newVideo.addEventListener('click', e => {
        if (newVideo.paused) newVideo.play();
        else newVideo.pause();
        e.stopPropagation();
    });
}

// =========================
// Scroll inside videoWrapper → next/prev reel
// =========================
videoWrapper.addEventListener('wheel', e => {
    e.stopPropagation(); // prevent main page scroll
    e.preventDefault();
    if (scrollTimeout) return;
    scrollTimeout = setTimeout(() => scrollTimeout = null, 400);
    if (e.deltaY > 0) nextReel();
    else prevReel();
}, { passive: false });

// =========================
// Navigation
// =========================
function nextReel() {
    loadReel((currentIndex + 1) % videos.length, 'next');
}
function prevReel() {
    loadReel((currentIndex - 1 + videos.length) % videos.length, 'prev');
}

// =========================
// Click outside video closes overlay
// =========================
container.addEventListener('click', e => {
    if (e.target.closest('#videoWrapper') || e.target.closest('#commentPanel')) return;
    closeReels();
});

// =========================
// Like / Comment / Reply / Share
// =========================
function likeVideo() {
    const postId = videos[currentIndex].id;
    fetch('like_video.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: postId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            videos[currentIndex].likes = data.likes_count;
            likeCount.textContent = data.likes_count;
        } else alert(data.message);
    })
    .catch(() => alert("Failed to like the video"));
}

function toggleCommentPanel() {
    const postId = videos[currentIndex].id;
    if (commentPanelVisible) {
        commentPanel.classList.remove('show');
        setTimeout(() => {
            commentPanel.style.display = 'none';
            commentList.innerHTML = '';
            commentInput.value = '';
            commentPanelVisible = false;
        }, 300);
    } else {
        commentPanel.style.display = 'flex';
        setTimeout(() => commentPanel.classList.add('show'), 10);
        loadComments(postId);
        commentPanelVisible = true;
    }
}

// =========================
// Comments
// =========================
function loadComments(postId) {
    currentPostId = postId;
    commentList.innerHTML = '';

    fetch(`load_comments.php?post_id=${encodeURIComponent(postId)}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.comments.length) {
                commentList.innerHTML = `<p style="text-align:center;">No comments yet.</p>`;
                return;
            }
            data.comments.forEach(c => renderComment(c));
        })
        .catch(err => {
            console.error("Load comments error:", err);
            commentList.innerHTML = `<p style="text-align:center; color:red;">Failed to load comments.</p>`;
        });
}

function renderComment(c, parentDiv = null) {
    const div = document.createElement('div');
    div.className = 'comment';
    div.setAttribute('data-id', c.id);
    div.style.marginBottom = '8px';

    div.innerHTML = `
        <strong>${c.user_name}</strong>: ${c.content}
        <button onclick="showReplyInput(${c.id})">Reply</button>
        <div class="replies" style="margin-left:15px;"></div>
    `;

    if (parentDiv) parentDiv.querySelector('.replies').appendChild(div);
    else commentList.appendChild(div);

    if (c.replies && c.replies.length) {
        c.replies.forEach(r => renderComment(r, div));
    }
}

function replyToComment(commentId, text) {
    if (!currentPostId || !text) return;

    postComment(currentPostId, text, commentId, newComment => {
        const commentDiv = document.querySelector(`.comment[data-id="${commentId}"]`);
        if (!commentDiv) return;
        renderComment(newComment, commentDiv);
    });
}

function showReplyInput(commentId) {
    const commentDiv = document.querySelector(`.comment[data-id="${commentId}"]`);
    if (!commentDiv) return;

    let existingInput = commentDiv.querySelector('.replyInput');
    if (existingInput) {
        existingInput.querySelector('input').focus();
        return;
    }

    const replyDiv = document.createElement('div');
    replyDiv.className = 'replyInput';
    replyDiv.style.marginTop = '5px';
    replyDiv.innerHTML = `
        <input type="text" placeholder="Reply..." style="width:70%; padding:4px; border-radius:3px; border:1px solid #ccc;">
        <button style="padding:4px 6px; border:none; background:#4caf50; color:white;">Send</button>
    `;
    commentDiv.appendChild(replyDiv);

    const input = replyDiv.querySelector('input');
    const btn = replyDiv.querySelector('button');
    input.focus();

    input.addEventListener('keypress', e => {
        if (e.key === 'Enter') btn.click();
    });

    btn.addEventListener('click', () => {
        const text = input.value.trim();
        if (!text) return;

        replyToComment(commentId, text);
        replyDiv.remove();
    });
}

function postComment(postId, content, parentId = 0, callback = null) {
    fetch('post_comment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ post_id: postId, comment: content, parent_id: parentId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (callback) callback(data.new_comment);
            else loadComments(postId);

            if (parentId === 0) commentInput.value = '';
        } else alert(data.message || "Failed to post comment");
    })
    .catch(err => {
        console.error("Post comment error:", err);
        alert("Failed to post comment");
    });
}

commentSubmit.addEventListener('click', () => {
    const text = commentInput.value.trim();
    if (!text || !currentPostId) return;
    postComment(currentPostId, text);
});






function shareVideo() {
    videos[currentIndex].shares = (videos[currentIndex].shares || 0) + 1;
    shareCount.textContent = videos[currentIndex].shares;
}

// =========================
// Hover Nav / Action Buttons
// =========================
document.addEventListener('mousemove', (e) => {
    const windowWidth = window.innerWidth;
    const rightEdgeThreshold = 150;
    const centerThreshold = 400;

    if (e.clientX > windowWidth - rightEdgeThreshold) {
        reelNav.classList.add('visible');
        reelNav.classList.remove('hidden');
        reelActions.classList.add('visible');
        reelActions.classList.remove('hidden');
    } else if (Math.abs(e.clientX - windowWidth / 2) < centerThreshold) {
        reelNav.classList.add('hidden');
        reelNav.classList.remove('visible');
        reelActions.classList.add('hidden');
        reelActions.classList.remove('visible');
    } else {
        reelNav.classList.remove('visible', 'hidden');
        reelActions.classList.remove('visible', 'hidden');
    }
});

// =========================
// Mobile swipe support for reels
// =========================
let touchStartY = 0;
let touchEndY = 0;

videoWrapper.addEventListener('touchstart', (e) => {
    if (!e.touches || e.touches.length === 0) return;
    touchStartY = e.touches[0].clientY;
}, { passive: true });

videoWrapper.addEventListener('touchmove', (e) => {
    e.preventDefault();
}, { passive: false });

videoWrapper.addEventListener('touchend', (e) => {
    touchEndY = e.changedTouches[0].clientY;
    const deltaY = touchStartY - touchEndY;

    const swipeThreshold = 50;

    if (Math.abs(deltaY) > swipeThreshold) {
        if (deltaY > 0) nextReel();
        else prevReel();
    }

    touchStartY = 0;
    touchEndY = 0;
});
</script>



<style>
/* Comment panel animations */
#commentPanel{
    transform: translateY(100%);
    opacity:0;
    transition: transform 0.3s ease, opacity 0.3s ease;
}
#commentPanel.show{
    transform: translateY(0);
    opacity:1;
}
#reelNav, #reelActions {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    flex-direction: column;
    gap: 15px;
    transition: right 0.3s ease, opacity 0.3s ease;
    opacity: 0;              /* hidden by default */
    pointer-events: auto;     /* ensure buttons are clickable */
}
.comment {
    padding: 6px 4px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    position: relative;
}

.comment strong {
    color: #0195f8ff; /* username color */
}

.comment button {
    background: none;
    border: none;
    color: #4caf50;
    font-size: 0.9em;
    margin-left: 10px;
    cursor: pointer;
}

.comment .replies {
    margin-left: 15px; /* indent replies */
    margin-top: 4px;
    border-left: 1px solid rgba(255, 255, 255, 1);
    padding-left: 8px;
}

.replyInput input {
    background: rgba(255,255,255,0.1);
    color: white;
}

.replyInput button {
    cursor: pointer;
}


/* Default hidden positions */
#reelNav { right: -60px; }
#reelActions { right: -130px; }

/* Visible positions */
#reelNav.visible { right: 20px; opacity: 1; }
#reelNav.hidden { right: -60px; opacity: 0; }

#reelActions.visible { right: 20px; opacity: 1; }
#reelActions.hidden { right: -130px; opacity: 0; }
#reelNav, #reelActions {
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.visible {
    opacity: 1;
    pointer-events: auto;
    transform: translateX(0);
}

.hidden {
    opacity: 0;
    pointer-events: none;
    transform: translateX(50px); /* slide slightly out */
}



/* Video slide animations */
.reel-slide {
   
    top: 0;
    left: 0;
    width: 100%;
    max-height: 80vh; /* Limit height to viewport */
    border-radius: 8px;
    opacity: 0;
    transition: transform 0.5s ease, opacity 0.5s ease;
    display: flex;
    justify-content: center;
    align-items: center;
}


/* Entering from right */
.reel-enter-right {
    transform: translateX(100%);
    opacity: 0;
}
.reel-enter-right-active {
    transform: translateX(0);
    opacity: 1;
}

/* Entering from left */
.reel-enter-left {
    transform: translateX(-100%);
    opacity: 0;
}
.reel-enter-left-active {
    transform: translateX(0);
    opacity: 1;
}

/* Exiting to left */
.reel-exit-left {
    transform: translateX(-100%);
    opacity: 0;
}

/* Exiting to right */
.reel-exit-right {
    transform: translateX(100%);
    opacity: 0;
}

/* Mobile adjustments */
@media (max-width: 768px) {
    #videoWrapper {
        max-width: 90%;
    }
    .reel-slide video {
        width: 100%;
        height: auto;
        border-radius: 8px;
    }
}


/* Mobile adjustments */
@media (max-width:768px){
    
    }
    #reelActions{
        position:static;
        flex-direction:row;
        justify-content:center;
        gap:10px;
        margin-top:15px;
        transform:none;
    }
    #videoWrapper{ max-width:90%; }
    #commentPanel{
        top:auto;
        bottom:0;
        right:0;
        width:100%;
        max-height:50%;
        border-radius:12px 12px 0 0;
        transform: translateY(100%);
        opacity:0;
    }
    #commentPanel.show{ transform:translateY(0); opacity:1; }

/* Slide animation for navigation and action buttons */
#reelNav, #reelActions {
    transition: transform 0.4s ease, opacity 0.4s ease;
}

#reelNav.hidden, #reelActions.hidden {
    transform: translateX(100px);
    opacity: 0;
}

#reelNav.visible, #reelActions.visible {
    transform: translateX(0);
    opacity: 1;
}

</style>





<div id="chatBox" style="display:none; border:1px solid gray; padding:10px;">
  <h4 id="chatHeader">Chat</h4>
  <div id="chatMessages" style="height:300px; overflow-y:scroll; border:1px solid #ccc;"></div>
  <form onsubmit="sendMessage(event)">
    <input type="text" id="chatInput" placeholder="Type a message..." required>
    <button type="submit">Send</button>
  </form>
</div>


    <form action="LeagueBook.php" method="POST" class="logout-form">
      <span>👤 Logged in as: <strong><?php echo htmlspecialchars($userName); ?></strong></span>
      <button type="submit" class="logout-button">
  <i class="fas fa-power-off"></i> Logout
</button>

    </form>
  </div>


  <div class="main-container">
    <!-- Your layout with left-sidebar, wrapper, right-sidebar -->

  <div class="main-container"> 
<a href="inbox.php" class="button">
  📩 Inbox <?= $unreadCount > 0 ? "<span class='badge'>$unreadCount</span>" : "" ?>
</a>
 
<div class="friend-request-container">
  <a href="view_friend_request.php" class="friend-request-link">
    📬 View Friend Requests
    <?php if ($pending > 0): ?>
      <span class="badge"><?php echo $pending; ?></span>
    <?php endif; ?>
  </a>
</div>    
<?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
  <a href="admin_report.php" style="color:red; font-weight:bold;">🚨 Admin Reports</a>
<?php endif; ?>

    <h2>👋 Welcome, <?php echo htmlspecialchars($userName); ?>!</h2>

</div>
<button class="toggle-btn" onclick="toggleSidebar('left-sidebar')">👥Online/Offline Users</button>
<button class="toggle-btn" onclick="toggleSidebar('right-sidebar')">🧑‍🤝‍🧑 Peope You May Know</button>

<!-- 📦 Right Sidebar -->
<div id="right-sidebar" class="right-sidebar">
  <h3>🧑‍🤝‍🧑 People You May Know</h3>
  <?php
  $suggestQuery = $conn->prepare("
    SELECT id, name FROM users
    WHERE id != ?
      AND id NOT IN (
        SELECT CASE
          WHEN user1_id = ? THEN user2_id
          WHEN user2_id = ? THEN user1_id
        END
        FROM friends
        WHERE user1_id = ? OR user2_id = ?
      )
      AND id NOT IN (
        SELECT receiver_id FROM friend_requests WHERE sender_id = ? AND status = 'pending'
      )
    ORDER BY RAND() LIMIT 5
  ");
  $suggestQuery->bind_param("iiiiii", $userId, $userId, $userId, $userId, $userId, $userId);
  $suggestQuery->execute();
  $suggestResult = $suggestQuery->get_result();

  while ($suggested = $suggestResult->fetch_assoc()):
  ?>
    <div class="user-suggestion">
      <strong><?= htmlspecialchars($suggested['name']); ?></strong>
       <form method="GET" action="LeagueBook_Page.php">
        <input type="hidden" name="receiver_id" value="<?= (int)$user['id']; ?>">
        <button type="submit">➕ Add Friend</button>
      </form>
    </div>
  <?php endwhile; ?>
</div>
<!-- 📦 Left Sidebar -->
<div id="left-sidebar" class="left-sidebar">
  <h4>🟢 Online Users</h4>
  <div class="online-users">
    <?php
    $onlineQuery = $conn->prepare("
      SELECT u.id, u.name, u.last_active
      FROM users u
      WHERE u.id != ?
        AND u.id IN (
          SELECT CASE
            WHEN user1_id = ? THEN user2_id
            ELSE user1_id
          END
          FROM friends
          WHERE ? IN (user1_id, user2_id)
        )
    ");
    $onlineQuery->bind_param("iii", $userId, $userId, $userId);
    $onlineQuery->execute();
    $onlineResult = $onlineQuery->get_result();

    while ($user = $onlineResult->fetch_assoc()):
      $isOnline = (time() - strtotime($user['last_active'])) <= 120;
      if ($isOnline):
    ?>
      <div class="user-suggestion">
        <strong>
          <?= htmlspecialchars($user['name']); ?>
          <?= getStatusToken($user['last_active']); ?>
        </strong>
        <!-- No Add Friend button because they're already friends -->
      </div>
    <?php endif; endwhile; ?>
  </div>

  <h4>⚫ Offline Users</h4>
  <div class="offline-users">
    <?php
    $offlineQuery = $conn->prepare("
      SELECT u.id, u.name, u.last_active
      FROM users u
      WHERE u.id != ?
        AND u.id IN (
          SELECT CASE
            WHEN user1_id = ? THEN user2_id
            ELSE user1_id
          END
          FROM friends
          WHERE ? IN (user1_id, user2_id)
        )
    ");
    $offlineQuery->bind_param("iii", $userId, $userId, $userId);
    $offlineQuery->execute();
    $offlineResult = $offlineQuery->get_result();

    while ($user = $offlineResult->fetch_assoc()):
      $isOnline = (time() - strtotime($user['last_active'])) <= 120;
      if (!$isOnline):
    ?>
      <div class="user-suggestion">
        <strong>
          <?= htmlspecialchars($user['name']); ?>
          <?= getStatusToken($user['last_active']); ?>
        </strong>
        <!-- No Add Friend button because they're already friends -->
      </div>
    <?php endif; endwhile; ?>
  </div>
</div>

    <!-- Post Form -->
    <form action="Post.php" method="POST" enctype="multipart/form-data" class="post-form">
      <textarea name="content" placeholder="What's on your mind?" required></textarea>
      <input type="file" name="image" accept="image/*">
      <input type="file" name="video" accept="video/mp4,video/webm,video/ogg">
      <button type="submit">➕ Post</button>
    </form>

    <hr>
    <h3>📰 News Feed:</h3>

    <?php
    $sql = "SELECT posts.*, users.name FROM posts JOIN users ON posts.user_id = users.id ORDER BY posts.created_at DESC";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0):
        while ($row = $result->fetch_assoc()):
    ?>
      <div class="post-box">
        <strong>
          <a href="view_profile.php?id=<?php echo (int)$row['user_id']; ?>">
            <?php echo htmlspecialchars($row['name']); ?>
          </a><br>
          <small>📅 Posted on: <?php echo $row['created_at']; ?></small>
        </strong>
        <p><?php echo nl2br(htmlspecialchars($row['content'])); ?></p>

        <?php if (!empty($row['image_path'])): ?>
          <img src="<?php echo htmlspecialchars($row['image_path']); ?>" style="max-width:100%; height:auto;"><br>
        <?php endif; ?>

        <?php if (!empty($row['video_path']) && file_exists($row['video_path'])): ?>
          <?php $mime = mime_content_type($row['video_path']); ?>
          <video controls style="max-width:100%; height:auto;">
            <source src="<?php echo htmlspecialchars($row['video_path']); ?>" type="<?php echo $mime; ?>">
            Your browser does not support the video tag.
          </video><br>
        <?php endif; ?>

        <?php if (!empty($row['updated_at'])): ?>
          <small><i>✏️ Edited on: <?php echo $row['updated_at']; ?></i></small><br>
        <?php endif; ?>

        <?php
        $likeQuery = $conn->query("SELECT COUNT(*) AS like_count FROM likes WHERE post_id = {$row['id']}");
        $likeCount = $likeQuery->fetch_assoc()['like_count'];

        $liked = false;
        $checkLike = $conn->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
        $checkLike->bind_param("ii", $row['id'], $userId);
        $checkLike->execute();
        $checkLike->store_result();
        $liked = $checkLike->num_rows > 0;
        ?>

        <form action="like_post.php" method="POST" style="display:inline;">
          <input type="hidden" name="post_id" value="<?php echo $row['id']; ?>">
          <button type="submit">
            <?php echo $liked ? "👎 Unlike" : "👍 Like"; ?> (<?php echo $likeCount; ?>)
          </button>
        </form>

        <?php if ($userId == $row['user_id']): ?>
          <a href="edit_post.php?id=<?php echo $row['id']; ?>">
            <button>✏️ Edit</button>
          </a>
          <form action="delete_post.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this post?');">
            <input type="hidden" name="post_id" value="<?php echo $row['id']; ?>">
            <button type="submit">🗑️ Delete</button>
          </form>
        <?php endif; ?>

      <?php
$post_id = $row['id'];
$share_link = "http://localhost/League-University/LeagueBook/view_post.php?id=$post_id";
?>
<a href="view_post.php?id=<?php echo $post_id; ?>">
  <button>🔍 View Post</button>
</a>
<button onclick="copyToClipboard('<?php echo $share_link; ?>')">🔗 Share</button>
<!-- Report Form -->
<form action="report_system.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to report this post?');">
  <input type="hidden" name="post_id" value="<?php echo $row['id']; ?>">
  <input type="hidden" name="reason" value="Inappropriate content">
  <button type="submit">🚨 Report</button>
</form>


        <!-- Comment Form -->
        <form action="comment.php" method="POST" class="comment-form">
          <input type="hidden" name="post_id" value="<?php echo $row['id']; ?>">
          <input type="text" name="comment" placeholder="Write a comment..." required>
          <button type="submit">💬 Comment</button>
        </form>
        

        <!-- Comments -->
        <?php
        $c_stmt = $conn->prepare("SELECT comments.*, users.name FROM comments JOIN users ON comments.user_id = users.id WHERE post_id = ? ORDER BY comments.created_at ASC");
        $c_stmt->bind_param("i", $row['id']);
        $c_stmt->execute();
        $c_result = $c_stmt->get_result();
        ?>
        <div class="comments">
          <?php while ($comment = $c_result->fetch_assoc()): ?>
            <div class="comment-box">
              <strong>
                <a href="view_profile.php?id=<?php echo (int)$comment['user_id']; ?>">
                  <?php echo htmlspecialchars($comment['name']); ?>
                </a>
              </strong>: <?php echo nl2br(htmlspecialchars($comment['content'])); ?><br>
              <small><?php echo $comment['created_at']; ?></small>
              <?php if ($comment['user_id'] == $userId): ?>
                <form method="post" action="delete_comment.php" style="display:inline;">
                  <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                  <button type="submit" onclick="return confirm('Delete this comment?')">🗑️ Delete</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endwhile; ?>
        </div>
      </div>
      <hr>
    <?php endwhile; else: echo "<p>No posts yet.</p>"; endif; ?>
  </div>
  <script>
  window.addEventListener("load", function () {
    const loader = document.getElementById("loading-screen");
    loader.style.opacity = "0";
    setTimeout(() => {
      loader.style.display = "none";
    }, 500);
  });
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Show reply forms
    document.querySelectorAll('.reply-link').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const form = document.getElementById('reply-form-' + this.dataset.commentId);
            if (form) {
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            }
        });
    });

    // ✅ INSERT THIS BELOW

    document.querySelectorAll('.toggle-replies').forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.getElementById(this.dataset.target);
            if (target) {
                const isVisible = target.style.display === 'block';
                target.style.display = isVisible ? 'none' : 'block';
                this.innerText = this.innerText.replace(isVisible ? '🔼 Hide' : '🔽 View', isVisible ? '🔽 View' : '🔼 Hide');
            }
        });
    });

    // Auto open a reply thread (if open_reply param is set)
    <?php if (!empty($open_reply_id)) : ?>
    const replyContainer = document.getElementById('replies-<?php echo $open_reply_id; ?>');
    const toggleButton = document.querySelector('[data-target="replies-<?php echo $open_reply_id; ?>"]');
    if (replyContainer && toggleButton) {
        replyContainer.style.display = 'block';
        toggleButton.innerText = toggleButton.innerText.replace('🔽 View', '🔼 Hide');
    }
    <?php endif; ?>
});
</script>

<script>
function toggleSidebar(id) {
  const sidebar = document.getElementById(id);
  if (sidebar.classList.contains('sidebar-visible')) {
    sidebar.classList.remove('sidebar-visible');
    setTimeout(() => sidebar.classList.add('hidden'), 300);
  } else {
    sidebar.classList.remove('hidden');
    setTimeout(() => sidebar.classList.add('sidebar-visible'), 10);
  }
}
document.addEventListener('click', function(event) {
  const leftSidebar = document.getElementById('left-sidebar');
  const rightSidebar = document.getElementById('right-sidebar');

  if (!leftSidebar.contains(event.target) && !event.target.closest('[onclick*="left-sidebar"]')) {
    leftSidebar.classList.remove('sidebar-visible');
    setTimeout(() => leftSidebar.classList.add('hidden'), 300);
  }

  if (!rightSidebar.contains(event.target) && !event.target.closest('[onclick*="right-sidebar"]')) {
    rightSidebar.classList.remove('sidebar-visible');
    setTimeout(() => rightSidebar.classList.add('hidden'), 300);
  }
});
function toggleSidebar(id) {
  const sidebar = document.getElementById(id);

  if (sidebar.classList.contains('sidebar-visible')) {
    // First, remove the visible class to trigger the slide-out animation
    sidebar.classList.remove('sidebar-visible');

    // After transition ends (300ms), add the hidden class to fully hide it
    setTimeout(() => {
      sidebar.classList.add('hidden');
    }, 300); // match this with your CSS transition duration
  } else {
    // Show sidebar
    sidebar.classList.remove('hidden');

    // Allow time for DOM to update before triggering the animation
    setTimeout(() => {
      sidebar.classList.add('sidebar-visible');
    }, 10); // slight delay allows transition to work smoothly
  }
}


</script>
<script>
  let chatUserId = null;

  function openChat(userId, userName) {
    chatUserId = userId;
    document.getElementById("chatHeader").innerText = `Chat with ${userName}`;
    document.getElementById("chatBox").style.display = "block";
    loadMessages();
    setInterval(loadMessages, 3000); // auto-refresh
  }

  function loadMessages() {
    if (!chatUserId) return;
    fetch("load_messages.php?user_id=" + chatUserId)
      .then(res => res.text())
      .then(data => {
        document.getElementById("chatMessages").innerHTML = data;
        const chatMessages = document.getElementById("chatMessages");
        chatMessages.scrollTop = chatMessages.scrollHeight;
      });
  }

  function sendMessage(e) {
    e.preventDefault();
    const input = document.getElementById("chatInput");
    const msg = input.value.trim();
    if (!msg || !chatUserId) return;

    fetch("send_message.php", {
      method: "POST",
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `receiver_id=${chatUserId}&message=${encodeURIComponent(msg)}`
    }).then(() => {
      input.value = '';
      loadMessages();
    });
  }
</script>
<script>
setInterval(() => {
  fetch('update_last_active.php');
}, 60000); // every 60 seconds
</script>

</body>
</html>

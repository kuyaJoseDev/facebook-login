<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

$currentUserId = $_SESSION['user_id'];
$chatUserId = $_GET['user_id'] ?? null;

if (!filter_var($chatUserId, FILTER_VALIDATE_INT)) {
    header("Location: inbox.php");
    exit();
}

function getStatusToken($last_active) {
    return strtotime($last_active) > strtotime('-5 minutes') ? "🟢 Online" : "⚫ Offline";
}

// Fetch chat user info
$statusStmt = $conn->prepare("SELECT name, last_active FROM users WHERE id = ?");
$statusStmt->bind_param("i", $chatUserId);
$statusStmt->execute();
$statusResult = $statusStmt->get_result();

if ($statusResult->num_rows === 0) die("User not found.");
$chatUser = $statusResult->fetch_assoc();

// Mark unread messages as read
$markRead = $conn->prepare("UPDATE private_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
$markRead->bind_param("ii", $chatUserId, $currentUserId);
$markRead->execute();

// Total messages count
$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM private_messages 
    WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
$countStmt->bind_param("iiii", $currentUserId, $chatUserId, $chatUserId, $currentUserId);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalMessages = $countResult->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📨 Private Messages</title>
<link rel="stylesheet" href="LeagueBook_Page.css">
<style>
/* General Styles */
body {
    font-family: 'Segoe UI', sans-serif;
    background-color: #f4f4f4;
    margin: 0;
    padding: 20px;
}

.main-container {
    max-width: 700px;
    margin: auto;
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0,0,0,0.05);
}

h2 {
    margin-top: 0;
    text-align: center;
    font-size: 1.4rem;
    margin-bottom: 20px;
}

/* Chat Box */
.chat-box {
    display: flex;
    flex-direction: column;
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 15px;
    background: linear-gradient(135deg, #2c3e50, #4ca1af);
    margin-bottom: 15px;
    border-radius: 10px;
    scroll-behavior: smooth;
}

.chat-message {
    display: flex;
    margin: 5px 0;
}

.my-message { justify-content: flex-end; }
.their-message { justify-content: flex-start; }

.message-bubble {
    background-color: #fff;
    color: #000;
    padding: 10px 15px;
    border-radius: 15px;
    max-width: 70%;
    font-size: 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    word-wrap: break-word;
}

.my-message .message-bubble { background-color: #00c3ff; align-self: flex-end; }

.message-bubble small { display: block; font-size: 11px; margin-top: 5px; color: #333; }
.message-bubble img, .message-bubble video { margin-top: 8px; max-width: 100%; border-radius: 8px; }

/* Chat Form */
.chat-form textarea {
    width: 100%;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 14px;
    resize: vertical;
    margin-bottom: 10px;
}

.chat-form button {
    background-color: #0ae2ff;
    color: #000;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.3s;
}

.chat-form button:hover { background-color: #09cfe6; }
.chat-form input[type="file"] { margin-bottom: 10px; font-size: 14px; }

/* Buttons */
.button, .animated-button {
    display: inline-block;
    margin: 10px auto;
    padding: 10px 15px;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: background 0.3s, transform 0.2s;
}

.animated-button { background: #eee; color: black; border: 1px solid #ccc; }
.animated-button:hover { background-color: #ddd; }

/* Mobile */
@media screen and (max-width: 600px) {
    body { padding: 10px; }
    .main-container { padding: 15px; }
    h2 { font-size: 1.2rem; }
    .chat-box { max-height: 300px; padding: 10px; }
    .message-bubble { font-size: 13px; padding: 8px 12px; max-width: 90%; }
    .chat-form textarea { font-size: 13px; }
    .chat-form button, .animated-button { width: 100%; font-size: 13px; }
}
</style>
</head>
<body>

<div class="main-container">
    <h2><?= htmlspecialchars($chatUser['name']) ?> - <?= getStatusToken($chatUser['last_active']) ?></h2>

    <?php if ($totalMessages > 10): ?>
        <button id="loadMoreBtn" class="animated-button">🔼 Show More</button>
    <?php endif; ?>

    <div id="messageContainer" class="chat-box"></div>

    <form id="chatForm" action="send_message.php" method="POST" enctype="multipart/form-data" class="chat-form">
        <input type="hidden" name="receiver_id" value="<?= htmlspecialchars($chatUserId) ?>">
        <textarea name="message" id="messageInput" rows="3" required placeholder="Type your reply..."></textarea>
        <input type="file" name="media" accept="image/*,video/*"><br>
        <button type="submit" class="btn btn-message">Send</button>
    </form>

    <a href="inbox.php" class="button">📥 Back to Inbox</a>
    <a href="LeagueBook_Page.php" class="button">🏠 Back to Main</a>   
</div>

<script>
const userId = <?= $chatUserId ?>;
const currentUserId = <?= $currentUserId ?>;
let offset = 0, limit = 10, loading = false;
const container = document.getElementById("messageContainer");
const form = document.getElementById("chatForm");
const textarea = document.getElementById("messageInput");

// Add message to chat
function addMessage(msg, prepend=false){
    const div = document.createElement("div");
    div.className = "chat-message " + (msg.sender_id == currentUserId ? "my-message" : "their-message");
    div.innerHTML = `
        <div class="message-bubble">
            <strong>${msg.sender_name}</strong><br>
            ${msg.message.replace(/\n/g,"<br>")}
            <br><small>${msg.created_at}</small>
            ${msg.media_path && msg.media_type === 'image' ? `<br><img src="${msg.media_path}">` : ''}
            ${msg.media_path && msg.media_type === 'video' ? `<br><video controls><source src="${msg.media_path}" type="video/mp4"></video>` : ''}
        </div>
    `;
    prepend ? container.appendChild(div) : container.prepend(div);
}

// Load messages via AJAX
function loadMessages(initial=false){
    if(loading) return;
    loading = true;
    fetch(`private_load_message.php?user_id=${userId}&offset=${offset}`)
        .then(res => res.json())
        .then(data => {
            data.reverse().forEach(msg => addMessage(msg, true));
            offset += limit;
            loading = false;
            if(initial) container.scrollTop = container.scrollHeight;
        });
}

// Initial load
loadMessages(true);
document.getElementById("loadMoreBtn")?.addEventListener("click", ()=>loadMessages());

// Infinite scroll
container.addEventListener("scroll", function(){
    if(this.scrollTop === 0 && offset < <?= $totalMessages ?>) loadMessages();
});

// Submit on Enter
textarea.addEventListener("keydown", e=>{
    if(e.key==="Enter" && !e.shiftKey){ e.preventDefault(); form.requestSubmit(); }
});

// WebSocket
const socket = new WebSocket("ws://localhost:3000");
socket.addEventListener("open", ()=>socket.send(JSON.stringify({ type:"join", user_id: currentUserId })));
socket.addEventListener("message", event => {
    const msg = JSON.parse(event.data);
    if(msg.type==="chat" && ((msg.sender_id==userId && msg.receiver_id==currentUserId) || (msg.sender_id==currentUserId && msg.receiver_id==userId))){
        addMessage(msg);
        container.scrollTop = container.scrollHeight;
    }
});

// AJAX send message
form.addEventListener("submit", e=>{
    e.preventDefault();
    const formData = new FormData(form);
    fetch("send_message.php",{method:"POST", body:formData})
        .then(res=>res.json())
        .then(resp=>{
            if(resp.success){
                addMessage(resp.message);
                socket.send(JSON.stringify({ type:"chat", ...resp.message }));
                form.reset();
                container.scrollTop = container.scrollHeight;
            } else alert("Failed to send message.");
        });
});

// Typing indicator
const typingIndicator = document.createElement("div");
typingIndicator.id = "typing-indicator";
typingIndicator.style.fontSize = "12px";
typingIndicator.style.color = "#555";
document.querySelector(".main-container").appendChild(typingIndicator);

let lastTypingTime = 0, typingTimeout;
textarea.addEventListener("input", ()=>{
    const now = Date.now();
    if(now - lastTypingTime > 1000){
        lastTypingTime = now;
        socket.send(JSON.stringify({
            type:"typing", sender_id: currentUserId, sender_name:"You", receiver_id: userId
        }));
    }
});

socket.addEventListener("message", event=>{
    const msg = JSON.parse(event.data);
    if(msg.type==="typing" && msg.sender_id==userId){
        typingIndicator.innerText = `${msg.sender_name} is typing...`;
        typingIndicator.style.display = "block";
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(()=>typingIndicator.style.display="none",3000);
    }
});
</script>

</body>
</html>

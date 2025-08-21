<?php
session_start();
include("connect.php");

// --- Ensure logged in ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header("Location: LeagueBook.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];

// ✅ Always fetch the user's real name from DB
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($fetchedName);
$stmt->fetch();
$stmt->close();

// If found, update session and use it
if (!empty($fetchedName)) {
    $_SESSION['user_name'] = $fetchedName;
    $userName = $fetchedName;
} else {
    // fallback: keep old session value, else "Guest"
    $userName = $_SESSION['user_name'] ?? 'Guest';
}

// --- Update last_active ---
$update = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
$update->bind_param("i", $userId);
$update->execute();
$update->close();

// --- Unread private messages ---
$unreadCount = 0;
$stmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count 
    FROM private_messages 
    WHERE receiver_id = ? AND is_read = 0
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $unreadCount = (int) $row['unread_count'];
}
$stmt->close();

// --- Pending friend requests ---
$pending = 0;
$checkPending = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM friend_requests 
    WHERE receiver_id = ? AND status = 'pending'
");
$checkPending->bind_param("i", $userId);
$checkPending->execute();
$pendingResult = $checkPending->get_result()->fetch_assoc();
$pending = $pendingResult['total'];
$checkPending->close();

// --- Handle friend request action ---
if (isset($_GET['receiver_id'])) {
    $receiverId = (int) $_GET['receiver_id'];
    if ($receiverId !== $userId) {
        $check = $conn->prepare("
            SELECT 1 
            FROM friend_requests 
            WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'
        ");
        $check->bind_param("ii", $userId, $receiverId);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows > 0) {
            $friendRequestMessage = "⚠️ You already sent a friend request to this user.";
        } else {
            $insert = $conn->prepare("
                INSERT INTO friend_requests (sender_id, receiver_id, status, created_at) 
                VALUES (?, ?, 'pending', NOW())
            ");
            $insert->bind_param("ii", $userId, $receiverId);
            $friendRequestMessage = $insert->execute() 
                ? "✅ Friend request sent successfully." 
                : "❌ Failed to send friend request.";
            $insert->close();
        }
        $check->close();
    } else {
        $friendRequestMessage = "⚠️ You cannot send a friend request to yourself.";
    }
}

// --- Auto mark offline (inactive > 2 mins) ---
$conn->query("UPDATE users 
              SET status = 'offline' 
              WHERE TIMESTAMPDIFF(SECOND, last_active, NOW()) > 120");

// --- Check current user online/offline ---
$getStatus = $conn->prepare("SELECT last_active FROM users WHERE id = ?");
$getStatus->bind_param("i", $userId);
$getStatus->execute();
$user = $getStatus->get_result()->fetch_assoc();
$getStatus->close();

$isOnline = ($user && (time() - strtotime($user['last_active']) <= 120));
$onlineStatus = $isOnline ? "🟢 Online" : "⚫ Offline";

// ==========================
// ✅ Now you have variables:
// $userId, $userName (always real name now)
// $unreadCount, $pending
// $friendRequestMessage (optional)
// $onlineStatus (string)
// ==========================
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
<!-- Chat Toggle Button -->
<?php
// Get total unread messages
$unreadCount = $unreadCount ?? 0; // fallback if not set
?>
<!-- Floating chat toggle button -->
<button id="openChatBtn" class="chat-toggle-btn">💬</button>


<!-- Chat Container with Layers -->
<!-- Friend Chat Layer -->
<div class="chat-container">
  <div id="chatLayers" class="chat-layers"></div>
</div>

<!-- Chat Templates: Mini Windows will be generated dynamically -->


<div id="chatWidget" class="chat-widget">
  <!-- Chat Header -->
  <div class="chat-header">
    <div class="chat-header-left">
      <a id="chatUserAvatarLink" href="#">
        <img id="chatUserAvatar" class="avatar" src="uploads/default-avatar.png" alt="User Avatar">
      </a>
      <span id="chatUserName">Chat</span>
    </div>
    <button id="closeChat" class="chat-close-btn">✕</button>
  </div>

  <!-- Chat Messages -->
  <div id="chatMessages" class="chat-messages"></div>

  <!-- Typing Indicator -->
  <div id="typingIndicator" class="typing-indicator" style="display:none;">
    <span id="typingUserName"></span> is typing...
  </div>

  <!-- Chat Form -->
  <form id="chatFormWidget" class="chat-form">
    <textarea id="chatInput" rows="2" placeholder="Type a message..." autocomplete="off"></textarea>
    <button type="submit" class="chat-send-btn">Send</button>
  </form>
</div> <!-- end chatWidget -->

<!-- 🔽 Place modal right here -->
<div id="deleteModal" class="modal" style="display:none;">
  <div class="modal-content">
    <p>Are you sure you want to delete this message?</p>
    <div class="modal-buttons">
      <button id="confirmDelete" class="btn-yes">Yes</button>
      <button id="cancelDelete" class="btn-no">No</button>
    </div>
  </div>
</div>





<style>
  .chat-header {
    display: flex;
    align-items: center;
    padding: 8px;
    background-color: #f0f0f0;
    border-bottom: 1px solid #ccc;
}

.chat-header .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 10px;
}

.chat-header span {
    font-weight: bold;
    font-size: 16px;
}

/* ==================== Toggle Button ==================== */
.chat-toggle-btn {
  position: fixed;
  bottom: 20px;
  right: 20px;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: #1877f2;
  color: white;
  font-size: 24px;
  cursor: pointer;
  border: none;
  z-index: 1001;
  box-shadow: 0 2px 6px rgba(0,0,0,0.3);
}

/* ==================== Chat Widget ==================== */
.chat-widget {
  position: fixed;
  bottom: 80px;
  right: 20px;
  width: 300px;
  max-height: 400px;
  background: #fff;
  border-radius: 10px;
  display: none;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 5px 20px rgba(0,0,0,0.3);
  font-family: 'Segoe UI', sans-serif;
  z-index: 1000;
}

/* ==================== Chat Header ==================== */
.chat-header {
  background: #1877f2;
  color: #000000ff;
  padding: 10px 12px;
  font-weight: bold;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
}

/* ==================== Messages ==================== */
.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 10px;
  background: #f0f2f5;
  font-size: 14px;
}

.typing-indicator {
  padding: 5px 10px;
  font-size: 12px;
  color: #555;
  font-style: italic;
}

/* ==================== Form ==================== */
.chat-form {
  display: flex;
  border-top: 1px solid #ddd;
  background: #fff;
}

.chat-form textarea {
  flex: 1;
  padding: 8px;
  font-size: 14px;
  border: none;
  resize: none;
}

.chat-form button {
  background: #1877f2;
  border: none;
  color: #fff;
  padding: 0 15px;
  cursor: pointer;
  font-weight: bold;
  transition: background 0.3s;
}

.chat-form button:hover {
  background: #0f6bcc;
}

/* ==================== Message Bubbles ==================== */
.message {
  display: flex;
  margin-bottom: 8px;
  align-items: flex-end;
}

.my-message {
  justify-content: flex-end;
}

.my-message .bubble {
  background: #00c3ff;
  color: #000;
  border-radius: 18px 18px 0 18px;
}

.their-message {
  justify-content: flex-start;
}

.their-message .bubble {
  background: #e4e6eb;
  color: #000;
  border-radius: 18px 18px 18px 0;
}

.bubble {
  max-width: 70%;
  padding: 8px 12px;
  font-size: 14px;
  word-wrap: break-word;
}

.bubble small {
  display: block;
  font-size: 10px;
  color: #555;
  margin-top: 2px;
}

/* ==================== Chat Layers / Avatars ==================== */
.chat-container {
  position: fixed;
  bottom: 20px;
  right: 20px;
  display: flex;
  flex-direction: row-reverse;
  gap: 10px;
  z-index: 1000;
  
}

/* Container for all chat layers */
.chat-layers {
  position: fixed;
  top: 200px;      /* distance from top */
  left: 20px;      /* distance from left edge */
  display: flex;
  flex-direction: column-reverse; /* new chats appear on top */
  gap: 10px;
  z-index: 1000;   /* make sure it's above content */
}



/* Avatar smaller */
.chat-layer .avatar {
  width: 25px;
  height: 25px;
  border-radius: 50%;
}

/* Username on left */
.chat-layer .name {
  order: -1;  /* moves name before avatar */
  font-size: 14px;
}

/* Badge on far right */
.chat-layer .badge {
  background: red;
  color: #fff;
  border-radius: 50%;
  padding: 2px 6px;
  font-size: 12px;
  margin-left: auto;
}

/* Hover effect */
.chat-layer:hover {
  transform: translateX(5px);
}
.message-container {
    display: flex;
    align-items: flex-end;
    margin-bottom: 10px;
}

.my-message {
    justify-content: flex-end;
}

.their-message {
    justify-content: flex-start;
}

.message-container .avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    margin: 0 8px;
}

.bubble {
    max-width: 70%;
    padding: 10px;
    border-radius: 18px;
    background: #e4e6eb;
    word-wrap: break-word;
}

.my-message .bubble {
    background: #0084ff;
    color: #fff;
    border-bottom-right-radius: 0;
}

.their-message .bubble {
    background: #e4e6eb;
    color: #000;
    border-bottom-left-radius: 0;
}
.typing-indicator {
  display: flex;
  align-items: center;
  font-style: italic;
  color: black;
  margin: 5px 10px;
}

.typing-indicator .dot {
  animation: blink 1.4s infinite both;
  margin-left: 2px;
}

.typing-indicator .dot:nth-child(3) { animation-delay: 0s; }
.typing-indicator .dot:nth-child(4) { animation-delay: 0.2s; }
.typing-indicator .dot:nth-child(5) { animation-delay: 0.4s; }

@keyframes blink {
  0%, 80%, 100% { opacity: 0; }
  40% { opacity: 1; }
}

.message-container a {
    display: inline-block;
}

.message-container a img.avatar {
    cursor: pointer;
}
.modal {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.modal-content {
  background: #000000ff;
  padding: 20px;
  border-radius: 12px;
  text-align: center;
  width: 280px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

.modal-buttons {
  margin-top: 15px;
  display: flex;
  justify-content: space-around;
}

.btn-yes {
  background: #e74c3c;
  color: #fff;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
}

.btn-no {
  background: #bdc3c7;
  color: #333;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
}

</style>

<script>

// ================= Variables =================
// ================= Variables =================
const chatLayers        = document.getElementById("chatLayers");
const chatWidget        = document.getElementById("chatWidget");
const openChatBtn       = document.getElementById("openChatBtn");
const closeChatBtn      = document.getElementById("closeChat");
const chatForm          = document.getElementById("chatFormWidget");
const chatInput         = document.getElementById("chatInput");
const chatMessages      = document.getElementById("chatMessages");
const chatUserName      = document.getElementById("chatUserName");
const typingIndicator   = document.getElementById("typingIndicator");
const myAvatarEl        = document.getElementById("myAvatar");
const myNameEl          = document.getElementById("myName");
const chatUserAvatarEl  = document.getElementById("chatUserAvatar");
const typingUserName    = document.getElementById("typingUserName");

let currentUserId   = <?= json_encode($_SESSION['user_id'] ?? 0) ?>;
let currentUserName = <?= json_encode($_SESSION['user_name'] ?? "Guest") ?>;
let activeChatId    = null;
let openChats       = {}; 
let myProfile       = { id: currentUserId, name: currentUserName, avatar: "uploads/default-avatar.png" };

// ================= Load My Profile =================
document.addEventListener("DOMContentLoaded", () => {
    fetch("get_my_profile.php")
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                myProfile = {
                    id: data.id,
                    name: data.name,
                    avatar: data.avatar || "uploads/default-avatar.png"
                };

                if (myAvatarEl) myAvatarEl.src = myProfile.avatar;
                if (myNameEl) myNameEl.innerText = myProfile.name;
            } else {
                console.error("Failed to load profile:", data.message);
            }
        })
        .catch(err => console.error("Error fetching profile:", err));
});

// ================= WebSocket =================
const socket = new WebSocket("ws://localhost:8080");

socket.addEventListener("open", () => {
    socket.send(JSON.stringify({ type: "init", user_id: currentUserId }));
});

socket.addEventListener("message", event => {
    const msg = JSON.parse(event.data);
    if (!msg.type) return;

    switch(msg.type) {

        // --- Chat message ---
        case "chat":
            const otherUser = msg.sender_id === currentUserId ? msg.receiver_id : msg.sender_id;
            if (!openChats[otherUser]) {
                openChats[otherUser] = { 
                    messages: [], 
                    name: msg.sender_name || "User", 
                    avatar: msg.sender_avatar || 'uploads/default-avatar.png' 
                };
            }
            openChats[otherUser].messages.push(msg);

            if (activeChatId === otherUser) {
                renderMessages();         
                removeBadge(otherUser);   
                markMessagesRead(otherUser);
            } else if (msg.sender_id !== currentUserId) {
                incrementBadge(otherUser);
            }
            break;

        // --- Typing indicators ---
        case "typing":
            if (activeChatId === msg.sender_id) {
                typingIndicator.style.display = "block";
                typingIndicator.innerText = `${msg.sender_name} is typing...`;
                clearTimeout(window.typingTimeout);
                window.typingTimeout = setTimeout(() => {
                    typingIndicator.style.display = "none";
                }, 2000);
            }
            break;

        case "stop_typing":
            if (msg.sender_id === activeChatId) {
                typingIndicator.style.display = "none";
            }
            break;

        // --- Real-time deletion ---
    case "delete_message":
    const div = document.querySelector(`#msg-${msg.message_id}`);
    if (div) div.remove();

    if (activeChatId && openChats[activeChatId]) {
        openChats[activeChatId].messages = openChats[activeChatId].messages.filter(
            m => m.message_id !== msg.message_id   // ✅ consistent
        );
    }
    break;


        // --- Add other cases below if needed ---
    }
});


// ================= Open Chat =================
function openChatWithUser(userId, userName, userAvatar = 'uploads/default-avatar.png') {
    activeChatId = userId;
    chatUserName.innerText = userName;
    chatWidget.style.display = "flex";

    const chatHeaderLink   = document.getElementById("chatUserAvatarLink");
    const chatHeaderAvatar = document.getElementById("chatUserAvatar");
    if(chatHeaderLink)   chatHeaderLink.href = `view_profile.php?id=${userId}`;
    if(chatHeaderAvatar) chatHeaderAvatar.src = userAvatar;

    removeBadge(userId);

    if (!openChats[userId]) openChats[userId] = { messages: [], name: userName, avatar: userAvatar };

    if (openChats[userId].messages.length === 0) {
        fetch(`private_load_message.php?user_id=${userId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    openChats[userId].messages = data.messages;
                    renderMessages();
                    if (data.messages.length > 0) markMessagesRead(userId);

                    const firstMsg = data.messages.find(m => m.sender_id === userId);
                    if (firstMsg && firstMsg.sender_avatar) {
                        openChats[userId].avatar = firstMsg.sender_avatar;
                        if(chatHeaderAvatar) chatHeaderAvatar.src = firstMsg.sender_avatar;
                    }
                }
            });
    } else {
        renderMessages();
    }
}

// ================= Render Messages =================
function renderMessages() {
    if (!activeChatId || !openChats[activeChatId]) return;
    chatMessages.innerHTML = "";

    openChats[activeChatId].messages.forEach(msg => renderMessage(msg));
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function renderMessage(msg, save = false) {
    if (!msg) return;

    const div = document.createElement("div");
    div.id = `msg-${msg.message_id}`; // ✅ use message_id consistently
    div.className = "message " + (msg.sender_id === currentUserId ? "my-message" : "their-message");

    const avatarSrc = msg.sender_avatar || (msg.sender_id === currentUserId ? myProfile.avatar : 'uploads/default-avatar.png');

    div.innerHTML = `
        <div class="message-container">
            ${msg.sender_id !== currentUserId 
                ? `<img class="avatar" src="${avatarSrc}" alt="avatar" onclick="window.location.href='view_profile.php?id=${msg.sender_id}'">` 
                : ""}

     <div class="bubble">
    ${msg.sender_id !== currentUserId ? `<strong>${msg.sender_name}</strong><br>` : ""}
    <div class="msg-text">${msg.message.replace(/\n/g, "<br>")}</div>
    ${msg.media_path && msg.media_type === "image" ? `<br><img src="${msg.media_path}" style="max-width:100%;">` : ""}
    ${msg.media_path && msg.media_type === "video" ? `<br><video controls style="max-width:100%;"><source src="${msg.media_path}" type="video/mp4"></video>` : ""}
    <br><small>${msg.created_at}</small>

    <div class="msg-actions">
        <!-- ✅ Reply button here -->
        <button class="btn-reply" 
            data-msgid="${msg.id}" 
            data-sender="${msg.sender_name || ''}" 
            data-text="${msg.message || ''}">↩ Reply</button>

        <!-- ✅ Only show delete button if it’s my message -->
        ${msg.sender_id === currentUserId 
            ? `<button class="btn-delete" data-msgid="${msg.id}">🗑 Delete</button>` 
            : ""}
    </div>
</div>


            ${msg.sender_id === currentUserId 
                ? `<img class="avatar" src="${avatarSrc}" alt="avatar" onclick="window.location.href='view_profile.php?id=${msg.sender_id}'">` 
                : ""}
        </div>
    `;

    chatMessages.appendChild(div);
    chatMessages.scrollTop = chatMessages.scrollHeight;

    if (save && activeChatId) {
        openChats[activeChatId].messages.push(msg);
    }

    // --- Reply ---
    const replyBtn = div.querySelector(".btn-reply");
    if (replyBtn) {
        replyBtn.addEventListener("click", () => {
            chatInput.value = `@${replyBtn.dataset.sender}: ${replyBtn.dataset.text}\n` + chatInput.value;
            chatInput.focus();
        });
    }

    // --- Delete with modal & real-time ---
    const deleteBtn = div.querySelector(".btn-delete");
    if (deleteBtn) {
        deleteBtn.addEventListener("click", () => {
            const modal = document.getElementById("deleteModal");
            const confirmBtn = document.getElementById("confirmDelete");
            const cancelBtn = document.getElementById("cancelDelete");

            modal.style.display = "flex";

            confirmBtn.onclick = null;
            cancelBtn.onclick = null;

            confirmBtn.onclick = () => {
                // 1️⃣ Notify WebSocket
                if (socket && socket.readyState === WebSocket.OPEN) {
                    socket.send(JSON.stringify({
                        type: "delete_message",
                        message_id: deleteBtn.dataset.msgid,   // ✅ consistent
                        sender_id: currentUserId,
                        receiver_id: activeChatId
                    }));
                }

                // 2️⃣ Delete from DB
                fetch("delete_message.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ message_id: deleteBtn.dataset.msgid })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        div.remove();
                        if (activeChatId && openChats[activeChatId]) {
                            openChats[activeChatId].messages = openChats[activeChatId].messages.filter(
                                m => m.message_id != deleteBtn.dataset.msgid   // ✅ fixed
                            );
                        }
                    } else {
                        console.error("Delete failed:", data);
                        alert(data.message || "Failed to delete message");
                    }
                })
                .catch(err => {
                    console.error("Error deleting:", err);
                    alert("Server error while deleting message");
                })
                .finally(() => {
                    modal.style.display = "none";
                });
            };

            cancelBtn.onclick = () => {
                modal.style.display = "none";
            };
        });
    }
}


// ================= Send Message =================
if (chatForm) {
    chatForm.addEventListener("submit", e => {
        e.preventDefault();
        const text = chatInput.value.trim();
        const fileInput = chatForm.querySelector("input[type='file']");
        if (!text && (!fileInput || !fileInput.files.length)) return;

        const formData = new FormData();
        formData.append("receiver_id", activeChatId);
        formData.append("message", text);
        if (fileInput && fileInput.files.length > 0) {
            formData.append("media", fileInput.files[0]);
        }

        fetch("send_message.php", { method: "POST", body: formData })
            .then(res => res.json())
            .then(resp => {
                if (resp.success && resp.message) {
                    chatInput.value = "";
                    if (fileInput) fileInput.value = "";
                    socket.send(JSON.stringify({ type: "chat", ...resp.message }));
                }
            });
    });
}

// ================= Enter key shortcut =================
if (chatInput) {
    chatInput.addEventListener("keydown", e => {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event("submit"));
        }

        // Send typing event while typing
        socket.send(JSON.stringify({
            type: "typing",
            sender_id: currentUserId,
            sender_name: currentUserName,
            receiver_id: activeChatId
        }));

        clearTimeout(window.typingTimeout);
        window.typingTimeout = setTimeout(() => {
            socket.send(JSON.stringify({
                type: "stop_typing",
                sender_id: currentUserId,
                receiver_id: activeChatId
            }));
        }, 2000);
    });
}

// ================= Friends List =================
fetch("get_friends_chats.php")
    .then(res => res.json())
    .then(friends => {
        friends.forEach(friend => {
            const div = document.createElement("div");
            div.className = "chat-layer";
            div.dataset.userId = friend.user_id;
            div.dataset.userName = friend.user_name;
            div.innerHTML = `
                <img src="${friend.avatar}" class="avatar">
                <span class="name">${friend.user_name}</span>
                ${friend.unread > 0 ? `<span class="badge">${friend.unread}</span>` : ""}
            `;
            chatLayers.appendChild(div);

            div.addEventListener("click", () => openChatWithUser(friend.user_id, friend.user_name, friend.avatar));
        });
    });

// ================= Badge Helpers =================
function incrementBadge(userId) {
    const layer = document.querySelector(`.chat-layer[data-user-id='${userId}']`);
    if (!layer) return;
    let badge = layer.querySelector(".badge");
    if (!badge) {
        badge = document.createElement("span");
        badge.className = "badge";
        badge.textContent = "1";
        layer.appendChild(badge);
    } else {
        badge.textContent = parseInt(badge.textContent) + 1;
    }
}

function removeBadge(userId) {
    const layer = document.querySelector(`.chat-layer[data-user-id='${userId}']`);
    if (!layer) return;
    const badge = layer.querySelector(".badge");
    if (badge) badge.remove();
}

function markMessagesRead(userId) {
    fetch("mark_messages_read.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ sender_id: userId })
    });
}

// ================= Inbox Badge Update =================
function updateInboxBadge() {
    fetch("get_unread_count.php")
        .then(res => res.json())
        .then(data => {
            const inboxLink = document.querySelector("a[href='inbox.php']");
            if (inboxLink) {
                inboxLink.innerHTML = `📩 Inbox ${data.total_unread > 0 ? `<span class='badge' style='color:red;'>${data.total_unread}</span>` : ""}`;
            }
        });
}
setInterval(updateInboxBadge, 5000);





</script>

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
<div id="reelsContainer" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); z-index:999999; padding:20px; display:flex; justify-content:center; align-items:center;">
    <div id="videoWrapper" style="position:relative; max-width:70%; width:100%;">
        <div id="reelContent" style="text-align:center;"></div>

        <!-- Navigation Buttons -->
        <div id="reelNav" style="position:absolute; right:-60px; top:50%; transform:translateY(-50%); display:flex; flex-direction:column; gap:10px;">
            <button onclick="prevReel()" style="padding:8px 12px;">⬆️</button>
            <button onclick="nextReel()" style="padding:8px 12px;">⬇️</button>
        </div>

        <!-- Right-side Action Buttons -->
        <div id="reelActions" style="position:absolute; right:-130px; top:50%; transform:translateY(-50%); display:flex; flex-direction:column; gap:15px; color:white; text-align:center;">
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
    <button onclick="closeReels()" style="position:fixed; top:20px; right:20px; z-index:1000001; background:red; color:white; border:none; padding:8px 12px; border-radius:6px; cursor:pointer;">❌ Close</button>
</div>

<!-- Comment Panel -->
<div id="commentPanel" style="display:none; position:fixed; top:40%; right:220px; width:320px; max-height:80%; background:rgba(0,0,0,0.9); color:white; border-radius:10px 0 0 10px; padding:10px; z-index:1000002; flex-direction:column;">
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
    commentList.innerHTML = '<p style="text-align:center;">Loading...</p>';

    fetch(`load_comments.php?post_id=${encodeURIComponent(postId)}`)
        .then(res => res.json())
        .then(data => {
            commentList.innerHTML = '';
            if (!data.success || !data.comments.length) {
                commentList.innerHTML = `<p style="text-align:center;">No comments yet.</p>`;
                return;
            }
            data.comments.forEach(c => renderComment(c, commentList));
        })
        .catch(err => {
            console.error("Load comments error:", err);
            commentList.innerHTML = `<p style="text-align:center; color:red;">Failed to load comments.</p>`;
        });
}
function renderComment(c, container) {
    const div = document.createElement('div');
    div.className = 'comment';
    div.setAttribute('data-id', c.id);
    div.style.marginBottom = '8px';
    div.style.borderBottom = '1px solid rgba(255,255,255,0.2)';
    div.style.paddingBottom = '4px';

    div.innerHTML = `
        <strong style="color:#1877f2;">${c.user_name}</strong> 
        <span class="timestamp" data-created="${c.created_at}" style="font-size:0.75em; color:#ccc; margin-left:5px;">
            ${timeAgo(c.created_at)}
        </span>
        <p>${c.content}</p>
        <span class="reply-button" style="cursor:pointer; color:#ccc;" onclick="showReplyInput(${c.id})">Reply</span>
        <div class="replies" style="margin-left:15px;"></div>
    `;

    container.appendChild(div);

    // Render replies recursively
    if (c.replies && c.replies.length) {
        c.replies.forEach(r => renderComment(r, div.querySelector('.replies')));
    }
}


function showReplyInput(commentId) {
    const commentDiv = document.querySelector(`.comment[data-id="${commentId}"]`);
    if (!commentDiv) return;

    if (commentDiv.querySelector('.replyInput')) return;

    const replyDiv = document.createElement('div');
    replyDiv.className = 'replyInput';
    replyDiv.style.marginTop = '5px';
    replyDiv.style.display = 'flex';
    replyDiv.style.gap = '5px';
    replyDiv.innerHTML = `
        <input type="text" placeholder="Write a reply..." style="flex:1; padding:4px 6px; border-radius:15px; border:1px solid #ccc;">
        <button style="padding:4px 8px; border-radius:15px; background:#1877f2; color:white; border:none;">Send</button>
    `;
    commentDiv.appendChild(replyDiv);

    const input = replyDiv.querySelector('input');
    const btn = replyDiv.querySelector('button');
    input.focus();

    input.addEventListener('keypress', e => { if (e.key === 'Enter') btn.click(); });

    btn.addEventListener('click', () => {
        const text = input.value.trim();
        if (!text) return;
        replyToComment(commentId, text);
        replyDiv.remove();
    });
}

function replyToComment(commentId, text) {
    postComment(currentPostId, text, commentId, newComment => {
        const repliesDiv = document.querySelector(`.comment[data-id="${commentId}"] .replies`);
        renderComment(newComment, repliesDiv);
        repliesDiv.scrollTop = repliesDiv.scrollHeight; // auto-scroll
    });
}

function postComment(postId, content, parentId = 0, callback = null) {
    fetch('post_comment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ post_id: postId, content: content, parent_id: parentId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (callback) callback(data.new_comment);
            else loadComments(postId);
            if (parentId === 0) commentInput.value = '';
            commentList.scrollTop = commentList.scrollHeight; // scroll to bottom
        } else alert(data.message || "Failed to post comment");
    })
    .catch(err => {
        console.error("Post comment error:", err);
        alert("Failed to post comment");
    });
}
function timeAgo(datetime) {
    const now = new Date();
    const past = new Date(datetime);
    let diff = Math.floor((now - past)/1000);
    if (diff < 0) diff = 0;

    if (diff < 60) return `${diff} sec${diff !== 1 ? 's' : ''} ago`;
    if (diff < 3600) {
        const m = Math.floor(diff/60);
        return `${m} min${m !== 1 ? 's' : ''} ago`;
    }
    if (diff < 86400) {
        const h = Math.floor(diff/3600);
        return `${h} hr${h !== 1 ? 's' : ''} ago`;
    }
    const d = Math.floor(diff/86400);
    return `${d} day${d !== 1 ? 's' : ''} ago`;
}
setInterval(() => {
    document.querySelectorAll('.comment .timestamp').forEach(el => {
        const datetime = el.getAttribute('data-created');
        el.textContent = timeAgo(datetime);
    });
}, 1000);
// Unified submit function
function submitComment() {
    const text = commentInput.value.trim();
    if (!text || !currentPostId) return;

    postComment(currentPostId, text, 0, newComment => {
        renderComment(newComment, commentList);
        commentInput.value = ""; // clear input
    });
}

// Enter key to submit (without Shift)
commentInput.addEventListener("keydown", e => {
    if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        submitComment();
    }
});


commentSubmit.addEventListener('click', () => {
    const text = commentInput.value.trim();
    if (!text || !currentPostId) return;
    postComment(currentPostId, text, 0, newComment => {
        renderComment(newComment, commentList);
    });
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
/* Single Comment */
.comment {
    background: #000000ff; /* FB white */
    padding: 8px 10px;
    border-radius: 12px;
    margin-bottom: 8px;
    transition: background 0.2s;
    color: #ffffffff; /* comment text color */
}

/* Username */
.comment strong {
    color: #050505; /* dark for visibility */
    font-weight: 600;
}

/* Time Ago */
.comment span.timeAgo {
    font-size: 0.75em;
    color: #65676b; /* FB gray */
    margin-left: 5px;
}

/* Comment Content */
.comment p {
    margin: 4px 0 0 0;
    line-height: 1.3;
    color: #ffffffff;
}

/* Reply Button */
.comment .reply-button {
    font-size: 0.85em;
    color: #65676b; /* FB gray */
    cursor: pointer;
    margin-top: 4px;
    display: inline-block;
}

.comment .reply-button:hover {
    color: #1877f2; /* FB blue */
}

/* Replies */
.comment .replies {
    margin-left: 15px;
}

/* Reply Input */
.replyInput {
    display: flex;
    gap: 5px;
    margin-top: 5px;
}

.replyInput input {
    flex: 1;
    padding: 6px 10px;
    border-radius: 20px;
    border: 1px solid #ccd0d5;
    outline: none;
    background: #f0f2f5; /* light gray */
    color: #050505; /* visible text */
}

.replyInput input:focus {
    border-color: #1877f2;
    background: #fff;
    color: #050505;
}

/* Reply Send Button */
.replyInput button {
    padding: 6px 12px;
    border-radius: 20px;
    background: #1877f2;
    color: #fff;
    border: none;
    cursor: pointer;
    font-weight: 600;
}

.replyInput button:hover {
    background: #165ec0;
}
.comment span.timeAgo {
    color: #fdfeffff; /* almost white */
}
.comment .reply-button {
    color: #ffffffff; /* white */
}
.replyInput input {
    background: #fffefeff; /* very dark */
}
.replyInput input:focus {
    background: #ffffffff;
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
<?php if (!empty($loggedInUserId)): ?>
<a href="view_profile.php?id=<?= $loggedInUserId ?>" id="profileIcon">
    <div class="profile-icon" style="background-image: url('img/user.jpg');"></div>
</a>
<?php endif; ?>



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

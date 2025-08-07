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

$statusStmt = $conn->prepare("SELECT name, last_active FROM users WHERE id = ?");
$statusStmt->bind_param("i", $chatUserId);
$statusStmt->execute();
$statusResult = $statusStmt->get_result();

if ($statusResult->num_rows === 0) {
    die("User not found.");
}

$chatUser = $statusResult->fetch_assoc();

$markRead = $conn->prepare("UPDATE private_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
$markRead->bind_param("ii", $chatUserId, $currentUserId);
$markRead->execute();

$countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM private_messages WHERE 
    (sender_id = ? AND receiver_id = ?) OR 
    (sender_id = ? AND receiver_id = ?)");
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
  body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

  .my-message {
      justify-content: flex-end;
  }

  .their-message {
      justify-content: flex-start;
  }

  .message-bubble {
      background-color: #fff;
      color: #000;
      padding: 10px 15px;
      border-radius: 15px;
      max-width: 70%;
      position: relative;
      font-size: 14px;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
      word-wrap: break-word;
  }

  .my-message .message-bubble {
      background-color: #00c3ff;
      align-self: flex-end;
  }

  .message-bubble small {
      display: block;
      font-size: 11px;
      margin-top: 5px;
      color: #333;
  }

  .message-bubble img,
  .message-bubble video {
      margin-top: 8px;
      max-width: 100%;
      border-radius: 8px;
  }

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

  .chat-form button:hover {
      background-color: #09cfe6;
  }

  .chat-form input[type="file"] {
      margin-bottom: 10px;
      font-size: 14px;
  }

  .button {
      display: inline-block;
      margin-top: 15px;
      padding: 8px 15px;
      background-color: #333;
      color: #fff;
      text-decoration: none;
      border-radius: 5px;
  }

  .animated-button {
      display: block;
      padding: 10px 15px;
      background: #eee;
      border: 1px solid #ccc;
      border-radius: 8px;
      margin: 10px auto;
      cursor: pointer;
      font-size: 14px;
      transition: background 0.3s;
      color: black;
  }

  .animated-button:hover {
      background-color: #ddd;
  }

  @keyframes pop-in {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
  }

  /* ✅ Mobile responsiveness */
  @media screen and (max-width: 600px) {
    body {
      padding: 10px;
    }

    .main-container {
      padding: 15px;
    }

    h2 {
      font-size: 1.2rem;
    }

    .chat-box {
      max-height: 300px;
      padding: 10px;
    }

    .message-bubble {
      font-size: 13px;
      padding: 8px 12px;
      max-width: 90%;
    }

    .chat-form textarea {
      font-size: 13px;
    }

    .chat-form button {
      width: 100%;
      padding: 12px;
    }

    .animated-button {
      width: 100%;
      font-size: 13px;
    }
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

    <script>
        let offset = 0;
        const limit = 10;
        const userId = <?= $chatUserId ?>;
        const currentUserId = <?= $currentUserId ?>;
        let loading = false;

        function loadMessages(initial = false) {
            if (loading) return;
            loading = true;

            fetch(`load_messages.php?user_id=${userId}&offset=${offset}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.length) return;

                    const container = document.getElementById("messageContainer");

                    data.forEach(msg => {
                        const div = document.createElement("div");
                        const isMyMessage = msg.sender_id == currentUserId;
                        div.className = "chat-message " + (isMyMessage ? "my-message" : "their-message");

                        div.innerHTML = `
                            <div class="message-bubble">
                                <strong>${msg.sender_name}</strong><br>
                                ${msg.message.replace(/\n/g, "<br>")}
                                <br><small>${msg.created_at}</small>
                                ${msg.media_path && msg.media_type === 'image' ? `<br><img src="${msg.media_path}" alt="Image">` : ''}
                                ${msg.media_path && msg.media_type === 'video' ? `
                                    <br><video controls>
                                        <source src="${msg.media_path}" type="video/mp4">
                                    </video>` : ''}
                            </div>
                        `;
                        container.prepend(div);
                    });

                    offset += limit;
                    loading = false;

                    if (initial) {
                        container.scrollTop = container.scrollHeight;
                    }
                });
        }

        loadMessages(true);

        document.getElementById("messageContainer").addEventListener("scroll", function () {
            if (this.scrollTop === 0 && offset < <?= $totalMessages ?>) {
                loadMessages();
            }
        });
    </script>

 <form id="chatForm" action="send_message.php" method="POST" enctype="multipart/form-data" class="chat-form">
    <input type="hidden" name="receiver_id" value="<?= htmlspecialchars($chatUserId) ?>">
    <textarea name="message" id="messageInput" rows="3" required placeholder="Type your reply..."></textarea>
    <input type="file" name="media" accept="image/*,video/*"><br>
    <button type="submit" class="btn btn-message">Send</button>
</form>


    <br>
    <a href="inbox.php" class="button">📥 Back to Inbox</a>
    <a href="LeagueBook_Page.php" class="button">🏠 Back to Main</a>   
</div>
<script>
    const textarea = document.getElementById("messageInput");
    const form = document.getElementById("chatForm");

    textarea.addEventListener("keydown", function (e) {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault(); // Prevent newline
            form.requestSubmit(); // Submit the form
        }
    });
</script>

<script>
const userId = <?= $chatUserId ?>;
const currentUserId = <?= $currentUserId ?>;
let offset = 0;
const limit = 10;
let loading = false;
const messageContainer = document.getElementById("messageContainer");

function addMessage(msg, prepend = false) {
    const div = document.createElement("div");
    div.className = "chat-message " + (msg.sender_id == currentUserId ? "my-message" : "their-message");
    div.innerHTML = `
        <div class="message-bubble">
            <strong>${msg.sender_name}</strong><br>
            ${msg.message.replace(/\n/g, "<br>")}
            <br><small>${msg.created_at}</small>
            ${msg.media_path && msg.media_type === 'image' ? `<br><img src="${msg.media_path}">` : ''}
            ${msg.media_path && msg.media_type === 'video' ? `<br><video controls><source src="${msg.media_path}" type="video/mp4"></video>` : ''}
        </div>
    `;
    prepend ? messageContainer.appendChild(div) : messageContainer.prepend(div);
}

function loadMessages(initial = false) {
    if (loading) return;
    loading = true;

    fetch(`load_messages.php?user_id=${userId}&offset=${offset}`)
        .then(res => res.json())
        .then(data => {
            data.reverse().forEach(msg => addMessage(msg, true));
            offset += limit;
            loading = false;
            if (initial) {
                messageContainer.scrollTop = messageContainer.scrollHeight;
            }
        });
}

document.getElementById("loadMoreBtn").addEventListener("click", () => {
    loadMessages();
});

messageContainer.addEventListener("scroll", function () {
    if (this.scrollTop === 0 && offset < <?= $totalMessages ?>) {
        loadMessages();
    }
});

loadMessages(true);

// 🧠 WebSocket Client
const socket = new WebSocket("ws://localhost:3000");

socket.addEventListener("open", () => {
    socket.send(JSON.stringify({ type: "join", user_id: currentUserId }));
});

socket.addEventListener("message", event => {
    const msg = JSON.parse(event.data);
    if (msg.type === "chat" && ((msg.sender_id == userId && msg.receiver_id == currentUserId) || (msg.sender_id == currentUserId && msg.receiver_id == userId))) {
        addMessage(msg);
    }
});

// 🧾 AJAX Form for Sending Message + Emit to WebSocket
document.getElementById("chatForm").addEventListener("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch("send_message.php", {
        method: "POST",
        body: formData
    }).then(res => res.json()).then(response => {
        if (response.success) {
            addMessage(response.message); // Append immediately
            socket.send(JSON.stringify({
                type: "chat",
                ...response.message
            }));
            this.reset();
        } else {
            alert("Failed to send message.");
        }
    });
});
function scrollToBottom() {
  const container = document.getElementById("messageContainer");
  container.scrollTop = container.scrollHeight;
}


function addMessage(data) {
  const messageDiv = document.createElement("div");
  messageDiv.textContent = `${data.sender_name}: ${data.message}`;
  document.getElementById("message-container").appendChild(messageDiv);
  scrollToBottom(); // 👈 always scroll after new message
}

window.addEventListener("load", scrollToBottom);
const typingIndicator = document.getElementById("typing-indicator");
let typingTimeout;

socket.addEventListener("message", event => {
    const msg = JSON.parse(event.data);

    if (msg.type === "typing" && msg.sender_id == userId) {
        typingIndicator.innerText = `${msg.sender_name} is typing...`;
        typingIndicator.style.display = "block";

        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            typingIndicator.style.display = "none";
        }, 3000);
    }
});
let lastTypingTime = 0;
textarea.addEventListener("input", () => {
  const now = Date.now();
  if (now - lastTypingTime > 1000) {
    lastTypingTime = now;
    socket.send(JSON.stringify({
      type: "typing",
      sender_id: currentUserId,
      sender_name: "You", // Replace with dynamic if needed
      receiver_id: userId
    }));
  }
});

</script>
</body>
</html>

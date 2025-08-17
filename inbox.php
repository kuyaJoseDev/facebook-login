<?php
session_start();
include("connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: LeagueBook.php");
    exit();
}

$currentUserId = $_SESSION['user_id'];

// Get total unread messages
$unreadQuery = $conn->prepare("
    SELECT COUNT(*) AS total_unread 
    FROM private_messages 
    WHERE receiver_id = ? AND is_read = 0
");
$unreadQuery->bind_param("i", $currentUserId);
$unreadQuery->execute();
$unreadResult = $unreadQuery->get_result();
$unreadCount = $unreadResult->fetch_assoc()['total_unread'] ?? 0;

// Get list of users for inbox
$usersResult = $conn->query("SELECT id, username FROM users WHERE id != $currentUserId");
$users = [];
while($row = $usersResult->fetch_assoc()) {
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>🏠 LeagueBook Inbox</title>
<link rel="stylesheet" href="LeagueBook_Page.css">
<style>
/* Same CSS as before */
</style>
</head>
<body>

<h2>Inbox Users (<?= $unreadCount ?> unread)</h2>
<div id="userList">
<?php foreach($users as $user): ?>
    <div class="user">
        <?= htmlspecialchars($user['username']) ?>
        <button class="open-floating-chat" data-user-id="<?= $user['id'] ?>" data-user-name="<?= htmlspecialchars($user['username']) ?>">💬</button>
    </div>
<?php endforeach; ?>
</div>

<!-- Floating Chat Button -->
<button id="openChatBtn" class="chat-toggle-btn">💬</button>

<!-- Chat Widget -->
<div id="chatWidget" class="chat-widget">
    <div class="chat-header">
        <span id="chatUserName">Chat</span>
        <button id="closeChat">✕</button>
    </div>
    <div id="chatMessages" class="chat-messages"></div>
    <div id="typingIndicator" class="typing-indicator">Typing...</div>
    <form id="chatFormWidget" class="chat-form">
        <textarea id="chatInput" rows="2" placeholder="Type a message..."></textarea>
        <button type="submit">Send</button>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const chatWidget = document.getElementById("chatWidget");
    const openChatBtn = document.getElementById("openChatBtn");
    const closeChatBtn = document.getElementById("closeChat");
    const chatFormWidget = document.getElementById("chatFormWidget");
    const chatInput = document.getElementById("chatInput");
    const chatMessages = document.getElementById("chatMessages");
    const chatUserName = document.getElementById("chatUserName");
    const typingIndicator = document.getElementById("typingIndicator");
    const currentUserId = <?= $currentUserId ?>;

    let activeChatId = null;
    let openChats = {};

    // Toggle widget
    openChatBtn.addEventListener("click", () => chatWidget.style.display="flex");
    closeChatBtn.addEventListener("click", () => chatWidget.style.display="none");

    // Open chat with a user
    document.querySelectorAll(".open-floating-chat").forEach(btn => {
        btn.addEventListener("click", () => {
            const userId = btn.dataset.userId;
            const userName = btn.dataset.userName;
            activeChatId = userId;
            chatUserName.innerText = userName;
            chatWidget.style.display="flex";

            if (!openChats[userId]) openChats[userId] = {name:userName, messages:[]};

            if(openChats[userId].messages.length===0){
                fetch(`private_load_message.php?user_id=${userId}`)
                    .then(res=>res.json())
                    .then(data=>{
                        data.reverse().forEach(msg=>openChats[userId].messages.push(msg));
                        renderActiveChat();
                    });
            } else {
                renderActiveChat();
            }
        });
    });

    function renderActiveChat(){
        if(!activeChatId) return;
        chatMessages.innerHTML="";
        openChats[activeChatId].messages.forEach(msg=>renderMessage(msg, false));
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function renderMessage(msg, save=true){
        const div = document.createElement("div");
        div.className = "message " + (msg.sender_id==currentUserId?"my-message":"their-message");
        div.innerHTML = `<div class="bubble">
            <strong>${msg.sender_name || ""}</strong><br>
            ${msg.message ? msg.message.replace(/\n/g,"<br>") : ""}
            <br><small>${msg.created_at || ""}</small>
            ${msg.media_path && msg.media_type==='image'?`<br><img src="${msg.media_path}">`:""}
            ${msg.media_path && msg.media_type==='video'?`<br><video controls><source src="${msg.media_path}" type="video/mp4"></video>`:""}
        </div>`;
        chatMessages.appendChild(div);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        if(save) openChats[activeChatId].messages.push(msg);
    }

    // Send message
    chatFormWidget.addEventListener("submit", e=>{
        e.preventDefault();
        if(!chatInput.value.trim() || !activeChatId) return;
        const formData=new FormData(chatFormWidget);
        formData.set("receiver_id", activeChatId);
        fetch("send_message.php",{method:"POST", body:formData})
            .then(res=>res.json())
            .then(resp=>{
                if(resp.success){
                    const message = {type:"chat", ...resp.message};
                    renderMessage(message);
                    chatInput.value='';
                    socket.send(JSON.stringify(message));
                }
            });
    });

    // WebSocket
    const socket = new WebSocket("ws://localhost:3000");
    socket.addEventListener("open", ()=>socket.send(JSON.stringify({type:"join", user_id:currentUserId})));
    socket.addEventListener("message", event=>{
        const msg=JSON.parse(event.data);

        if(msg.type==="chat"){
            const otherUser = msg.sender_id==currentUserId ? msg.receiver_id : msg.sender_id;
            if(!openChats[otherUser]) openChats[otherUser]={name:"User", messages:[]};
            openChats[otherUser].messages.push(msg);
            if(activeChatId==otherUser) renderActiveChat();
        }

        if(msg.type==="typing" && msg.sender_id!=currentUserId && msg.sender_id==activeChatId){
            typingIndicator.innerText = `${msg.sender_name} is typing...`;
            typingIndicator.style.display="block";
            clearTimeout(window.typingTimeout);
            window.typingTimeout = setTimeout(()=>typingIndicator.style.display="none",2000);
        }
    });

    // Typing indicator (self → server)
    chatInput.addEventListener("input", ()=>{
        if(!activeChatId) return;
        socket.send(JSON.stringify({
            type:"typing", sender_id:currentUserId, sender_name:"You", receiver_id:activeChatId
        }));
    });
});
</script>

</body>
</html>

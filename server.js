// server.js
const WebSocket = require("ws");

const wss = new WebSocket.Server({ port: 8080 });
console.log("✅ WebSocket server running on ws://localhost:8080");

// Connected clients: { userId: ws }
let clients = {};

// Helper: send JSON safely
function sendJSON(ws, data) {
    if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify(data));
    }
}

wss.on("connection", (ws) => {
    console.log("🔗 New client connected");

    ws.on("message", (message) => {
        try {
            const data = JSON.parse(message);

            // ✅ User registers
            if (data.type === "register") {
                clients[data.user_id] = ws;
                ws.user_id = data.user_id;
                console.log(`✅ User ${data.user_id} registered`);
                sendJSON(ws, { type: "registered", user_id: data.user_id });
                return;
            }

            // ✅ Typing indicator
            if (data.type === "typing" || data.type === "stop_typing") {
                const target = clients[data.receiver_id];
                sendJSON(target, data);
                return;
            }

            // ✅ Chat messages
            if (data.type === "chat_message") {
                const target = clients[data.receiver_id];
                if (target) {
                    sendJSON(target, data);
                    sendJSON(ws, { type: "delivered", message_id: data.message_id });
                } else {
                    sendJSON(ws, { type: "failed", reason: "Receiver offline", message_id: data.message_id });
                }
                return;
            }

            // ✅ Real-time deletion
            if (data.type === "delete_message") {
                const { message_id, sender_id, receiver_id } = data;

                // Send deletion event to both sender and receiver if online
                [sender_id, receiver_id].forEach(userId => {
                    const client = clients[userId];
                    if (client) {
                        sendJSON(client, {
                            type: "delete_message",
                            message_id,
                            sender_id,
                            receiver_id
                        });
                    }
                });

                return;
            }

        } catch (err) {
            console.error("❌ Error parsing message:", err);
            sendJSON(ws, { type: "error", message: "Invalid JSON" });
        }
    });

    ws.on("close", () => {
        if (ws.user_id) {
            delete clients[ws.user_id];
            console.log(`❌ User ${ws.user_id} disconnected`);

            for (let id in clients) {
                sendJSON(clients[id], { type: "user_offline", user_id: ws.user_id });
            }
        }
    });

    ws.on("error", (err) => {
        console.error("⚠️ WebSocket error:", err);
    });
});

// server.js
const WebSocket = require("ws");

const wss = new WebSocket.Server({ port: 8080 });
console.log("✅ WebSocket server running on ws://localhost:8080");

let clients = {}; 
// Structure: { userId: ws }

wss.on("connection", (ws) => {
    console.log("🔗 New client connected");

    ws.on("message", (message) => {
        try {
            const data = JSON.parse(message);

            // Register the user when they connect
            if (data.type === "register") {
                clients[data.user_id] = ws;
                ws.user_id = data.user_id;
                console.log(`✅ User ${data.user_id} registered`);
                return;
            }

            // Forward typing or stop_typing to the correct receiver
            if (data.type === "typing" || data.type === "stop_typing") {
                const target = clients[data.receiver_id];
                if (target && target.readyState === WebSocket.OPEN) {
                    target.send(JSON.stringify(data));
                }
            }

            // Handle normal chat messages
            if (data.type === "chat_message") {
                const target = clients[data.receiver_id];
                if (target && target.readyState === WebSocket.OPEN) {
                    target.send(JSON.stringify(data));
                }
            }

        } catch (err) {
            console.error("❌ Error parsing message:", err);
        }
    });

    ws.on("close", () => {
        if (ws.user_id) {
            delete clients[ws.user_id];
            console.log(`❌ User ${ws.user_id} disconnected`);
        }
    });
});

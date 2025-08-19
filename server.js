const WebSocket = require("ws");
const net = require("net");

// --- WebSocket server for browser clients ---
const wss = new WebSocket.Server({ port: 3000 });
let clients = {}; // user_id -> ws

wss.on("connection", ws => {

    ws.on("message", raw => {
        let msg;
        try { msg = JSON.parse(raw); } catch { return; }

        // --- Register user ---
        if (msg.type === "init" && msg.user_id) {
            clients[msg.user_id] = ws;
            ws.user_id = msg.user_id;
            console.log(`User joined: ${msg.user_id}`);
        }

        // --- Typing indicator ---
        if (msg.type === "typing" && msg.sender_id && msg.receiver_id) {
            const receiver = clients[msg.receiver_id];
            if (receiver?.readyState === WebSocket.OPEN && msg.sender_id !== msg.receiver_id) {
                receiver.send(JSON.stringify({
                    type: "typing",
                    sender_id: msg.sender_id,
                    sender_name: msg.sender_name
                }));
            }
        }

        // --- Chat message ---
        if (msg.type === "chat" && msg.sender_id && msg.receiver_id) {
            [msg.sender_id, msg.receiver_id].forEach(id => {
                const client = clients[id];
                if (client?.readyState === WebSocket.OPEN) {
                    client.send(JSON.stringify(msg));
                }
            });
        }
    });

    ws.on("close", () => {
        if (ws.user_id && clients[ws.user_id] === ws) {
            delete clients[ws.user_id];
            console.log(`User disconnected: ${ws.user_id}`);
        }
    });

    ws.on("error", err => console.error("WebSocket error:", err.message));
});

// --- TCP Server for PHP push messages ---
const tcpServer = net.createServer(socket => {
    let buffer = "";

    socket.on("data", chunk => {
        buffer += chunk.toString();
        const lines = buffer.split("\n");
        buffer = lines.pop();

        for (const line of lines) {
            if (!line.trim()) continue;

            let msg;
            try { msg = JSON.parse(line); } catch { continue; }

            if (msg.type === "chat" && msg.sender_id && msg.receiver_id) {
                [msg.sender_id, msg.receiver_id].forEach(id => {
                    const client = clients[id];
                    if (client?.readyState === WebSocket.OPEN) {
                        client.send(JSON.stringify(msg));
                    }
                });
            }
        }
    });

    socket.on("error", err => console.error("TCP socket error:", err.message));
});

tcpServer.listen(3001, "127.0.0.1", () => console.log("TCP push server running on port 3001"));

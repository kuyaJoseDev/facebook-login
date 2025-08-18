const WebSocket = require("ws");
const net = require("net");

const wss = new WebSocket.Server({ port: 3000 });
let clients = {}; // user_id -> ws

// --- Browser WebSocket connections ---
wss.on("connection", ws => {
    ws.on("message", raw => {
        let msg;
        try { msg = JSON.parse(raw); } catch { return; }

        // Register user
        if (msg.type === "join") {
            clients[msg.user_id] = ws;
            ws.user_id = msg.user_id;
            console.log("User joined:", msg.user_id);
        }

        // Typing indicator
        if (msg.type === "typing") {
            const receiver = clients[msg.receiver_id];
            if (receiver?.readyState === WebSocket.OPEN) receiver.send(JSON.stringify(msg));
        }

        // Sending chat directly from browser (optional)
        if (msg.type === "chat") {
            [msg.sender_id, msg.receiver_id].forEach(id => {
                const client = clients[id];
                if (client?.readyState === WebSocket.OPEN) client.send(JSON.stringify(msg));
            });
        }
    });

    ws.on("close", () => {
        if (ws.user_id) delete clients[ws.user_id];
    });
});

// --- TCP Server for PHP pushes ---
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

            if (msg.type === "chat") {
                [msg.sender_id, msg.receiver_id].forEach(id => {
                    const client = clients[id];
                    if (client?.readyState === WebSocket.OPEN) client.send(JSON.stringify(msg));
                });
            }
        }
    });

    socket.on("error", err => console.error("TCP socket error:", err.message));
});

tcpServer.listen(3001, "127.0.0.1", () => console.log("TCP push server running on port 3001"));

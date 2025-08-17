const WebSocket = require("ws");
const net = require("net");

// --- WebSocket server for browsers ---
const wss = new WebSocket.Server({ port: 3000 });
let clients = {}; // user_id -> ws connection

wss.on("connection", ws => {
    ws.on("message", raw => {
        let msg;
        try { msg = JSON.parse(raw); } catch (e) { return; }

        if (msg.type === "join") {
            clients[msg.user_id] = ws;
            ws.user_id = msg.user_id;
            console.log("User joined:", msg.user_id);
        }

        if (msg.type === "typing") {
            if (clients[msg.receiver_id]) {
                clients[msg.receiver_id].send(JSON.stringify(msg));
            }
        }
    });

    ws.on("close", () => {
        if (ws.user_id) {
            delete clients[ws.user_id];
            console.log("User disconnected:", ws.user_id);
        }
    });
});

// --- Raw TCP server for PHP pushes ---
const tcpServer = net.createServer(socket => {
    let buffer = "";

    socket.on("data", chunk => {
        buffer += chunk.toString();

        // process each full line (\n separated)
        let parts = buffer.split("\n");
        buffer = parts.pop(); // save incomplete piece

        for (let line of parts) {
            if (!line.trim()) continue;
            let msg;
            try { msg = JSON.parse(line); } catch (e) {
                console.error("Invalid JSON from PHP:", line);
                continue;
            }

            console.log("Push from PHP:", msg);

            if (msg.type === "chat") {
                // send to receiver
                if (clients[msg.receiver_id] && clients[msg.receiver_id].readyState === WebSocket.OPEN) {
                    clients[msg.receiver_id].send(JSON.stringify(msg));
                }
                // echo back to sender
                if (clients[msg.sender_id] && clients[msg.sender_id].readyState === WebSocket.OPEN) {
                    clients[msg.sender_id].send(JSON.stringify(msg));
                }
            }
        }
    });

    socket.on("error", err => {s
        console.error("TCP socket error:", err.message);
    });
});

tcpServer.listen(3001, "127.0.0.1", () => {
    console.log("TCP push server running on port 3001");
});

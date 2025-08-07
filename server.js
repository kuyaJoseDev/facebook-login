const WebSocket = require('ws');
const wss = new WebSocket.Server({ port: 8080 });

let clients = {};

wss.on('connection', ws => {
    ws.on('message', message => {
        const data = JSON.parse(message);

        if (data.type === "init") {
            clients[data.user_id] = ws;
        }

        if (data.type === "message") {
            const sendTo = clients[data.receiver_id];
            if (sendTo) {
                sendTo.send(JSON.stringify({
                    type: "message",
                    sender_id: data.sender_id,
                    sender_name: "User" + data.sender_id, // Replace with actual DB name if needed
                    message: data.message,
                    created_at: new Date().toLocaleString()
                }));
            }
        }
    });

    ws.on('close', () => {
        for (let id in clients) {
            if (clients[id] === ws) {
                delete clients[id];
                break;
            }
        }
    });
});

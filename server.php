<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
require __DIR__ . '/vendor/autoload.php';

class ChatServer implements MessageComponentInterface {
    protected $clients;      // SplObjectStorage for all connections
    protected $userMap = [];  // user_id => ConnectionInterface

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "✅ ChatServer initialized\n";
    }

    // --- New connection ---
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "🟢 New connection: {$conn->resourceId}\n";
    }

    // --- Incoming message ---
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['type'])) return;

        // --- Register user ---
        if ($data['type'] === 'init') {
            $this->userMap[$data['user_id']] = $from;
            $from->user_id = $data['user_id'];
            echo "✅ User {$data['user_id']} registered on connection {$from->resourceId}\n";
            return;
        }

        // --- Chat message ---
        if ($data['type'] === 'chat') {
            $receiverId = $data['receiver_id'];

            // Send to receiver if online
            if (isset($this->userMap[$receiverId])) {
                $this->userMap[$receiverId]->send(json_encode($data));
            }

            // Echo back to sender
            $from->send(json_encode($data));

            echo "📨 {$data['sender_id']} -> {$receiverId}: {$data['message']}\n";
        }

        // --- Typing indicator ---
        if ($data['type'] === 'typing') {
            $receiverId = $data['receiver_id'];
            if (isset($this->userMap[$receiverId])) {
                $this->userMap[$receiverId]->send(json_encode($data));
            }
            // No echo back to sender
        }
    }

    // --- Connection closed ---
    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        if (isset($conn->user_id)) {
            unset($this->userMap[$conn->user_id]);
            echo "🔴 User {$conn->user_id} disconnected\n";
        } else {
            echo "🔴 Connection {$conn->resourceId} disconnected\n";
        }
    }

    // --- Error handling ---
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "❌ Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// --- Run WebSocket server ---
$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            new ChatServer()
        )
    ),
    8080 // WebSocket port
);

echo "✅ WebSocket server running at ws://localhost:8080\n";
$server->run();

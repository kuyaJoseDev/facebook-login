<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

require __DIR__ . '/vendor/autoload.php';

class ChatServer implements MessageComponentInterface {
    protected $clients; // SplObjectStorage
    protected $userMap = []; // user_id => ConnectionInterface

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "🟢 New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        if ($data['type'] === 'init') {
            $this->userMap[$data['user_id']] = $from;
            echo "✅ User {$data['user_id']} registered on {$from->resourceId}\n";
            return;
        }

       if ($data['type'] === 'chat') {
        $receiverId = $data['receiver_id'];
        
        // Send to receiver only
        if (isset($this->userMap[$receiverId])) {
            $this->userMap[$receiverId]->send(json_encode($data));
        }
            // Echo back to sender
            $from->send(json_encode($data));
            echo "📨 {$data['sender_id']} -> {$receiverId}: {$data['message']}\n";
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        // Remove from userMap
        foreach ($this->userMap as $userId => $client) {
            if ($client === $conn) unset($this->userMap[$userId]);
        }
        echo "🔴 {$conn->resourceId} disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "❌ Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// --- Run the WebSocket server ---
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

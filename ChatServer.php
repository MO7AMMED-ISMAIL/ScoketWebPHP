<?php
require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ChatServer implements MessageComponentInterface {
    protected $clients;
    protected $usernames;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->usernames = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        if (isset($data['login']) && isset($data['username'])) {
            $this->usernames[$from->resourceId] = $data['username'];
            $message = [
                'username' => $data['username'],
                'message' => $data['username'] . ' has joined',
                'type' => 'login',
                'online' => $this->getOnlineUsers()
            ];
        } elseif (isset($data['body'])) {
            $message = [
                'message' => $data['body'],
                'type' => 'chat',
                'online' => $this->getOnlineUsers()
            ];
        }

        $this->sendToAllClients(json_encode($message), $from);
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);

        $username = isset($this->usernames[$conn->resourceId]) ? $this->usernames[$conn->resourceId] : 'Unknown';
        unset($this->usernames[$conn->resourceId]);

        $message = [
            'type' => 'logout',
            'message' => $username . ' has disconnected',
            'online' => $this->getOnlineUsers()
        ];

        $this->sendToAllClients(json_encode($message));
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function getOnlineUsers() {
        return array_values($this->usernames);
    }

    protected function sendToAllClients($message, $exclude = null) {
        foreach ($this->clients as $client) {
            if ($exclude === null || $client !== $exclude) {
                $client->send($message);
            }
        }
    }
}

$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            new ChatServer()
        )
    ),
    8090
);

echo "---server started\n";
$server->run();

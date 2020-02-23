<?php

namespace BeyondCode\LaravelWebSockets\Server\Logger;

use Ratchet\ConnectionInterface;

class ConnectionLogger extends Logger implements ConnectionInterface
{
    /** @var \Ratchet\ConnectionInterface */
    protected $connection;

    public static function decorate(ConnectionInterface $app): self
    {
        $logger = app(self::class);

        return $logger->setConnection($app);
    }

    public function setConnection(ConnectionInterface $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    protected function getConnection()
    {
        return $this->connection;
    }

    public function send($data)
    {

        $socketId = $this->connection->socketId ?? null;

        $this->info("Connection id {$socketId} sending message {$data}");


        $m = json_decode($data);

        if (strpos($m->channel, 'videoStream') !== false && property_exists($m, 'data')) {
            $message = json_decode($m->data);

            if ($message->message->messageType == 'videoStream') {
                $room_id = $message->message->room_id;
                $streams = session()->get('streams');
                $streams[$socketId] = (array)$message->message;
                session(['streams' => $streams]);
            }

            if($message->message->messageType == 'getRoomVideoStreams'){
                $streams = session()->get('streams');

                $room_id = $message->message->room_id;

                $collection = collect($streams);

                $filtered = $collection->filter(function ($value, $key) use ($room_id){
                    return $value['room_id'] == $room_id;
                });

                $newData = [];
                $newData['channel'] = $m->channel;
                $newData['event'] = $m->event;
                $newData['data']['message'] = ['streams'=>$filtered->all()];

                $data = json_encode($newData);
            }
        }
        $this->connection->send($data);
    }

    public function close()
    {
        $this->warn("Connection id {$this->connection->socketId} closing.");

        $this->connection->close();
    }

    public function __set($name, $value)
    {
        return $this->connection->$name = $value;
    }

    public function __get($name)
    {
        return $this->connection->$name;
    }

    public function __isset($name)
    {
        return isset($this->connection->$name);
    }

    public function __unset($name)
    {
        unset($this->connection->$name);
    }
}

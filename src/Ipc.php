<?php

namespace Yabx\Ipc;

use Exception;
use Throwable;
use RuntimeException;

class Ipc {

    protected string $id;
    protected array $listeners = [];
    protected static string $ipcPath = '/dev/shm/php-ipc';
    protected static int $usleep = 100_000;

    public function __construct(string $id) {
        $this->id = $id;
        if(!is_dir(self::$ipcPath)) mkdir(self::$ipcPath, 0755, true);
    }

    public function send(string $receiver, mixed $payload): bool {
        return $this->sendMessage(new Message($this->id, $receiver, $payload));
    }

    public function call(int $id, string $method, array $args = [], int $timeout = 30): mixed {
        $call = new CallRequest($method, $args);
        $this->sendMessage(new Message($this->id, $id, $call));
        $result = null;
        $finished = false;
        $this->setListener($call->getId(), function(mixed $payload) use ($call, &$result, &$finished) {
            $finished = true;
            $result = $payload;
            $this->removeListener($call->getId());
        });
        while(!$finished) {
            $this->processMessages();
            $this->usleep();
        }
        return $result;
    }

    public function callAsync(int $id, string $method, array $args, callable $callback): void {
        $call = new CallRequest($method, $args);
        $this->sendMessage(new Message($this->id, $id, $call));
        $this->setListener($call->getId(), function(mixed $payload) use ($callback, $call) {
            $this->removeListener($call->getId());
            call_user_func($callback, $payload);
        });
    }

    public function sendMessage(Message $message): bool {
        $path = self::$ipcPath . '/' . md5($message->getReceiver());
        if(!is_dir($path)) mkdir($path, 0755, true);
        file_put_contents("{$path}/{$message->getId()}", serialize($message));
        return true;
    }

    public function processMessages(?callable $callback = null): void {
        $messages = $this->getMessages();
        foreach($messages as $message) {
            $payload = $message->getPayload();

            if($callback) call_user_func($callback, $message);

            if($payload instanceof CallRequest) {
                if($callable = $this->listeners[$payload->getMethod()] ?? false) {
                    try {
                        $result = call_user_func_array($callable, $payload->getArgs());
                        $this->send($message->getSender(), new CallResult($payload->getId(), $result));
                    } catch(Throwable $error) {
                        $this->send($message->getSender(), new CallResult($payload->getId(), $error));
                    }

                } else {
                    $this->send($message->getSender(), new CallResult($payload->getId(), new RuntimeException('No such method: ' . $payload->getMethod())));
                }

            } elseif($payload instanceof CallResult) {
                if($callable = $this->listeners[$payload->getId()] ?? false) {
                    call_user_func($callable, $payload->getResult());
                }
            } else {

                if(gettype($payload) !== 'object') continue;

                $class = get_class($payload);
                if(class_exists($class) && key_exists($class, $this->listeners)) {
                    call_user_func($this->listeners[$class], $payload);

                } elseif($callable = $this->listeners['message'] ?? null) {
                    call_user_func($callable, $message);
                }

            }

        }
    }

    /**
     * @return Message[]
     */
    protected function getMessages(bool $clear = true): array {
        $path = self::$ipcPath . '/' . md5($this->id);
        if(!is_dir($path)) return [];
        $messages = [];
        foreach(glob($path . '/*') as $file) {
            $message = unserialize(file_get_contents($file));
            assert($message instanceof Message);
            $messages[] = $message;
            if($clear) unlink($file);
        }
        return $messages;
    }

    public function setListener(string $method, callable $callback): void {
        $this->listeners[$method] = $callback;
    }

    public function removeListener(string $method): void {
        unset($this->listeners[$method]);
    }

    public function setMessageListener(callable $callback): void {
        $this->setListener('message', $callback);
    }

    public function usleep(): void {
        usleep(self::$usleep);
    }

    public static function setUsleep(int $usleep): void {
        self::$usleep = $usleep;
    }

    public static function setIpcPath(string $ipcPath): void {
        self::$ipcPath = $ipcPath;
    }

}

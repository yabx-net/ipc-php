<?php

namespace Yabx\Ipc;

use Throwable;
use RuntimeException;

class Ipc {

    protected string $id;
    protected array $listeners = [];
    protected array $methods = [];
    protected static string $ipcPath = '/dev/shm/php-ipc';
    protected static int $usleep = 100_000;

    public function __construct(string $id) {
        $this->id = $id;
        if(!is_dir(self::$ipcPath)) mkdir(self::$ipcPath, 0755, true);
    }

    public function send(string $receiver, mixed $payload): bool {
        return $this->sendMessage(new Message($this->id, $receiver, $payload));
    }

    public function call(string $id, string $method, array $args = [], int $timeout = 30): mixed {
        $call = new Call($method, $args);
        $this->sendMessage(new Message($this->id, $id, $call));
        $result = null;
        $finished = false;
        $this->setListener($call->getId(), function(mixed $payload) use ($call, &$result, &$finished) {
            $finished = true;
            $result = $payload;
            $this->removeListener($call->getId());
        });
        $end = time() + $timeout;
        while(!$finished) {
            if(time() > $end) {
                $this->removeListener($call->getId());
                throw new RuntimeException('Call timed out: ' . $method);
            }
            $this->processMessages();
            $this->usleep();
        }
        return $result;
    }

    public function callAsync(string $id, string $method, array $args = [], ?callable $callback = null): void {
        $call = new Call($method, $args);
        $this->sendMessage(new Message($this->id, $id, $call));
        if($callback) {
            $this->setListener($call->getId(), function(mixed $payload) use ($callback, $call) {
                $this->removeListener($call->getId());
                call_user_func($callback, $payload);
            });
        }
    }

    public function sendMessage(Message $message): bool {
        $path = self::$ipcPath . '/' . self::hash($message->getReceiver());
        if(!is_dir($path)) mkdir($path, 0755, true);
        return (bool)file_put_contents("{$path}/{$message->getId()}", serialize($message));
    }

    public function processMessages(?callable $callback = null): void {
        $messages = $this->getMessages();
        foreach($messages as $message) {
            $payload = $message->getPayload();

            if($callback) call_user_func($callback, $message);

            if($callable = $this->listeners['message'] ?? null) {
                call_user_func($callable, $message);
            }

            if(is_object($payload)) {
                $class = get_class($payload);
                if(class_exists($class) && key_exists($class, $this->listeners)) {
                    call_user_func($this->listeners[$class], $payload, $message);
                }
            }

            if($payload instanceof Call) {
                if($callable = $this->methods[$payload->getMethod()] ?? false) {
                    try {
                        $result = call_user_func_array($callable, $payload->getArgs());
                    } catch(Throwable $error) {
                        $result = $error;
                    }
                } else {
                    $result = new RuntimeException('No such method: ' . $payload->getMethod());
                }

                $this->send($message->getSender(), new Result($payload->getId(), $result));

            } elseif($payload instanceof Result) {
                if($callable = $this->listeners[$payload->getId()] ?? false) {
                    call_user_func($callable, $payload->getResult());
                }
            }
        }
    }

    /**
     * @return Message[]
     */
    protected function getMessages(bool $clear = true): array {
        $path = self::$ipcPath . '/' . self::hash($this->id);
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

    public function setMethod(string $method, callable $callback): void {
        $this->methods[$method] = $callback;
    }

    public function setListener(string $name, callable $callback): void {
        $this->listeners[$name] = $callback;
    }

    public function removeListener(string $name): void {
        unset($this->listeners[$name]);
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

    protected static function hash(string $str): string {
        static $cache = [];
        return $cache[$str] ?? $cache[$str] = md5($str);
    }

}

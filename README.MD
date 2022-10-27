About
-------
Simple IPC implementation for PHP

Install
-------
```bash
composer require yabx/ipc
```
Basic usage
-------
```php
<?php

use Yabx\Ipc\Ipc;
use Yabx\Ipc\Message;

require __DIR__ . '/vendor/autoload.php';

// Initialize IPC with current process ID "pr1"
$ipc = new Ipc('pr1');

// Sending payload to process ID "pr2" 
$ipc->send('pr2', ['foo' => 'baz']);

while(true) {

    // Do some things

    // Processing incoming messages from other processes
    $ipc->processMessages(function(Message $message) {
        print_r($message->getPayload());
    });

    // take a short break to reduce CPU usage
    $ipc->usleep();

}
```
Calling methods between processes
-------
Contents of `master.php`
```php
<?php

use Yabx\Ipc\Ipc;

require __DIR__ . '/vendor/autoload.php';

$ipc = new Ipc('master');

// Sync call
$status = $ipc->call('worker', 'get_status');

// Display result
echo 'STATUS: ' . print_r($status, 1) . PHP_EOL;

// Async call
$ipc->callAsync('worker', 'plus', [1, 2], function(int $result) {
    // Display result ans exit
    echo '1 + 2 = ' . $result . PHP_EOL;
    exit;
});

while(true) {

    // Processing messages
    $ipc->processMessages();
    
    // take a short break to reduce CPU usage
    $ipc->usleep();

}
```
Contents of `worker.php`
```php
<?php

use Yabx\Ipc\Ipc;

require __DIR__ . '/vendor/autoload.php';

$ipc = new Ipc('worker');

$ipc->setMethod('get_status', function() {
    return [
        'status' => 'OK',
        'progress' => '50%'
    ];
});

$ipc->setMethod('plus', function(int $a, int $b) {
    return $a + $b;
});

while(true) {

    // Do some work

    // Processing incoming messages from other processes
    $ipc->processMessages();

    // take a short break to reduce CPU usage
    $ipc->usleep();

}
```
API
-------
```php
// Ipc class constructor
$ipc = new Ipc(string $processId);

// Build Message with $payload and send to $id
$ipc->send(string $id, mixed $payload): bool;

// Call synchronously $method(...$args) on $id process
$ipc->call(string $id, string $method, array $args = [], int $timeout = 30): mixed

// Call asynchronously $method(...$args) on $id process
// If $callback is defined, Result will be passed to $callback(mixed $result)
$ipc->callAsync(string $id, string $method, array $args = [], ?callable $callback = null): void

// Send raw Message
$ipc->sendMessage(Message $message): bool

// Process incoming messages
// If $callback is defined it will be called as $callback(Message $message)
$ipc->processMessages(?callable $callback = null): void

// Set method (makes it callable from other processes)
$ipc->setMethod(string $method, callable $callback): void

// Set listener to process SomePayload::class (example) messages
$ipc->setListener(SomePayload::class, function(SomePayload $payload, Message $message) { ... }): void

// Remove listener
$ipc->removeListener(string $method): void

// Set listener to process all incoming messages
$ipc->setMessageListener(callable $callback): void
$ipc->setMessageListener(function(Message $message) { ... }): void

// Sleep (0.1 sec by default)
$ipc->usleep(): void

// Changes usleep time (default: 100000 = 0.1 sec), in microseconds
Ipc::setUsleep(int $usleep): void

// Changes IPC files store path (default: /dev/shm/ipc-php)
Ipc::setIpcPath(string $ipcPath): void
```

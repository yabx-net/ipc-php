<?php

namespace Yabx\Ipc;

use DateTimeImmutable;

class Message {

    protected string $id;
    protected string $sender;
    protected string $receiver;
    protected mixed $payload;
    protected DateTimeImmutable $createdAt;

    public function __construct(string $sender, string $receiver, mixed $payload) {
        $this->id = Utils::seqId("{$sender}:{$receiver}");
        $this->sender = $sender;
        $this->receiver = $receiver;
        $this->payload = $payload;
        $this->createdAt = new DateTimeImmutable;
    }

    public function getId(): string {
        return $this->id;
    }

    public function getSender(): string {
        return $this->sender;
    }

    public function getReceiver(): string {
        return $this->receiver;
    }

    public function getPayload(): mixed {
        return $this->payload;
    }

    public function getCreatedAt(): DateTimeImmutable {
        return $this->createdAt;
    }

}

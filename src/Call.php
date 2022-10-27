<?php

namespace Yabx\Ipc;

class Call {

    protected string $id;
    protected string $method;
    protected array $args;

    public function __construct(string $method, array $args) {
        $this->id = 'call_' . md5(uniqid());
        $this->method = $method;
        $this->args = $args;
    }

    public function getId(): string {
        return $this->id;
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function getArgs(): array {
        return $this->args;
    }

}

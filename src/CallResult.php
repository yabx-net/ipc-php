<?php

namespace Yabx\Ipc;

class CallResult {

    protected string $id;
    protected mixed $result;

    public function __construct(string $id, mixed $result) {
        $this->id = $id;
        $this->result = $result;
    }

    public function getId(): string {
        return $this->id;
    }

    public function getResult(): mixed {
        return $this->result;
    }

}

<?php

declare(strict_types=1);

namespace LaravelProductionDoctor;

final class CheckResult
{
    public function __construct(
        public readonly string $name,
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $recommendation = null,
    ) {
        if (!in_array($status, ['pass', 'warning', 'fail', 'skip'], true)) {
            throw new \InvalidArgumentException('Invalid check status.');
        }
    }

    /** @return array{name:string,status:string,message:string,recommendation:?string} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'status' => $this->status, 'message' => $this->message, 'recommendation' => $this->recommendation];
    }
}

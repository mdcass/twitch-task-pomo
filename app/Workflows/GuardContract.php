<?php

namespace App\Workflows;

interface GuardContract
{
    public function passes(): bool;

    public function message(): ?string;
}

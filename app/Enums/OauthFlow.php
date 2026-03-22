<?php

namespace App\Enums;

enum OauthFlow: string
{
    case Login = 'login';
    case Register = 'register';

    public function routeName(): string
    {
        return $this->value;
    }
}

<?php

namespace App\Exceptions;

use RuntimeException;

final class TodoistReauthorizationRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A autorização do Todoist expirou ou foi revogada. Autorize o Todoist novamente para continuar.');
    }
}

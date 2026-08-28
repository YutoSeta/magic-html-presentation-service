<?php

namespace App\Exceptions;

use RuntimeException;

final class InvalidPresentationException extends RuntimeException
{
    /** @param array<string,array<int,string>> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The presentation input does not satisfy contract 1.0.');
    }
}

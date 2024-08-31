<?php

declare(strict_types=1);

namespace App\Message;

class ParsingMessage extends AbstractMessage
{
    protected const MAX_ATTEMPTS = 3;
}

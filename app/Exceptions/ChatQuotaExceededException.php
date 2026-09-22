<?php

namespace App\Exceptions;

class ChatQuotaExceededException extends \RuntimeException
{
    public function __construct(public readonly int $limit, public readonly bool $isPremium)
    {
        parent::__construct($isPremium
            ? "Batas chat tercapai ({$limit} pesan per user)."
            : "Akun gratis dibatasi {$limit} pesan per user. Upgrade ke Premium untuk kuota lebih besar.");
    }
}

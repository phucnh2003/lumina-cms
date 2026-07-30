<?php

namespace App\Models;

use Lumina\Webhook\Traits\HasWebhookEvents;

class User extends \Lumina\Customer\Models\User
{
    use HasWebhookEvents;

    protected static function webhookEvents(): array
    {
        return ['created', 'updated'];
    }
}

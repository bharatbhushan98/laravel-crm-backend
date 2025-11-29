<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('private-user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
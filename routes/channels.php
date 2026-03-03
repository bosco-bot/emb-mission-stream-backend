<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal public pour les notifications de statut de stream WebTV
Broadcast::channel('webtv-stream-status', function () {
    return true; // Canal public, accessible à tous
});

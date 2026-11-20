<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

// Order/reservation dashboards: staff and admins only.
Broadcast::channel('staff', fn ($user) => $user->isStaff());

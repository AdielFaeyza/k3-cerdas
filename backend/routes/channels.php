<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('k3.dashboard', fn ($user) => in_array($user->role, ['admin', 'hr', 'hse'], true));

<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', fn (User $user, string $id): bool => hash_equals($user->id, $id));
Broadcast::channel('users.{userId}.ai-reviews', fn (User $user, string $userId): bool => hash_equals($user->id, $userId));

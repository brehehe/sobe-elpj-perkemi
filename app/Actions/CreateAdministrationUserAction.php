<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateAdministrationUserAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /**
     * @param  array{name: string, email: string, unit_id: string, role: string, is_active: bool}  $data
     * @return array{user: User, invitation_status: string}
     */
    public function handle(User $actor, array $data): array
    {
        $user = DB::transaction(function () use ($actor, $data): User {
            $user = User::query()->create([
                'unit_id' => $data['unit_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::password(48),
                'is_active' => $data['is_active'],
            ]);
            $user->assignRole($data['role']);

            $this->recordAuditEvent->handle(
                $user,
                'user.created',
                $actor,
                toStatus: $user->is_active ? 'ACTIVE' : 'INACTIVE',
                payload: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'unit_id' => $user->unit_id,
                    'role' => $data['role'],
                ],
                authority: 'admin.manage-users',
            );

            return $user;
        });

        event(new Registered($user));
        $invitationStatus = Password::sendResetLink(['email' => $user->email]);

        return ['user' => $user, 'invitation_status' => $invitationStatus];
    }
}

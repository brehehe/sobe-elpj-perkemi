<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateAdministrationUserAction
{
    public function __construct(private RecordAuditEventAction $recordAuditEvent) {}

    /** @param array{name: string, email: string, unit_id: string, role: string, is_active: bool} $data */
    public function handle(User $managedUser, User $actor, array $data): User
    {
        [$user, $emailChanged] = DB::transaction(function () use ($managedUser, $actor, $data): array {
            $user = User::query()->lockForUpdate()->findOrFail($managedUser->getKey());
            $user->load('roles');
            $before = [
                'name' => $user->name,
                'email' => $user->email,
                'unit_id' => $user->unit_id,
                'role' => $user->roles->first()?->name,
                'is_active' => $user->is_active,
            ];

            if ($actor->is($user) && (! $data['is_active'] || $data['role'] !== $before['role'])) {
                throw ValidationException::withMessages([
                    'role' => 'Role atau status akun yang sedang digunakan tidak dapat diubah dari sesi ini.',
                ]);
            }

            if ($before['role'] === 'Super Admin' && ($data['role'] !== 'Super Admin' || ! $data['is_active'])) {
                $otherActiveSuperAdminExists = User::role('Super Admin')
                    ->whereKeyNot($user->getKey())
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->exists();

                if (! $otherActiveSuperAdminExists) {
                    throw ValidationException::withMessages([
                        'role' => 'Minimal satu Super Admin aktif harus tetap tersedia.',
                    ]);
                }
            }

            $emailChanged = $user->email !== $data['email'];
            $user->fill([
                'unit_id' => $data['unit_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => $data['is_active'],
            ]);

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();
            $user->syncRoles([$data['role']]);

            $this->recordAuditEvent->handle(
                $user,
                'user.access_updated',
                $actor,
                $before['is_active'] ? 'ACTIVE' : 'INACTIVE',
                $user->is_active ? 'ACTIVE' : 'INACTIVE',
                payload: [
                    'before' => $before,
                    'after' => [
                        'name' => $user->name,
                        'email' => $user->email,
                        'unit_id' => $user->unit_id,
                        'role' => $data['role'],
                        'is_active' => $user->is_active,
                    ],
                ],
                authority: 'admin.manage-users',
            );

            return [$user, $emailChanged];
        });

        if ($emailChanged) {
            event(new Registered($user));
        }

        return $user;
    }
}

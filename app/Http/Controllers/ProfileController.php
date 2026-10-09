<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        $user = request()->user();

        return Inertia::render('settings/profile', [
            'sessions' => DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get()->map(fn ($session): array => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => now()->setTimestamp($session->last_activity)->diffForHumans(),
                'is_current' => $session->id === request()->session()->getId(),
            ]),
        ]);
    }

    public function update(UpdateProfile $request): RedirectResponse
    {
        $user = $request->user();
        $emailChanged = $user->email !== $request->string('email')->toString();
        $user->update($request->safe()->only(['name', 'email']));
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();
            $user->sendEmailVerificationNotification();
        }

        return back()->with('success', 'Profil berhasil diperbarui.');
    }
}

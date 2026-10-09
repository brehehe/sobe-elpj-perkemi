<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DeviceSessionController extends Controller
{
    public function destroy(string $session): RedirectResponse
    {
        abort_if($session === request()->session()->getId(), 422, 'Sesi aktif tidak dapat dihapus dari daftar perangkat.');
        DB::table('sessions')->where('id', $session)->where('user_id', request()->user()->id)->delete();

        return back()->with('success', 'Sesi perangkat berhasil dicabut.');
    }
}

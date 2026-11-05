<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    /**
     * Change the logged-in user's password.
     */
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        return redirect()->route('settings.edit')->with('status', 'Password changed.');
    }
}

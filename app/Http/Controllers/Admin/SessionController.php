<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()?->can('access-admin')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $credentials = ['email' => strtolower($data['email']), 'password' => $data['password'], 'is_admin' => true];
        if (! Auth::guard('web')->attempt($credentials)) {
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'The supplied administrator credentials could not be verified.'])
                ->withInput(['email' => $data['email']]);
        }
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

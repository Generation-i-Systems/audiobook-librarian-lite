<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Contracts\DocumentStoreServiceInterface;
use App\Http\Controllers\Controller;
use App\Services\NewUserRegistrationNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly DocumentStoreServiceInterface $documentStoreService,
        private readonly NewUserRegistrationNotifier $registrationNotifier,
    ) {
    }

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($this->documentStoreService->userExistsByEmail($validated['email'])) {
            return back()->withErrors(['email' => 'The email has already been taken.'])->withInput();
        }

        if ($this->documentStoreService->userExistsByUsername($validated['username'])) {
            return back()->withErrors(['username' => 'The username has already been taken.'])->withInput();
        }

        $userData = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'unverified',
        ];

        if ($this->registrationNotifier->isSpamRegistration($userData, $request)) {
            return back()->withErrors(['email' => 'Registration could not be completed.'])->withInput();
        }

        $userId = $this->documentStoreService->createUser($userData);
        if (!$userId) {
            return back()->withErrors(['email' => 'Registration could not be completed.'])->withInput();
        }

        $completeUserData = $this->documentStoreService->getUserById($userId) ?? array_merge($userData, ['id' => $userId]);
        $this->registrationNotifier->send($completeUserData, 'web', $request);

        return redirect()->route('landing')->with(
            'success',
            'Your account request was received. An administrator will review it before you can sign in.'
        );
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\User;

class VerifyEmailController extends Controller
{
    /**
     * Mark the user's email address as verified.
     */
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Lien de vérification invalide.');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('login', ['verified' => 1])
                ->with('status', 'Votre compte est déjà vérifié. Vous pouvez vous connecter.');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->route('login', ['verified' => 1])
            ->with('status', 'Email vérifié avec succès ! Vous pouvez maintenant vous connecter.');
    }
}

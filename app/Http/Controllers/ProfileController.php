<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ProfilePhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'tab' => $request->query('tab', 'profile'),
        ]);
    }

    public function update(ProfileUpdateRequest $request, ProfilePhotoService $photos): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            try {
                $data['avatar_path'] = $photos->store($request->file('avatar'), (int) $user->id, $user->avatar_path);
            } catch (\Throwable $e) {
                return back()->withErrors(['avatar' => $e->getMessage()])->withInput();
            }
        }

        if ($request->boolean('remove_avatar') && ! $request->hasFile('avatar')) {
            $photos->deleteOld($user->avatar_path);
            $data['avatar_path'] = null;
        }

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if (! empty($data['locale'])) {
            $request->session()->put('locale', $data['locale']);
            app()->setLocale($data['locale']);
        }

        return Redirect::route('profile.edit', ['tab' => 'profile'])->with('status', 'profile-updated');
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(string $id): View
    {
        $profile    = User::findOrFail($id);
        $isOwner    = Auth::id() === $id;
        $isFollowing = Auth::check() && Follow::where('follower_id', Auth::id())
                                               ->where('following_id', $id)
                                               ->exists();

        $testimoniesQuery = Testimony::with('user')
            ->where('user_id', $id)
            ->latest();

        if (!$isOwner) $testimoniesQuery->published();

        $testimonies = $testimoniesQuery->paginate(12);

        return view('profile.show', compact('profile', 'isOwner', 'isFollowing', 'testimonies'));
    }

    public function edit(): View
    {
        return view('profile.edit', ['user' => Auth::user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'display_name' => 'required|string|max:100',
            'country'      => 'nullable|string|max:3',
            'bio'          => 'nullable|string|max:500',
            'avatar'       => 'nullable|image|max:5120',
        ]);

        $user = Auth::user();

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar_url' => asset('storage/' . $path)]);
        }

        $user->update([
            'display_name' => $data['display_name'],
            'country'      => $data['country'] ?? $user->country,
            'bio'          => $data['bio'] ?? null,
        ]);

        return back()->with('success', 'Profil mis à jour.');
    }

    public function savedTestimonies(): View
    {
        $testimonies = Auth::user()
            ->savedTestimonies()
            ->with('user')
            ->published()
            ->latest('saved_at')
            ->paginate(12);

        return view('profile.saved', compact('testimonies'));
    }

    public function settings(): View
    {
        $settings = Auth::user()->settings ?? new \App\Models\UserSetting();
        return view('profile.settings', compact('settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_private_account' => 'nullable|boolean',
            'comment_permission' => 'nullable|in:everyone,followers,nobody',
            'push_comments'      => 'nullable|boolean',
            'push_likes'         => 'nullable|boolean',
            'push_prayers'       => 'nullable|boolean',
            'push_approval'      => 'nullable|boolean',
            'push_new_followed'  => 'nullable|boolean',
            'app_theme'          => 'nullable|in:light,dark,system',
            'language'           => 'nullable|string|max:5',
        ]);

        $settings = Auth::user()->settings ?? \App\Models\UserSetting::create(['user_id' => Auth::id()]);
        $settings->update($data);

        return back()->with('success', 'Paramètres enregistrés.');
    }
}

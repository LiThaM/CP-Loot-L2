<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        // L2 character management lives in /characters now; this page
        // stays focused on web-account fields (email, password, prefs).
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // ProfileUpdateRequest validates the JSON fields. The avatar file
        // can't be in that FormRequest (which validates `validated()` keys)
        // because we want to keep the upload path defensive — validate here
        // and persist via forceFill so `avatar_path` stays out of mass
        // assignment from the form payload.
        $request->validate([
            'avatar' => 'nullable|image|max:3072',
        ]);

        $request->user()->fill($request->validated());

        // Derive main_race from the chosen main_class so the two stay
        // coherent. Anything the form posted as `main_race` is overwritten
        // when a class id is present — same pattern the Character model
        // uses for secondary chars.
        if ($request->user()->main_class_id) {
            $class = \App\Contexts\Identity\Domain\Models\L2Class::find($request->user()->main_class_id);
            if ($class) {
                $request->user()->main_race = $class->race;
            }
        } else {
            $request->user()->main_race = null;
        }

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        // Avatar upload (handled separately because the file goes through
        // GD resize + storage, not through Eloquent fill).
        if ($request->hasFile('avatar')) {
            $user = $request->user();
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $path = $this->storeResizedAvatar($request->file('avatar'), $user->id);
            $user->forceFill(['avatar_path' => $path])->save();
        }

        // Sync language preference to session immediately
        $langPref = $request->input('language_preference', 'system');
        if ($langPref !== 'system' && in_array($langPref, ['en', 'es'], true)) {
            $request->session()->put('locale', $langPref);
        } else {
            $request->session()->forget('locale');
        }

        return Redirect::route('profile.edit');
    }

    /**
     * Decodes the uploaded image with GD, scales it down to 512×512 max
     * preserving the aspect ratio, re-encodes as JPG q85 and writes it to
     * `storage/app/public/avatars/{userId}/{filename}`. Returns the
     * relative path saved in `users.avatar_path`.
     *
     * Falls back to a raw `store()` if GD can't decode the file (rare,
     * for exotic image formats). The JPG output is what every avatar
     * spot in the UI consumes via `<img>` so animations are intentionally
     * stripped.
     */
    private function storeResizedAvatar(UploadedFile $file, int $userId): string
    {
        $contents = @file_get_contents($file->getRealPath());
        $img = $contents !== false ? @imagecreatefromstring($contents) : false;

        if (! $img) {
            // Edge case: GD couldn't decode. Keep the upload at full size
            // rather than rejecting the user — validation already ensured
            // it's an image MIME.
            $fallback = $file->store("avatars/{$userId}", 'public');
            Log::info('Avatar upload skipped GD resize (fallback to raw)', [
                'user_id' => $userId, 'path' => $fallback,
            ]);
            return $fallback;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $max = 512;
        $ratio = min($max / $w, $max / $h, 1);
        $newW = max(1, (int) round($w * $ratio));
        $newH = max(1, (int) round($h * $ratio));

        $resized = imagecreatetruecolor($newW, $newH);
        // Fill with white so transparent PNGs don't turn black when we
        // re-encode as JPG (no alpha channel).
        $white = imagecolorallocate($resized, 255, 255, 255);
        imagefilledrectangle($resized, 0, 0, $newW, $newH, $white);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);

        $filename = 'av_' . Str::random(10) . '.jpg';
        $tmpPath = tempnam(sys_get_temp_dir(), 'av_') . '.jpg';
        imagejpeg($resized, $tmpPath, 85);
        imagedestroy($img);
        imagedestroy($resized);

        Storage::disk('public')->putFileAs(
            "avatars/{$userId}",
            new \Illuminate\Http\File($tmpPath),
            $filename,
        );
        @unlink($tmpPath);

        return "avatars/{$userId}/{$filename}";
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}

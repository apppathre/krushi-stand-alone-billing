<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MailSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailSettingController extends Controller
{
    public function edit()
    {
        return view('settings.mail', ['mail' => MailSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['nullable', 'required_if:is_enabled,1', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['nullable', 'in:tls,ssl'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'required_if:is_enabled,1', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);
        $data['is_enabled'] = $request->boolean('is_enabled');

        $mail = MailSetting::current();
        if (blank($data['password'] ?? null)) {
            unset($data['password']); // keep the stored password
        }
        $mail->update($data);

        return back()->with('status', __('Mail settings saved.'));
    }

    public function test(Request $request): RedirectResponse
    {
        $request->validate(['test_email' => ['required', 'email']]);

        if (! MailSetting::current()->is_enabled) {
            return back()->withErrors(['test_email' => __('Enable and save SMTP settings first.')]);
        }

        try {
            Mail::raw(__('This is a test email from :app.', ['app' => config('app.name')]), function ($m) use ($request) {
                $m->to($request->test_email)->subject(__('SMTP test').' - '.config('app.name'));
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['test_email' => __('Mail failed: :msg', ['msg' => $e->getMessage()])]);
        }

        return back()->with('status', __('Test email sent to :email.', ['email' => $request->test_email]));
    }
}

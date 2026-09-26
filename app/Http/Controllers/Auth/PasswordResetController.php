<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني.');
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors([
                'email' => 'تم طلب رابط إعادة التعيين مؤخراً. حاول مرة أخرى بعد قليل.',
            ]);
        }

        return back()->withErrors([
            'email' => 'تعذر إرسال رابط إعادة التعيين لهذا البريد الإلكتروني.',
        ]);
    }

    public function createResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', PasswordRule::min(8), 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => null,
                ])->save();

                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('status', 'تم تغيير كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.');
        }

        return back()->withErrors([
            'email' => 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية.',
        ])->withInput($request->only('email'));
    }

    public function sendResetLinkApi(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'تم طلب رابط إعادة التعيين مؤخراً. حاول مرة أخرى بعد قليل.',
            ], 429);
        }

        return response()->json([
            'message' => 'إذا كان البريد الإلكتروني مسجلاً في النظام، فسيتم إرسال رابط إعادة تعيين كلمة المرور إليه.',
        ]);
    }

    public function resetApi(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', PasswordRule::min(8), 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => null,
                ])->save();

                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية.',
            ], 422);
        }

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Jobs\SendEmail;
use App\Mail\UserEmailUpdateRequest;
use App\Models\User;
use App\Providers\QRCodeProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RobThree\Auth\Algorithm;
use RobThree\Auth\TwoFactorAuth;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return view('account', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'firstname' => 'required',
            'surname' => 'required',
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'phone' => 'required',

            'shipping_address' => 'required_with:shipping_city,shipping_postcode,shipping_country,shipping_state',
            'shipping_city' => 'required_with:shipping_address,shipping_postcode,shipping_country,shipping_state',
            'shipping_postcode' => 'required_with:shipping_address,shipping_city,shipping_country,shipping_state',
            'shipping_country' => 'required_with:shipping_address,shipping_city,shipping_postcode,shipping_state',
            'shipping_state' => 'required_with:shipping_address,shipping_city,shipping_postcode,shipping_country',

            'billing_address' => 'required_with:billing_city,billing_postcode,billing_country,billing_state',
            'billing_city' => 'required_with:billing_address,billing_postcode,billing_country,billing_state',
            'billing_postcode' => 'required_with:billing_address,billing_city,billing_country,billing_state',
            'billing_country' => 'required_with:billing_address,billing_city,billing_postcode,billing_state',
            'billing_state' => 'required_with:billing_address,billing_city,billing_postcode,billing_country',
        ], [
            'firstname.required' => __('validation.custom_messages.firstname_required'),
            'surname.required' => __('validation.custom_messages.surname_required'),
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid'),
            'phone.required' => __('validation.custom_messages.phone_required'),

            'shipping_address.required' => __('validation.custom_messages.shipping_address_required'),
            'shipping_city.required' => __('validation.custom_messages.shipping_city_required'),
            'shipping_postcode.required' => __('validation.custom_messages.shipping_postcode_required'),
            'shipping_country.required' => __('validation.custom_messages.shipping_country_required'),
            'shipping_state.required' => __('validation.custom_messages.shipping_state_required'),

            'billing_address.required' => __('validation.custom_messages.billing_address_required'),
            'billing_city.required' => __('validation.custom_messages.billing_city_required'),
            'billing_postcode.required' => __('validation.custom_messages.billing_postcode_required'),
            'billing_country.required' => __('validation.custom_messages.billing_country_required'),
            'billing_state.required' => __('validation.custom_messages.billing_state_required'),
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $userData = $request->all();

        $newEmail = $userData['email'];
        unset($userData['email']);

        if (strtolower($user->email) !== strtolower($newEmail)) {
            $user->tokens()->where('type', 'email-update')->delete();

            $token = $user->tokens()->create([
                'type' => 'email-update',
                'data' => [
                    'email' => $newEmail,
                ],
                'expires_at' => now()->addMinutes(30),
            ]);

            dispatch(new SendEmail($user->email, new UserEmailUpdateRequest($token->id, $user->email, $newEmail)))->onQueue('mail');
        }

        $userData['subscribed'] = ($request->get('subscribed', false) === 'on');
        $user->update($userData);
        $user->save();

        session()->flash('message', 'Your account details have been saved');
        session()->flash('message-title', 'Details updated');
        session()->flash('message-type', 'success');
        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        /** @var User $user */
        $user = auth()->user();
        auth()->logout();

        $user->delete();

        session()->flash('message', 'Your account has been deleted');
        session()->flash('message-title', 'Account Deleted');
        session()->flash('message-type', 'success');
        return redirect()->route('index');
    }

    public static function getTFAInstance()
    {
        $tfa = new TwoFactorAuth(new QRCodeProvider(), 'STEMMechanics', 6, 30, Algorithm::Sha512);
        $tfa->ensureCorrectTime();
        return $tfa;
    }

    public function show_tfa()
    {
        $user = auth()->user();
        if ($user->tfa_secret === null) {
            $tfa = self::getTFAInstance();
            $secret = $tfa->createSecret();

            return response()->json([
                'secret' => $secret,
            ]);
        } else {
            abort(404);
        }
    }

    public function show_tfa_image(Request $request)
    {
        $user = auth()->user();
        if ($user->tfa_secret === null && $request->has('secret')) {
            $tfa = self::getTFAInstance();

            $qrCodeProvider = new QRCodeProvider();
            $qrCode = $qrCodeProvider->getQRCodeImage(
                $tfa->getQRText($user->email, $request->get('secret')),
                200
            );

            return response()->stream(function () use ($qrCode) {
                echo $qrCode;
            }, 200, ['Content-Type' => $qrCodeProvider->getMimeType()]);
        } else {
            abort(404);
        }
    }

    public function post_tfa(Request $request)
    {
        $user = auth()->user();

        if ($user->tfa_secret === null && $request->has('secret') && $request->has('code')) {
            $secret = $request->get('secret');
            $code = $request->get('code');

            $tfa = self::getTFAInstance();

            if ($tfa->verifyCode($secret, $code, 4)) {
                $user->tfa_secret = $secret;
                $user->save();

                $codes = $user->generateBackupCodes();

                return response()->json([
                    'success' => true,
                    'codes' => $codes
                ]);
            } else {
                return response()->json([
                    'success' => false,
                ]);
            }
        } else {
            abort(403);
        }
    }

    public function destroy_tfa(Request $request)
    {
        $user = auth()->user();

        if ($user->tfa_secret !== null) {
            $user->tfa_secret = null;
            $user->save();

            $user->backupCodes()->delete();

            return response()->json([
                'success' => true,
            ]);
        } else {
            abort(403);
        }
    }

    public function post_tfa_reset_backup_codes(Request $request)
    {
        $user = auth()->user();

        if ($user->tfa_secret !== null) {
            $codes = $user->generateBackupCodes();

            return response()->json([
                'success' => true,
                'codes' => $codes
            ]);
        } else {
            abort(403);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enum\HttpResponseCodes;
use App\Http\Requests\AuthLoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

class AuthController extends ApiController
{
    /**
     * Resource name
     * @var string
     */
    protected $resourceName = 'user';


    /**
     * ApplicationController constructor.
     */
    public function __construct()
    {
        // $this->middleware('auth:sanctum')
        //     ->only(['me']);
    }

    /**
     * Current User details
     *
     * @param Request $request Current request data.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->makeVisible(['permissions']);
        return $this->respondAsResource($user);
    }

    /**
     * Login user with supplied creditials
     *
     * @param App\Http\Controllers\Api\AuthLoginRequest $request Created request data.
     * @return JsonResponse|void
     */
    public function login(AuthLoginRequest $request)
    {
        $user = User::where('email', '=', $request->input('email'))->first();

        if ($user !== null && strlen($user->password) > 0 && Hash::check($request->input('password'), $user->password) === true) {
            if ($user->email_verified_at === null) {
                return $this->respondWithErrors([
                    'email' => 'Email address has not been verified.'
                ]);
            }

            if ($user->disabled === true) {
                return $this->respondWithErrors([
                    'email' => 'Account has been disabled.'
                ]);
            }

            $token = $user->createToken('user_token')->plainTextToken;

            $user->logins()->create([
                'token' => $token,
                'login' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return $this->respondAsResource(
                $user->makeVisible(['permissions']),
                ['appendData' => ['token' => $token]]
            );
        }//end if

        return $this->respondWithErrors([
            'email' => 'Invalid email or password',
            'password' => 'Invalid email or password',
        ]);
    }

    /**
     * Logout current user
     *
     * @param Request $request Current request data.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->logins()->where('token', $user->currentAccessToken())->update(['logout' => now()]);
        $user->currentAccessToken()->delete();

        return $this->respondNoContent();
    }
}

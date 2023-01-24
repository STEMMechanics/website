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
     * @return JsonResponse
     */
    public function me(Request $request)
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
        $user = User::where('username', '=', $request->input('username'))->first();

        if ($user !== null && Hash::check($request->input('password'), $user->password) === true) {
            if ($user->email_verified_at === null) {
                return $this->respondWithErrors([
                    'username' => 'Email address has not been verified.'
                ]);
            }

            if ($user->disabled === true) {
                return $this->respondWithErrors([
                    'username' => 'Account has been disabled.'
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
                ['token' => $token]
            );
        }//end if

        return $this->respondWithErrors([
            'username' => 'Invalid username or password',
            'password' => 'Invalid username or password',
        ]);
    }

    /**
     * Logout current user
     *
     * @param Request $request Current request data.
     * @return JsonResponse
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        $user->logins()->where('token', $user->currentAccessToken())->update(['logout' => now()]);
        $user->currentAccessToken()->delete();

        return $this->respondNoContent();
    }
}

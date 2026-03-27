<?php

namespace App\Http\Controllers\OpenId;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenIDConnect\ClaimExtractor;

class UserInfoController extends Controller
{
    public function __invoke(Request $request, ClaimExtractor $claimExtractor): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $token = $user->token();
        $scopes = [];

        if (is_object($token)) {
            if (isset($token->oauth_scopes) && is_array($token->oauth_scopes)) {
                $scopes = array_values(array_filter(array_map('strval', $token->oauth_scopes)));
            } elseif (method_exists($token, 'getScopes')) {
                $scopes = array_values(array_map(
                    static fn (object $scope): string => (string) $scope->getIdentifier(),
                    $token->getScopes()
                ));
            } elseif (isset($token->scopes) && is_array($token->scopes)) {
                $scopes = array_values(array_filter(array_map('strval', $token->scopes)));
            }
        }

        $identity = app(config('openid.repositories.identity'))->getByIdentifier((string) $user->getAuthIdentifier());
        $claims = $claimExtractor->extract($scopes, $identity->getClaims($scopes));
        $claims['sub'] = (string) $user->getAuthIdentifier();

        return response()->json($claims);
    }
}

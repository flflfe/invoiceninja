<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Middleware;

use App\Events\User\UserLoggedIn;
use App\Models\CompanyToken;
use App\Models\User;
use Closure;

class TokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->header('X-API-TOKEN') && ($company_token = CompanyToken::with(['user','company'])->whereRaw("BINARY `token`= ?", [$request->header('X-API-TOKEN')])->first())) {
            $user = $company_token->user;
            

            $error = [
                'message' => 'User inactive',
                'errors' => []
            ];
            //user who once existed, but has been soft deleted
            if (!$user) {
                return response()->json(json_encode($error, JSON_PRETTY_PRINT), 403);
            }

            /*
            |
            | Necessary evil here: As we are authenticating on CompanyToken,
            | we need to link the company to the user manually. This allows
            | us to decouple a $user and their attached companies completely.
            |
            */
            $user->setCompany($company_token->company);

            //user who once existed, but has been soft deleted
            if ($user->company_user->is_locked) {
                $error = [
                    'message' => 'User access locked',
                    'errors' => []
                ];

                return response()->json(json_encode($error, JSON_PRETTY_PRINT), 403);
            }
   
            //stateless, don't remember the user.
            auth()->login($user, false);

            event(new UserLoggedIn($user));
        } else {
            $error = [
                'message' => 'Invalid token',
                'errors' => []
            ];

            return response()->json(json_encode($error, JSON_PRETTY_PRINT), 403);
        }

        return $next($request);
    }
}
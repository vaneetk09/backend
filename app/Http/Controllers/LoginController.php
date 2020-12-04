<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Validation;
use Illuminate\Validation\ValidationException;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;
use App\Models\Company;
use App\Models\Tree;
use DB;

class LoginController extends Controller
{
    use UsesLandlordConnection;

    public function login(Request $request) {
    	$request->validate([
    		"email" => ["required"],
    		"password" => ["required"]
    	]);

    	if(Auth::attempt($request->only(["email", "password"]))) {
            $user = auth()->user();
            $company = DB::connection($this->getConnectionName())->table('user_company')->where('user_id', $user->id)->select('company_id')->first();
            $tree = Tree::where('company_id', $company->company_id)->first();
            $tenant = Tenant::where('tree_id', $tree->id)->first();
            $tenant->makeCurrent();
    		return response()->json(auth()->user(), 200);
    	}

    	throw ValidationException::withMessages([
    		'email' => ['The provided credentials are incorrect.']
    	]);
    }

    /**
     * Redirect the user to the Provider authentication page.
     *
     * @param $provider
     * @return JsonResponse
     */
    public function redirectToProvider($provider)
    {
        $validated = $this->validateProvider($provider);
        if (!is_null($validated)) {
            return $validated;
        }

        return Socialite::driver($provider)->stateless()->redirect();
    }

    /**
     * Obtain the user information from Provider.
     *
     * @param $provider
     * @return JsonResponse
     */
    public function handleProviderCallback($provider)
    {
        $validated = $this->validateProvider($provider);
        if (!is_null($validated)) {
            return $validated;
        }
        try {
            $user = Socialite::driver($provider)->stateless()->user();
        } catch (ClientException $exception) {
            return response()->json(['error' => 'Invalid credentials provided.'], 422);
        }

        $userCreated = User::firstOrCreate(
            [
                'email' => $user->getEmail()
            ],
            [
                'email_verified_at' => now(),
                'name' => $user->getName(),
                'status' => true,
            ]
        );
        $userCreated->providers()->updateOrCreate(
            [
                'provider' => $provider,
                'provider_id' => $user->getId(),
            ],
            [
                'avatar' => $user->getAvatar()
            ]
        );
        $token = $userCreated->createToken('token-name')->plainTextToken;

        return response()->json($userCreated, 200, ['Access-Token' => $token]);
    }

    /**
     * @param $provider
     * @return JsonResponse
     */
    protected function validateProvider($provider)
    {
        if (!in_array($provider, ['facebook', 'google'])) {
            return response()->json(['error' => 'Please login using facebook or google'], 422);
        }
    }

    public function logout(Request $request) {
    	Auth::logout();
    }
}

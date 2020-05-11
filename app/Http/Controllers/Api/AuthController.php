<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Notifications\AuthNotification;
use App\Mail\WelcomeMail;
use Carbon\Carbon;
use App\User;
use App;

class AuthController extends Controller
{
    /**
     * Create user
     *
     * @param  [string] name
     * @param  [string] email
     * @param  [string] password
     * @param  [string] password_confirmation
     * @return [string] message
     */
    public function signup(Request $request)
    {

        $messages = [
            'name.required'    => 'Enter full name!',
            'email.required' => 'Enter an e-mail address!',
            'email' => 'E-mail address exist!',
            'password.required'    => 'Password is required',
            'password_confirmation' => 'The :password and :password_confirmation must match.'
        ];

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required',
            'password_confirmation' => 'required|same:password',
        ], $messages);

        $user = User::where('email', $request->get('email'))->withCount('likes', 'posts')->first();


        if ($user) {
            return response()->json([
                'status' => 'exist',
                'message' => 'User already exist. please login',
            ], 409);
        } elseif ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 406);
        } else {
            //generate referrer code if user is registringg as referrer
            $referrer_code = null;
            // Delimit by multiple spaces, hyphen, underscore, comma
            $words = preg_split("/[\s,_-]+/", $request->name);
            $acronym = "";
            foreach ($words as $w) {
                $acronym .= $w[0];
            }
            $number = mt_rand(10000000, 99999999);
            $referrer_code = (strtoupper($acronym) . $number);

            // insert new record
            $input = $request->all();
            $input['password'] = bcrypt($input['password']);
            // $input['activation_token'] = str_random(60);
            $input['referrer_code'] = $referrer_code;

            $user = User::create($input);

            Follow::create([
                'user_id' => $user->id,
                'follow_id' => 1,
                'type' => 'user',
            ]);

            if ($request->get('type') == 'mentor') {
                SocialLink::create([
                    'user_id' => $user->id,
                    'facebook' => $request->get('facebook'),
                    'twitter' => $request->get('twitter'),
                    'linkedin' => $request->get('linkedin'),
                    'instagram' => $request->get('instagram'),
                ]);
                //send mentors pre-confirmation email
                Mail::to($user)->send(new MentorsPreconfirmationMessage($user));
            } elseif ($request->get('type') == 'organisation') {
                Organisation::create([
                    'user_id' => $user->id,
                    'name' => $request->get('name'),
                    'email' => $request->get('email'),
                    'phone' => $request->get('phone'),
                    'website' => $request->get('website'),
                    'address' => $request->get('address'),
                    'contact_name' => $request->get('contact_name'),
                    'contact_phone' => $request->get('contact_phone'),
                    'contact_email' => $request->get('contact_email'),
                ]);
                SocialLink::create([
                    'user_id' => $user->id,
                    'facebook' => $request->get('facebook'),
                    'twitter' => $request->get('twitter'),
                    'linkedin' => $request->get('linkedin'),
                    'instagram' => $request->get('instagram'),
                ]);

                Mail::to($user)->send(new MentorsPreconfirmationMessage($user));
            }

            Mail::to($user)->send(new WelcomeMail($user, 'mentee'));

            $title = 'Signup Notification';
            $body = 'Welcome to 5minutes ';
            $user->notify(new AuthNotification($user, $title, $body));

            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;
            $token->save();

            try {
                if ($request->interest) {
                    for ($i = 0; $i < count($request->interest); $i++) {
                        PostInterest::create([
                            'user_id' => $user->id,
                            'category_id' => $request->interest[$i],
                        ]);
                    }
                }
            } catch (\Throwable $th) {
                //throw $th;
            }

            $organization = null;

            try {
                if ($request->organization) {
                    $organization = User::where('slug', $request->organization)->first();
                    UserOrganization::create([
                        'user_id' => $user->id,
                        'organization_id' => $organization->id,
                        'is_mentor' => true,
                        'is_staff' => true
                    ]);
                }
            } catch (\Throwable $th) {
                //throw $th;
            }

            try {
                if ($request->department) {
                    UserDepartment::create([
                        'user_id' => $user->id,
                        'department_id' => $request->department
                    ]);
                }
            } catch (\Throwable $th) {
                //throw $th;
            }

            return response()->json([
                'status' => 'successful',
                'message' => 'User created successfully',
                'access_token' => $tokenResult->accessToken,
                'data' => $user->loadCount('likes', 'posts', 'followers', 'following','mentor_organization',
                'mentor_organization.organization:id,name')
                ->load('mentor_organization','mentor_organization.organization:id,name'),
            ]);
        }


        // $request->validate([
        //     'name' => 'required|string',
        //     'email' => 'required|string|email|unique:users',
        //     'password' => 'required|string|confirmed'
        // ]);


        // $input = $request->all();
        // $input['password'] = bcrypt($request->password);
        // $user = User::create($input);

        // $avatar = Avatar::create($user->name)->getImageObject()->encode('png');
        // Storage::put('avatars/'.$user->id.'/avatar.png', (string) $avatar);

        // // $user->notify(new SignupActivate($user));

        // $tokenResult = $user->createToken('Personal Access Token');
        // $token = $tokenResult->token;

        // return response()->json([
        //     'status' => 'success',
        //     'access_token' => $tokenResult->accessToken,
        //     // 'token_type' => 'Bearer',
        //     // 'expires_at' => Carbon::parse(
        //     //     $tokenResult->token->expires_at
        //     // )->toDateTimeString(),
        //     'message' => 'Registration Successful'
        // ], 201);

    }

    /**
     * Login user and create token
     *
     * @param  [string] email
     * @param  [string] password
     * @param  [boolean] remember_me
     * @return [string] access_token
     * @return [string] token_type
     * @return [string] expires_at
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $credentials = request(['email', 'password']);
        $credentials['active'] = 1;
        $credentials['deleted_at'] = null;

        if (!Auth::attempt($credentials))
            return response()->json([
                'status' => 'failed',
                'message' => 'Unauthorized'
            ], 401);

        if (!Auth::user()->active)
            return response()->json([
                'status' => 'failed',
                'message' => 'Your account is under review'
            ], 401);

        $user = User::where('id', Auth::user()->id)->withCount('likes', 'posts', 'followers', 'following',
         'running_subscription'
        )->with('mentor_organization','mentor_organization.organization:id,name')->first();

        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->token;

        if ($request->remember_me)
            $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();

        return response()->json([
            'status' => 'success',
            'access_token' => $tokenResult->accessToken,
            'message' => 'login successful',
            'data' => $user
        ]);
    }

    /**
     * Logout user (Revoke the token)
     *
     * @return [string] message
     */
    public function logout(Request $request)
    {
        $user = Auth::user();
        $request->user()->token()->revoke();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out'
        ]);
    }

    /**
     * Get the authenticated User
     *
     * @return [json] user object
     */
    public function user(Request $request)
    {
        $user = User::where('id', Auth::user()->id)->withCount('likes', 'posts', 'followers', 'following', 'running_subscription'
        )->with('mentor_organization','mentor_organization.organization:id,name')->first();
        return response()->json([
            'status' => 'success',
            'message' => 'user fetched',
            'data' => $user
        ]);
    }

    public function signupActivate($token)
    {
        $user = User::where('activation_token', $token)->first();
        if (!$user) {
            return response()->json([
                'status' => 'failed',
                'message' => 'This activation token is invalid.'
            ], 404);
        }
        $user->active = true;
        $user->activation_token = '';
        $user->save();
        return $user;
    }
}

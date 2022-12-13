<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed'
        ]);
        if (!$validator->fails()) {
            DB::beginTransaction();
            try {
                //Set data
                $user = new User();
                $user->name = $request->name;
                $user->email = $request->email;
                $user->password = Hash::make($request->password); //encrypt password
                $user->save();
                DB::commit();
                return $this->getResponse201('user account', 'created', $user);
            } catch (Exception $e) {
                DB::rollBack();
                return $this->getResponse500([$e->getMessage()]);
            }
        } else {
            return $this->getResponse500([$validator->errors()]);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required'
        ]);
        if (!$validator->fails()) {
            $user = User::where('email', '=', $request->email)->first();
            if (isset($user->id)) {
                if (Hash::check($request->password, $user->password)) {
                    
                    // foreach ($user->tokens as $token) { //Revoke all previous tokens
                    //     $token->delete();
                    // }

                    //Create token
                    $token = $user->createToken('auth_token')->plainTextToken;
                    return response()->json([
                        'message' => "Successful authentication",
                        'access_token' => $token,
                    ], 200);
                } else { //Invalid credentials
                    // return $this->getResponse401([]); // Me mandaba un error de getResponse401 does not exist.
                    return response()->json(['message' => 'Invalid credentials'], 401);
                }
            } else { //User not found
                  // return $this->getResponse401([]); // Me mandaba un error de getResponse401 does not exist.
                  return response()->json(['message' => 'Invalid credentials'], 401);
                }
        } else {
            return $this->getResponse500([$validator->errors()]);
        }
    }

    public function userProfile()
    {
        return $this->getResponse200(auth()->user());
    }

    public function logout(Request $request)
    {
        // $request->user()->tokens()->delete(); //Revoke all tokens

        // Revoke the token that was used to authenticate the current request...
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => "Logout successful"
        ], 200);
    }
   
    public function changePassword(Request $request)
    {
        

        $validator = Validator::make($request->all(), [
            'password' => 'required',
            'password_confirmation' => 'required'
        ]);
        if (!$validator->fails()) {
            DB::beginTransaction();
            try {

                if(trim($request->password) != trim($request->password_confirmation)){
                    throw new Exception("Las contraseñas no son las mismas.");
                }

                //Set data
                $user = User::find(auth()->user()->id);
                $user->password = Hash::make(trim($request->password)); //encrypt password
                $user->update();

                $request->user()->tokens()->delete(); //Revoke all tokens

                DB::commit();
                return response()->json(['message' =>'Your password has been successfully updated'], 201);
            } catch (Exception $e) {
                DB::rollBack();
                return $this->getResponse500([$e->getMessage()]);
            }
        } else {
            return $this->getResponse500([$validator->errors()]);
        }
    }
}

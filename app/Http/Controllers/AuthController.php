<?php

namespace App\Http\Controllers;

use App\Image;
use App\UserImages;
use http\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\User;
use Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
class AuthController extends Controller
{
    public $successStatus = 200;

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
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'phone' => 'required|regex:/(0)[0-9]{9}/|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 401);
        }
        $input = $request->all();
        $input['password'] = bcrypt($input['password']);
        $user = User::create($input);
        $tokenResult = $user->createToken('Personal Access Token');
        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString()], $this->successStatus);
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
            'phone' => 'required|regex:/(0)[0-9]{9}/',
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);
        $credentials = request(['phone', 'password']);
        if (!Auth::attempt($credentials))
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        $user = $request->user();
        $tokenResult = $user->createToken('Personal Access Token');
        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Logout user (Revoke the token)
     *
     * @return [string] message
     */
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    public function update($id, Request $request)
    {
        User::where('id', $id)->update($request->all());
        return response()->json([
            'message' => 'Successfully update'
        ]);
    }

    /**
     * Get the authenticated User
     *
     * @return [json] user object
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }
    public function allUser(Request $request)
    {
        if($userid = Auth::guard('api')->user()->level=='1') {
            return response()->json(User::get());
        }
        else return response()->json(['message' => 'asdasdasdasdasd']);
    }
    function changePassword(Request $request)
    {
        $input = $request->all();
        $userid = Auth::guard('api')->user()->id;
        $rules = array(
            'old_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        );
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            $arr = array("status" => 400, "message" => $validator->errors()->first(), "data" => array());
        } else {
            try {
                if ((Hash::check(request('old_password'), Auth::user()->password)) == false) {
                    $arr = array("status" => 400, "message" => "Check your old password.", "data" => array());
                } else if ((Hash::check(request('new_password'), Auth::user()->password)) == true) {
                    $arr = array("status" => 400, "message" => "Please enter a password which is not similar then current password.", "data" => array());
                } else {
//                    $input['new_password'] = bcrypt($input['new_password']);
                    User::where('id', $userid)->update(['password' => Hash::make($input['new_password'])]);
                    $arr = array("status" => 200, "message" => "Password updated successfully.", "data" => array());
                }
            } catch (Exception $ex) {
                if (isset($ex->errorInfo[2])) {
                    $msg = $ex->errorInfo[2];
                } else {
                    $msg = $ex->getMessage();
                }
                $arr = array("status" => 400, "message" => $msg, "data" => array());
            }
        }
        return Response()->json($arr);
    }
    public function fileUpload($key,Request $request){
        if($key=='images') {
            $input = $request->all();
            $File = $input;
            foreach ($File as $file) {
                $sub_path = 'files';
                $datetime = new \DateTime();
                $real_name = $datetime->format('Y-m-d_H-i-s') . '_' . $file->getClientOriginalName();
                $destination_path = public_path($sub_path);
                $saveImage = $file->move($destination_path, $real_name);
                if ($saveImage) {
                    $userid = Auth::guard('api')->user()->id;
                    $save = UserImages::create([
                        'image_path' => 'http://ad6358f65535.ngrok.io/' . $sub_path . '/' . $real_name,
                        'user_id' => $userid,
                        'is_avatar' => 'false'
                    ]);
                    var_dump($userid);
                }
            }
        }
        if($key == 'avatar')
        {
            $input = $request->all();
            $File = $input;
            foreach ($File as $file) {
                $sub_path = 'files';
                $datetime = new \DateTime();
                $real_name = $datetime->format('Y-m-d_H-i-s') . '_' . $file->getClientOriginalName();
                $destination_path = public_path($sub_path);
                $saveImage = $file->move($destination_path, $real_name);
                if ($saveImage) {
                    $userid = Auth::guard('api')->user()->id;
                    $save = UserImages::create([
                        'image_path' => 'http://ad6358f65535.ngrok.io/' . $sub_path . '/' . $real_name,
                        'user_id' => $userid,
                        'is_avatar' => 'true'
                    ]);
                    User::where('id', $userid)->update(['avatar_url'=>'http://ad6358f65535.ngrok.io/' . $sub_path . '/' . $real_name]);
                    var_dump($userid);
                }
            }
        }
    }
    public function getImageUpload(Request $request){
        $userid = Auth::guard('api')->user()->id;
        return response()->json(UserImages::where('user_id', $userid)->get());
    }
    public function deleteImage($id,Request $request){
//        $product = UserImages::where('id',$id)->get();
//        $productImage = $product[0]->image_path;
//        $productImage ='/public/files/' .basename($productImage);
//        if(File::exists($productImage)){
//            File::delete($productImage);
//        }
        UserImages::where('id', $id)->delete();
        return response()->json([
            'message' => 'Delete image success'
        ]);
    }
    public function multiDeleteImages(Request $request){
        $File = $request->all();
        foreach ($File as $file){

            User::where('avatar_url',$file['url'])->update(['avatar_url' => null]);
            UserImages::where('id', $file['id'])->delete();
        }
        return response()->json([
            'message' => 'Delete image success'
        ]);
    }

}

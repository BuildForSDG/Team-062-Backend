<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\AuthNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\User;
use Auth;

class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        dd('index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        dd('create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        dd('store');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        dd('show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        dd('edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();

        $profile = User::find(auth()->user()->id);
        $profile->name = $request->name;
        $profile->contact = $request->contact;
        $profile->save();
       
        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated',
            'data' => $profile
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        dd('destrol');
    }

    public function avatar(Request $request){
       
        $name = $request->file('data')->getClientOriginalName();
        $extension = $request->file('data')->getClientOriginalExtension();
        $file_name = 'ur-report-'.auth()->user()->id .  strtotime(Carbon::now()) . '-' . $name;
       
        Storage::disk('public')->putFileAs('profile/' . auth()->user()->id, new File($request->file('data')), $file_name);

       User::find(auth()->user()->id)->update(['image' => $file_name]);

        return response()->json([
            'status' => 'success',
            'message' => 'avatar updated',
            'data' => $file_name
        ]);
    }

}

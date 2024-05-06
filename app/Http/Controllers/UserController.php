<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::query();

        if($request->has('search')) {
            $query->where('firstname', 'like', '%' . $request->search . '%');
            $query->orWhere('surname', 'like', '%' . $request->search . '%');
            $query->orWhere('phone', 'like', '%' . $request->search . '%');
            $query->orWhere('email', 'like', '%' . $request->search . '%');
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(12)->onEachSide(1);

        return view('admin.user.index', [
            'users' => $users
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.user.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'firstname' => '',
            'surname' => '',
            'email' => 'email|unique:users',
            'phone' => '',

            'home_address' => 'required_with:home_city,home_postcode,home_country,home_state',
            'home_city' => 'required_with:home_address,home_postcode,home_country,home_state',
            'home_postcode' => 'required_with:home_address,home_city,home_country,home_state',
            'home_country' => 'required_with:home_address,home_city,home_postcode,home_state',
            'home_state' => 'required_with:home_address,home_city,home_postcode,home_country',

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

            'home_address.required' => __('validation.custom_messages.home_address_required'),
            'home_city.required' => __('validation.custom_messages.home_city_required'),
            'home_postcode.required' => __('validation.custom_messages.home_postcode_required'),
            'home_country.required' => __('validation.custom_messages.home_country_required'),
            'home_state.required' => __('validation.custom_messages.home_state_required'),

            'billing_address.required' => __('validation.custom_messages.billing_address_required'),
            'billing_city.required' => __('validation.custom_messages.billing_city_required'),
            'billing_postcode.required' => __('validation.custom_messages.billing_postcode_required'),
            'billing_country.required' => __('validation.custom_messages.billing_country_required'),
            'billing_state.required' => __('validation.custom_messages.billing_state_required'),
        ]);

        User::create($request->all());

        session()->flash('message', 'User has been created');
        session()->flash('message-title', 'User created');
        session()->flash('message-type', 'success');
        return redirect()->route('admin.user.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        return view('admin.user.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'firstname' => '',
            'surname' => '',
            'email' => ['email', Rule::unique('users')->ignore($user->id)],
            'phone' => '',

            'home_address' => 'required_with:home_city,home_postcode,home_country,home_state',
            'home_city' => 'required_with:home_address,home_postcode,home_country,home_state',
            'home_postcode' => 'required_with:home_address,home_city,home_country,home_state',
            'home_country' => 'required_with:home_address,home_city,home_postcode,home_state',
            'home_state' => 'required_with:home_address,home_city,home_postcode,home_country',

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

            'home_address.required' => __('validation.custom_messages.home_address_required'),
            'home_city.required' => __('validation.custom_messages.home_city_required'),
            'home_postcode.required' => __('validation.custom_messages.home_postcode_required'),
            'home_country.required' => __('validation.custom_messages.home_country_required'),
            'home_state.required' => __('validation.custom_messages.home_state_required'),

            'billing_address.required' => __('validation.custom_messages.billing_address_required'),
            'billing_city.required' => __('validation.custom_messages.billing_city_required'),
            'billing_postcode.required' => __('validation.custom_messages.billing_postcode_required'),
            'billing_country.required' => __('validation.custom_messages.billing_country_required'),
            'billing_state.required' => __('validation.custom_messages.billing_state_required'),
        ]);

        $user->update($request->all());

        session()->flash('message', 'User details have been updated');
        session()->flash('message-title', 'Details updated');
        session()->flash('message-type', 'success');
        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        if($user->id !== '1') {
            $user->delete();
            session()->flash('message', 'User has been deleted');
            session()->flash('message-title', 'User deleted');
            session()->flash('message-type', 'success');
        } else {
            session()->flash('message', 'You cannot delete the main admin user');
            session()->flash('message-title', 'User not deleted');
            session()->flash('message-type', 'error');
        }

        return redirect()->route('admin.user.index');
    }
}

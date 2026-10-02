<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedTraveller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'status' => $user->status,
                'created_at' => $user->created_at?->toIso8601String(),
                'stats' => [
                    'total_bookings' => $user->bookings()->count(),
                    'upcoming_trips' => $user->bookings()->whereIn('status', ['confirmed', 'payment_pending'])->count(),
                ],
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }

    public function savedTravellers(Request $request)
    {
        $travellers = $request->user()->savedTravellers()->latest()->get();

        return response()->json([
            'data' => $travellers->map(fn (SavedTraveller $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'first_name' => $t->first_name,
                'last_name' => $t->last_name,
                'full_name' => $t->full_name,
                'dob' => $t->dob?->format('Y-m-d'),
                'gender' => $t->gender,
                'id_type' => $t->id_type,
                'id_number' => $t->id_number,
            ]),
        ]);
    }

    public function storeSavedTraveller(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|in:Mr,Mrs,Ms,Miss,Master',
            'first_name' => 'required|string|max:60',
            'last_name' => 'required|string|max:60',
            'dob' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'id_type' => 'nullable|string|max:30',
            'id_number' => 'nullable|string|max:40',
        ]);

        $traveller = $request->user()->savedTravellers()->create($validated);

        return response()->json([
            'message' => 'Traveller saved successfully.',
            'data' => [
                'id' => $traveller->id,
                'full_name' => $traveller->full_name,
                'title' => $traveller->title,
                'first_name' => $traveller->first_name,
                'last_name' => $traveller->last_name,
            ],
        ], 201);
    }
}

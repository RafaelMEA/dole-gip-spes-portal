<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Http\Resources\StudentProfileResource;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Show the authenticated student's profile and student details.
     */
    public function show(Request $request)
    {
        return new StudentProfileResource(
            $request->user()->load('studentDetail'),
        );
    }

    /**
     * Update the student's profile and student details.
     */
    public function update(UpdateStudentProfileRequest $request)
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['name' => $request->validated('name')]);

        $user->studentDetail()->updateOrCreate(
            ['user_id' => $user->id],
            $request->safe()->only([
                'school_name', 'course', 'year_level', 'gwa',
                'address', 'birthplace', 'birthdate', 'sex',
                'is_indigent', 'is_4ps_member',
            ]),
        );

        return new StudentProfileResource($user->load('studentDetail'));
    }
}

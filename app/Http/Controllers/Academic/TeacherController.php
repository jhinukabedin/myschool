<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\ExaminationSubject;
use App\Models\TeacherAssignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherController extends Controller
{
    /**
     * Teacher landing page. Teachers only ever see the classes, sections and
     * subjects they are actively assigned to.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $staff = $user->staff;
        $session = current_session();

        $assignments = collect();
        $students = collect();
        $examSubjects = collect();

        if ($staff) {
            $assignments = TeacherAssignment::query()
                ->with(['class', 'section', 'subject', 'academicSession'])
                ->where('staff_id', $staff->id)
                ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
                ->effective()
                ->orderBy('class_id')
                ->orderBy('section_id')
                ->get();

            if ($assignments->isNotEmpty()) {
                $students = Enrollment::query()
                    ->with(['student', 'class', 'section'])
                    ->where('academic_session_id', $assignments->first()->academic_session_id)
                    ->where('status', 'active')
                    ->where(function ($query) use ($assignments) {
                        foreach ($assignments as $assignment) {
                            $query->orWhere(function ($inner) use ($assignment) {
                                $inner->where('class_id', $assignment->class_id);

                                if ($assignment->section_id) {
                                    $inner->where('section_id', $assignment->section_id);
                                }
                            });
                        }
                    })
                    ->orderBy('class_id')
                    ->orderBy('section_id')
                    ->orderBy('roll_no')
                    ->get();

                $examSubjects = ExaminationSubject::query()
                    ->with(['examination', 'class', 'subject'])
                    ->whereIn('class_id', $assignments->pluck('class_id')->unique())
                    ->whereIn('subject_id', $assignments->pluck('subject_id')->unique())
                    ->whereHas('examination', fn ($query) => $query->where('academic_session_id', $assignments->first()->academic_session_id))
                    ->orderBy('exam_date')
                    ->get();
            }
        }

        return view('teacher.dashboard', [
            'staff' => $staff,
            'session' => $session,
            'assignments' => $assignments,
            'students' => $students,
            'examSubjects' => $examSubjects,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Examination;
use App\Models\GradingScheme;
use App\Models\Mark;
use App\Models\ParentStudentLink;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentPortalLink;
use App\Models\User;
use App\Services\ExamResultService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Adds portal demo accounts and published snapshots to an existing demo
 * database. DemoDataSeeder intentionally skips itself once students exist, so
 * this seeder is safe to run repeatedly after new portal features are added.
 */
class DemoPortalSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->first();

        if (! $admin) {
            return;
        }

        $students = Student::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->limit(2)
            ->get();

        if ($students->count() < 2) {
            return;
        }

        $roles = Role::query()->pluck('id', 'slug');

        if (! isset($roles['student'], $roles['parent'])) {
            return;
        }

        $password = Hash::make(env('DEMO_PASSWORD', 'Admin@12345'));

        $studentUser = User::updateOrCreate(
            ['email' => 'student@edusphere.test'],
            [
                'role_id' => $roles['student'],
                'name' => $students[0]->full_name,
                'password' => $password,
                'is_active' => true,
            ]
        );

        StudentPortalLink::updateOrCreate(
            ['user_id' => $studentUser->id],
            [
                'student_id' => $students[0]->id,
                'verified_at' => now(),
                'is_active' => true,
                'created_by' => $admin->id,
            ]
        );

        $parentUser = User::updateOrCreate(
            ['email' => 'parent@edusphere.test'],
            [
                'role_id' => $roles['parent'],
                'name' => 'Demo Guardian',
                'phone' => '+880 1700 000099',
                'password' => $password,
                'is_active' => true,
            ]
        );

        foreach ($students as $child) {
            $guardian = $child->guardians()->first();

            ParentStudentLink::updateOrCreate(
                ['user_id' => $parentUser->id, 'student_id' => $child->id],
                [
                    'student_guardian_id' => $guardian?->id,
                    'relationship' => $guardian?->relation ?? 'Guardian',
                    'verified_at' => now(),
                    'is_active' => true,
                    'created_by' => $admin->id,
                ]
            );
        }

        $exam = Examination::query()
            ->where('status', 'published')
            ->orderByDesc('start_date')
            ->first();

        if (! $exam) {
            return;
        }

        if (! $exam->grading_scheme_id) {
            $scheme = GradingScheme::query()
                ->where('name', 'Institution Standard 2026')
                ->where('academic_session_id', $exam->academic_session_id)
                ->first();

            if ($scheme) {
                $exam->update(['grading_scheme_id' => $scheme->id]);
            }
        }

        $subjectIds = $exam->subjects()->pluck('id');

        if ($subjectIds->isEmpty() || ! Mark::whereIn('examination_subject_id', $subjectIds)->exists()) {
            return;
        }

        Mark::query()
            ->whereIn('examination_subject_id', $subjectIds)
            ->whereIn('status', ['draft', 'submitted'])
            ->update([
                'status' => 'approved',
                'submitted_at' => now(),
                'submitted_by' => $admin->id,
                'approved_at' => now(),
                'approved_by' => $admin->id,
            ]);

        $exam->update([
            'result_published_at' => $exam->result_published_at ?? now(),
            'result_locked_at' => $exam->result_locked_at ?? now(),
            'published_by' => $exam->published_by ?? $admin->id,
            'locked_by' => $exam->locked_by ?? $admin->id,
        ]);

        app(ExamResultService::class)->publishSnapshots($exam->fresh(), $admin);
    }
}

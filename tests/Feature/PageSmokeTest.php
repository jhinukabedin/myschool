<?php

namespace Tests\Feature;

use App\Models\AdmissionEnquiry;
use App\Models\Examination;
use App\Models\Expense;
use App\Models\FeeStructure;
use App\Models\Notice;
use App\Models\Payroll;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_authenticated_page_renders_for_the_administrator(): void
    {
        $this->seed();

        $admin = User::where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
        $this->actingAs($admin);

        $student = Student::query()->where('status', 'active')->firstOrFail();
        $archived = Student::onlyTrashed()->first();
        $structure = FeeStructure::firstOrFail();
        $exam = Examination::firstOrFail();
        $expense = Expense::withTrashed()->firstOrFail();
        $staff = Staff::firstOrFail();
        $payroll = Payroll::firstOrFail();
        $notice = Notice::firstOrFail();
        $class = SchoolClass::firstOrFail();
        $section = Section::firstOrFail();
        $role = Role::firstOrFail();
        $subject = Subject::firstOrFail();
        $enquiry = AdmissionEnquiry::firstOrFail();
        $user = User::whereKeyNot($admin->id)->firstOrFail();

        $routes = [
            'dashboard' => route('dashboard'),

            'students.index' => route('students.index'),
            'students.show' => route('students.show', $student),
            'students.edit' => route('students.edit', $student),
            'students.archive' => route('students.archive'),
            'admissions.create' => route('admissions.create'),
            'enquiries.index' => route('enquiries.index'),
            'enquiries.create' => route('enquiries.create'),
            'enquiries.edit' => route('enquiries.edit', $enquiry),
            'promotion.index' => route('promotion.index'),
            'session-report.index' => route('session-report.index'),
            'session-report.export' => route('session-report.export'),

            'fees.structures.index' => route('fees.structures.index'),
            'fees.structures.create' => route('fees.structures.create'),
            'fees.structures.edit' => route('fees.structures.edit', $structure),
            'fees.collection.index' => route('fees.collection.index'),
            'fees.collection.show' => route('fees.collection.show', $student),
            'fees.quick.index' => route('fees.quick.index'),
            'fees.reminders.index' => route('fees.reminders.index'),
            'fees.deletion-log' => route('fees.deletion-log'),
            'payments.receipt' => route('payments.receipt', $student->payments()->firstOrFail()),

            'attendance.index' => route('attendance.index'),
            'attendance.report' => route('attendance.report'),
            'attendance.report.export' => route('attendance.report.export'),
            'exams.index' => route('exams.index'),
            'exams.create' => route('exams.create'),
            'exams.show' => route('exams.show', $exam),
            'exams.edit' => route('exams.edit', $exam),
            'exams.schedule' => route('exams.schedule'),
            'exams.marks-entry' => route('exams.marks-entry'),
            'exams.class-result' => route('exams.class-result', ['exam_id' => $exam->id, 'class_id' => $class->id]),
            'exams.result-sheet' => route('exams.result-sheet', ['exam' => $exam, 'student' => $student]),
            'subjects.index' => route('subjects.index'),
            'subjects.create' => route('subjects.create'),
            'subjects.edit' => route('subjects.edit', $subject),
            'class-subjects.index' => route('class-subjects.index'),
            'grade-rules.index' => route('grade-rules.index'),
            'assignments.index' => route('assignments.index'),
            'assignments.create' => route('assignments.create'),
            'teacher.dashboard' => route('teacher.dashboard'),

            'expenses.index' => route('expenses.index'),
            'expenses.create' => route('expenses.create'),
            'expenses.edit' => route('expenses.edit', $expense),

            'staff.index' => route('staff.index'),
            'staff.create' => route('staff.create'),
            'staff.show' => route('staff.show', $staff),
            'staff.edit' => route('staff.edit', $staff),
            'payroll.index' => route('payroll.index'),
            'payroll.report' => route('payroll.report'),
            'payroll.report.export' => route('payroll.report.export'),
            'payroll.slip' => route('payroll.slip', $payroll),

            'notices.index' => route('notices.index'),
            'notices.create' => route('notices.create'),
            'notices.edit' => route('notices.edit', $notice),

            'reports.index' => route('reports.index'),
            'reports.collection' => route('reports.show', 'collection'),
            'reports.income-expense' => route('reports.show', 'income-expense'),
            'reports.dues' => route('reports.show', 'dues'),
            'reports.partial' => route('reports.show', 'partial'),
            'reports.category-collection' => route('reports.show', 'category-collection'),
            'reports.collectors' => route('reports.show', 'collectors'),
            'reports.expenses' => route('reports.show', 'expenses'),
            'reports.attendance' => route('reports.show', 'attendance'),
            'reports.results' => route('reports.show', 'results'),
            'reports.students' => route('reports.show', 'students'),
            'reports.payroll' => route('reports.show', 'payroll'),
            'reports.export' => route('reports.export', 'collection'),

            'admin.index' => route('admin.index'),
            'sessions.index' => route('sessions.index'),
            'classes.index' => route('classes.index'),
            'sections.index' => route('sections.index'),
            'users.index' => route('users.index'),
            'users.create' => route('users.create'),
            'users.edit' => route('users.edit', $user),
            'roles.index' => route('roles.index'),
            'audit.index' => route('audit.index'),
            'settings.index' => route('settings.index'),
            'website-admin.index' => route('website-admin.index'),
            'website-admin.design' => route('website-admin.design'),
            'website-admin.header' => route('website-admin.header'),
            'website-admin.footer' => route('website-admin.footer'),
            'website-admin.hero' => route('website-admin.hero'),
            'website-admin.sliders' => route('website-admin.sliders'),
            'website-admin.featured-menu' => route('website-admin.featured-menu'),
            'website-admin.sidebar' => route('website-admin.sidebar'),
            'website-admin.notice' => route('website-admin.notice'),
            'website-admin.admission-info' => route('website-admin.admission-info'),
            'website-admin.class-routine' => route('website-admin.class-routine'),
            'website-admin.academic-calendar' => route('website-admin.academic-calendar'),
            'website-admin.syllabus' => route('website-admin.syllabus'),
            'website-admin.video-gallery' => route('website-admin.video-gallery'),
            'website-content.index' => route('website-content.index'),
            'website-content.create' => route('website-content.create'),
            'messages.index' => route('messages.index'),
            'password.change' => route('password.change'),
        ];

        foreach ($routes as $name => $url) {
            $this->get($url)->assertOk();
        }

        if ($archived) {
            $this->get(route('students.show', $archived))->assertOk();
        }
    }
}

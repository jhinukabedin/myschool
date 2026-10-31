<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Mark;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\Role;
use App\Models\SalaryPayment;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamResultService;
use App\Services\FeeAssignmentService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class CoreWorkflowTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    public function test_guests_are_redirected_and_roles_are_authorised(): void
    {
        $session = $this->seedCore();

        $this->get(route('dashboard'))->assertRedirect(route('login'));

        $admin = User::query()->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'Admin@12345',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);

        $teacherRole = Role::query()->where('slug', 'teacher')->firstOrFail();
        $teacher = User::create([
            'role_id' => $teacherRole->id,
            'name' => 'Test Teacher',
            'email' => 'teacher.test@example.com',
            'password' => 'Secret@12345',
            'is_active' => true,
        ]);

        $this->actingAs($teacher)->get(route('attendance.index'))->assertOk();
        $this->actingAs($teacher)->get(route('admin.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('users.index'))->assertForbidden();

        $accountantRole = Role::query()->where('slug', 'accountant')->firstOrFail();
        $accountant = User::create([
            'role_id' => $accountantRole->id,
            'name' => 'Test Accountant',
            'email' => 'accountant.test@example.com',
            'password' => 'Secret@12345',
            'is_active' => true,
        ]);

        $this->actingAs($accountant)->get(route('expenses.index'))->assertOk();
        $this->actingAs($accountant)->get(route('users.index'))->assertForbidden();

        $inactive = User::create([
            'role_id' => $teacherRole->id,
            'name' => 'Inactive Teacher',
            'email' => 'inactive.test@example.com',
            'password' => 'Secret@12345',
            'is_active' => false,
        ]);

        $this->actingAs($inactive)->get(route('dashboard'))->assertForbidden();
    }

    public function test_admission_creates_student_guardian_and_enrollment(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        $class = SchoolClass::query()->where('numeric_level', '>=', 3)->orderBy('numeric_level')->firstOrFail();
        $section = $class->sections()->orderBy('name')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admissions.store'), [
            'first_name' => 'Amina',
            'last_name' => 'Test',
            'gender' => 'female',
            'date_of_birth' => '2014-08-20',
            'phone' => '+880 1712 345678',
            'email' => 'amina.test@example.com',
            'address' => 'Mirpur, Dhaka',
            'city' => 'Dhaka',
            'admission_date' => $session->start_date->toDateString(),
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'guardian_name' => 'Rahim Test',
            'guardian_relation' => 'Father',
            'guardian_phone' => '+880 1712 345679',
            'guardian_email' => 'rahim.test@example.com',
        ]);

        $student = Student::query()->where('email', 'amina.test@example.com')->firstOrFail();

        $response->assertRedirect(route('students.show', $student));

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'first_name' => 'Amina',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $student->id,
            'name' => 'Rahim Test',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => 'active',
        ]);
        $this->assertNotNull($student->admission_no);
    }

    public function test_payment_collection_and_reversal_preserve_the_ledger(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();
        [$student, , $class] = $this->createStudent($session);

        [$assignment, $structure] = $this->assignFee($student->id, $session, $class->id);

        $this->actingAs($admin)->post(route('fees.collection.store', $student), [
            'allocations' => [$assignment->id => 500],
            'payment_method' => 'cash',
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'note' => 'Part payment for testing',
        ])->assertRedirect();

        $payment = Payment::query()->where('student_id', $student->id)->firstOrFail();

        $this->assertSame('completed', $payment->status);
        $this->assertSame('500.00', $payment->amount);
        $this->assertSame('partial', $assignment->fresh()->status);

        $this->actingAs($admin)->get(route('payments.receipt', $payment))->assertOk();

        $this->actingAs($admin)->post(route('payments.reverse', $payment), [
            'type' => 'cancellation',
            'reason' => 'Duplicate collection entered during testing',
        ])->assertRedirect();

        $payment->refresh();

        $this->assertSame('reversed', $payment->status);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'amount' => 500,
            'status' => 'reversed',
        ]);
        $this->assertDatabaseHas('payment_reversals', [
            'payment_id' => $payment->id,
            'type' => 'cancellation',
            'amount' => 500,
            'reason' => 'Duplicate collection entered during testing',
        ]);
        $this->assertSame('pending', $assignment->fresh()->status);
        $this->assertSame(0.0, $assignment->fresh()->paid_amount);

        // The original receipt remains accessible and shows the reversal detail.
        $receipt = $this->actingAs($admin)->get(route('payments.receipt', $payment));
        $receipt->assertOk();
        $receipt->assertSee('Duplicate collection entered during testing');
        $receipt->assertSee($admin->name);

        // The reversal is written to the audit log against the acting user.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment_reversed',
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('payments.reverse', $payment), [
            'type' => 'refund',
            'reason' => 'Attempted second reversal',
        ])->assertSessionHasErrors('payment');
    }

    public function test_quick_collection_allocation_order_is_configurable(): void
    {
        $session = $this->seedCore();
        [$student, , $class] = $this->createStudent($session);

        $this->assignFee($student->id, $session, $class->id);

        $rows = StudentFeeAssignment::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->orderBy('due_date')
            ->get();

        $this->assertGreaterThanOrEqual(2, $rows->count());

        $service = app(PaymentService::class);

        $oldest = $service->autoAllocate($student->fresh(), $session, 1200);
        $this->assertSame($rows->first()->id, array_key_first($oldest));

        SchoolSetting::put('fee_allocation_order', 'newest', 'fees');

        $newest = $service->autoAllocate($student->fresh(), $session, 1200);
        $this->assertSame($rows->last()->id, array_key_first($newest));
    }

    public function test_attendance_is_upserted_per_student_and_day(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();
        [$student, , $class, $section] = $this->createStudent($session);
        $date = '2026-10-20';

        $this->actingAs($admin)->post(route('attendance.store'), [
            'date' => $date,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'statuses' => [$student->id => 'present'],
            'notes' => [$student->id => 'Arrived on time'],
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id,
            'attendance_date' => $date,
            'status' => 'present',
        ]);

        $this->actingAs($admin)->post(route('attendance.store'), [
            'date' => $date,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'statuses' => [$student->id => 'absent'],
        ])->assertRedirect();

        $this->assertSame(
            1,
            AttendanceRecord::query()
                ->where('student_id', $student->id)
                ->whereDate('attendance_date', $date)
                ->count()
        );
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id,
            'attendance_date' => $date,
            'status' => 'absent',
        ]);
    }

    public function test_exam_results_are_ranked_and_failures_are_handled(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();
        [$first, , $class] = $this->createStudent($session, 'First');
        [$second] = $this->createStudent($session, 'Second');

        $exam = Examination::create([
            'academic_session_id' => $session->id,
            'name' => 'Unit Test Examination',
            'exam_type' => 'terminal',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-08',
            'status' => 'published',
        ]);

        $subjects = Subject::query()->limit(2)->get();

        $examSubjects = $subjects->map(fn (Subject $subject) => ExaminationSubject::create([
            'examination_id' => $exam->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'full_marks' => 100,
            'pass_marks' => 33,
            'exam_date' => '2026-11-02',
            'start_time' => '09:00',
            'duration_minutes' => 120,
        ]));

        $service = app(ExamResultService::class);

        $service->enterMark($examSubjects[0], $first, 80, false, $admin);
        $service->enterMark($examSubjects[1], $first, 70, false, $admin);
        $service->enterMark($examSubjects[0], $second, 40, false, $admin);
        $service->enterMark($examSubjects[1], $second, 20, false, $admin);

        $results = $service->meritList($exam->load('subjects'), $class);

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['student']->is($first));
        $this->assertSame(1, $results[0]['rank']);
        $this->assertSame('Pass', $results[0]['result']);
        $this->assertGreaterThan(0, $results[0]['gpa']);
        $this->assertTrue($results[1]['student']->is($second));
        $this->assertSame(2, $results[1]['rank']);
        $this->assertSame('Fail', $results[1]['result']);
        $this->assertSame(0.0, $results[1]['gpa']);
        $this->assertSame(1, $results[1]['failed_subjects']);
    }

    public function test_dashboard_metrics_reflect_recorded_financial_data(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();
        [$student, , $class] = $this->createStudent($session);

        [$assignment] = $this->assignFee($student->id, $session, $class->id);

        $this->actingAs($admin)->post(route('fees.collection.store', $student), [
            'allocations' => [$assignment->id => 500],
            'payment_method' => 'bank',
            'paid_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $metrics = $response->viewData('metrics');

        $this->assertSame(1, $metrics['active_students']);
        $this->assertSame(500.0, $metrics['collected']);
        $this->assertSame(500.0, $metrics['collected_this_month']);
        $this->assertSame(11500.0, $metrics['pending']);
        $this->assertCount(1, $metrics['recent_payments']);
        $this->assertSame($student->id, $metrics['defaulters']->first()['student']->id);
    }

    public function test_reports_and_csv_exports_are_available(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('reports.show', 'collection'))->assertOk();
        $this->actingAs($admin)->get(route('reports.show', 'income-expense'))->assertOk();
        $this->actingAs($admin)->get(route('reports.show', 'results'))->assertOk();

        foreach (['partial', 'category-collection', 'collectors'] as $feeReport) {
            $this->actingAs($admin)->get(route('reports.show', $feeReport))->assertOk();
            $this->actingAs($admin)->get(route('reports.export', $feeReport))->assertOk();
        }

        $staff = Staff::create([
            'employee_no' => 'EMP-TEST-001',
            'first_name' => 'Payroll',
            'last_name' => 'Tester',
            'gender' => 'female',
            'phone' => '+880 1700 111222',
            'joining_date' => $session->start_date->toDateString(),
            'employment_type' => 'full_time',
            'status' => 'active',
            'basic_salary' => 50000,
        ]);

        Payroll::create([
            'academic_session_id' => $session->id,
            'staff_id' => $staff->id,
            'month' => $session->start_date->format('Y').'-09',
            'basic_salary' => 50000,
            'total_allowances' => 5000,
            'total_deductions' => 2000,
            'net_salary' => 53000,
            'status' => 'paid',
        ]);

        $salaryReport = $this->actingAs($admin)->get(route('payroll.report', ['year' => $session->start_date->format('Y')]));
        $salaryReport->assertOk();
        $this->assertCount(1, $salaryReport->viewData('rows'));
        $this->assertSame(53000.0, $salaryReport->viewData('totalNet'));

        $response = $this->actingAs($admin)->get(route('reports.export', 'collection'));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('EduSphere School - Fee Collection Report', $response->streamedContent());

        $salary = $this->actingAs($admin)->get(route('payroll.report.export', ['year' => $session->start_date->format('Y')]));
        $salary->assertOk();
        $this->assertStringContainsString('Salary Report', $salary->streamedContent());

        $attendance = $this->actingAs($admin)->get(route('attendance.report.export'));
        $attendance->assertOk();

        $sessionReport = $this->actingAs($admin)->get(route('session-report.export'));
        $sessionReport->assertOk();
    }

    public function test_fee_structure_discount_cannot_exceed_the_fee_amount(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        $class = SchoolClass::query()->where('numeric_level', '>=', 3)->firstOrFail();
        $category = FeeCategory::query()->firstOrFail();

        $response = $this->actingAs($admin)->post(route('fees.structures.store'), [
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'name' => 'Invalid Discount Structure',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'status' => 'active',
            'items' => [[
                'fee_category_id' => $category->id,
                'amount' => 1000,
                'discount_amount' => 1500,
                'frequency' => 'monthly',
                'due_day' => 10,
            ]],
        ]);

        $response->assertSessionHasErrors('items.0.discount_amount');
        $this->assertDatabaseMissing('fee_structures', ['name' => 'Invalid Discount Structure']);

        $valid = $this->actingAs($admin)->post(route('fees.structures.store'), [
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'name' => 'Valid Discount Structure',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'status' => 'active',
            'items' => [[
                'fee_category_id' => $category->id,
                'amount' => 1000,
                'discount_amount' => 1000,
                'frequency' => 'monthly',
                'due_day' => 10,
            ]],
        ]);

        $valid->assertRedirect(route('fees.structures.index'));
        $this->assertDatabaseHas('fee_structures', ['name' => 'Valid Discount Structure']);
    }

    public function test_salary_payment_can_be_reversed_and_the_original_is_preserved(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        $staff = Staff::create([
            'employee_no' => 'EMP-REV-001',
            'first_name' => 'Reverse',
            'last_name' => 'Tester',
            'gender' => 'male',
            'phone' => '+880 1700 333444',
            'joining_date' => $session->start_date->toDateString(),
            'employment_type' => 'full_time',
            'status' => 'active',
            'basic_salary' => 40000,
        ]);

        $payroll = Payroll::create([
            'academic_session_id' => $session->id,
            'staff_id' => $staff->id,
            'month' => $session->start_date->format('Y').'-09',
            'basic_salary' => 40000,
            'total_allowances' => 2000,
            'total_deductions' => 1000,
            'net_salary' => 41000,
            'status' => 'processed',
        ]);

        $this->actingAs($admin)->post(route('payroll.pay', $payroll), [
            'amount' => 41000,
            'payment_method' => 'bank',
            'reference_no' => 'TRX-REV-1',
            'paid_at' => '2026-09-05 10:00:00',
        ])->assertRedirect();

        $this->assertSame('paid', $payroll->fresh()->status);
        $payment = SalaryPayment::query()->where('payroll_id', $payroll->id)->firstOrFail();
        $this->assertSame('completed', $payment->status);

        // Short reasons are rejected so reversals stay auditable.
        $this->actingAs($admin)
            ->post(route('payroll.reverse', $payroll), ['reason' => 'nope'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('payroll.reverse', $payroll), [
            'reason' => 'Duplicate payment correction',
        ])->assertRedirect();

        $this->assertSame('processed', $payroll->fresh()->status);
        $this->assertSame('reversed', $payment->fresh()->status);
        $this->assertDatabaseHas('salary_payments', ['id' => $payment->id, 'status' => 'reversed']);
        $this->assertDatabaseHas('salary_payment_reversals', [
            'salary_payment_id' => $payment->id,
            'reason' => 'Duplicate payment correction',
        ]);
        $this->assertNull($payroll->fresh()->payment);

        // Original payment kept for audit; both actions logged against the actor.
        $this->assertDatabaseHas('audit_logs', ['action' => 'salary_paid', 'user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'salary_payment_reversed', 'user_id' => $admin->id]);

        // The salary slip shows the reversal reason after the payment is reversed.
        $slip = $this->actingAs($admin)->get(route('payroll.slip', $payroll));
        $slip->assertOk();
        $slip->assertSee('Duplicate payment correction');

        // Reversed payments must not count towards completed payroll totals.
        $month = $session->start_date->format('Y').'-09';
        $year = $session->start_date->format('Y');

        $index = $this->actingAs($admin)->get(route('payroll.index', ['month' => $month]));
        $index->assertOk();
        $this->assertSame(0.0, (float) $index->viewData('summary')['paid']);

        $salaryReport = $this->actingAs($admin)->get(route('payroll.report', ['year' => $year]));
        $salaryReport->assertOk();
        $this->assertSame(0.0, (float) $salaryReport->viewData('totalPaid'));

        $incomeExpense = $this->actingAs($admin)->get(route('reports.show', 'income-expense'));
        $incomeExpense->assertOk();
        $salariesPaid = collect($incomeExpense->viewData('summary'))
            ->firstWhere('label', 'Salaries paid')['value'];
        $this->assertSame(money(0), $salariesPaid);

        // A corrected payment can be recorded against the same payroll.
        $this->actingAs($admin)->post(route('payroll.pay', $payroll), [
            'amount' => 41000,
            'payment_method' => 'cash',
            'paid_at' => '2026-09-06 09:00:00',
        ])->assertRedirect();

        $this->assertSame('paid', $payroll->fresh()->status);
        $this->assertCount(2, SalaryPayment::query()->where('payroll_id', $payroll->id)->get());
        $this->assertSame('completed', $payroll->fresh()->payment->status);

        $this->assertSame(
            41000.0,
            (float) $this->actingAs($admin)->get(route('payroll.report', ['year' => $year]))->viewData('totalPaid')
        );
    }

    public function test_salary_installments_support_partial_payments_and_block_overpayment(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        $staff = Staff::create([
            'employee_no' => 'EMP-INS-001',
            'first_name' => 'Installment',
            'last_name' => 'Tester',
            'gender' => 'male',
            'phone' => '+880 1700 555666',
            'joining_date' => $session->start_date->toDateString(),
            'employment_type' => 'full_time',
            'status' => 'active',
            'basic_salary' => 30000,
        ]);

        $payroll = Payroll::create([
            'academic_session_id' => $session->id,
            'staff_id' => $staff->id,
            'month' => $session->start_date->format('Y').'-09',
            'basic_salary' => 30000,
            'total_allowances' => 0,
            'total_deductions' => 0,
            'net_salary' => 30000,
            'status' => 'processed',
        ]);

        // First installment: part of the net salary.
        $this->actingAs($admin)->post(route('payroll.pay', $payroll), [
            'amount' => 10000,
            'payment_method' => 'cash',
            'paid_at' => '2026-09-05 10:00:00',
        ])->assertRedirect();

        $payroll->refresh();
        $this->assertSame('partially_paid', $payroll->status);
        $this->assertSame(10000.0, $payroll->paidAmount());
        $this->assertSame(20000.0, $payroll->outstandingAmount());
        $this->assertSame(1, SalaryPayment::query()->where('payroll_id', $payroll->id)->count());

        // An installment larger than the outstanding balance is rejected.
        $this->actingAs($admin)->post(route('payroll.pay', $payroll), [
            'amount' => 25000,
            'payment_method' => 'bank',
            'paid_at' => '2026-09-05 11:00:00',
        ])->assertSessionHasErrors('amount');

        // Zero and negative amounts are rejected by validation.
        $this->actingAs($admin)->post(route('payroll.pay', $payroll), [
            'amount' => 0,
            'payment_method' => 'cash',
            'paid_at' => '2026-09-05 11:00:00',
        ])->assertSessionHasErrors('amount');

        $this->assertSame(1, SalaryPayment::query()->where('payroll_id', $payroll->id)->count());

        // Pay the remaining balance.
        $this->actingAs($admin)->post(route('payroll.pay', $payroll), [
            'amount' => 20000,
            'payment_method' => 'bank',
            'reference_no' => 'TRX-INS-2',
            'paid_at' => '2026-09-06 09:00:00',
        ])->assertRedirect();

        $payroll->refresh();
        $this->assertSame('paid', $payroll->status);
        $this->assertSame(30000.0, $payroll->paidAmount());
        $this->assertSame(0.0, $payroll->outstandingAmount());
        $this->assertSame(2, SalaryPayment::query()->where('payroll_id', $payroll->id)->count());

        // Nothing remains, so further installments are refused.
        $this->actingAs($admin)->post(route('payroll.pay', $payroll), [
            'amount' => 1,
            'payment_method' => 'cash',
            'paid_at' => '2026-09-07 09:00:00',
        ])->assertSessionHasErrors('payroll');

        $this->assertSame(2, SalaryPayment::query()->where('payroll_id', $payroll->id)->count());

        // Reversing one installment recomputes paid/outstanding from the rest.
        $second = SalaryPayment::query()->where('payroll_id', $payroll->id)->orderByDesc('id')->firstOrFail();

        $this->actingAs($admin)->post(route('payroll.payments.reverse', $second), [
            'reason' => 'Installment entered twice by mistake',
        ])->assertRedirect();

        $payroll->refresh();
        $this->assertSame('partially_paid', $payroll->status);
        $this->assertSame(10000.0, $payroll->paidAmount());
        $this->assertSame(20000.0, $payroll->outstandingAmount());
        $this->assertSame('reversed', $second->fresh()->status);

        // Reversing the same installment twice is blocked.
        $this->actingAs($admin)->post(route('payroll.payments.reverse', $second), [
            'reason' => 'Second reversal attempt',
        ])->assertSessionHasErrors('payment');
    }

    public function test_cancelled_payrolls_are_retained_but_excluded_from_totals(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();
        $year = $session->start_date->format('Y');

        $staff = Staff::create([
            'employee_no' => 'EMP-CAN-001',
            'first_name' => 'Cancel',
            'last_name' => 'Tester',
            'gender' => 'male',
            'phone' => '+880 1700 777888',
            'joining_date' => $session->start_date->toDateString(),
            'employment_type' => 'full_time',
            'status' => 'active',
            'basic_salary' => 20000,
        ]);

        $payroll = Payroll::create([
            'academic_session_id' => $session->id,
            'staff_id' => $staff->id,
            'month' => $year.'-09',
            'basic_salary' => 20000,
            'total_allowances' => 0,
            'total_deductions' => 0,
            'net_salary' => 20000,
            'status' => 'processed',
        ]);

        $this->actingAs($admin)->post(route('payroll.cancel', $payroll), [
            'reason' => 'Generated for the wrong month',
        ])->assertRedirect();

        $this->assertSame('cancelled', $payroll->fresh()->status);
        $this->assertDatabaseHas('payrolls', ['id' => $payroll->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payroll_cancelled']);

        // A cancelled payroll cannot be processed or paid.
        $this->actingAs($admin)->post(route('payroll.process', $payroll))->assertSessionHasErrors('payroll');
        $this->actingAs($admin)->post(route('payroll.pay', $payroll), [
            'amount' => 1000,
            'payment_method' => 'cash',
            'paid_at' => '2026-09-05 10:00:00',
        ])->assertSessionHasErrors('payroll');

        $report = $this->actingAs($admin)->get(route('payroll.report', ['year' => $year]));
        $report->assertOk();
        $this->assertSame(0.0, (float) $report->viewData('totalNet'));
        $this->assertSame(0.0, (float) $report->viewData('totalPaid'));

        // Once an installment exists the payroll must be reversed, not cancelled.
        $paidPayroll = Payroll::create([
            'academic_session_id' => $session->id,
            'staff_id' => $staff->id,
            'month' => $year.'-10',
            'basic_salary' => 20000,
            'total_allowances' => 0,
            'total_deductions' => 0,
            'net_salary' => 20000,
            'status' => 'processed',
        ]);

        $this->actingAs($admin)->post(route('payroll.pay', $paidPayroll), [
            'amount' => 5000,
            'payment_method' => 'cash',
            'paid_at' => '2026-10-05 10:00:00',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('payroll.cancel', $paidPayroll), [
            'reason' => 'Trying to cancel after payment',
        ])->assertSessionHasErrors('payroll');

        $this->assertSame('partially_paid', $paidPayroll->fresh()->status);
    }

    public function test_only_unpublished_exams_without_marks_can_be_deleted(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        $class = SchoolClass::query()->where('numeric_level', '>=', 3)->firstOrFail();
        $subject = Subject::query()->firstOrFail();

        $exam = Examination::create([
            'academic_session_id' => $session->id,
            'name' => 'Deletable Draft Exam',
            'exam_type' => 'term',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
            'status' => 'draft',
        ]);

        $exam->subjects()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);

        $this->actingAs($admin)
            ->from(route('exams.show', $exam))
            ->delete(route('exams.destroy', $exam))
            ->assertRedirect(route('exams.index'));

        $this->assertDatabaseMissing('examinations', ['id' => $exam->id]);

        // An examination with marks is preserved.
        [$student] = $this->createStudent($session, 'Exam');

        $examWithMarks = Examination::create([
            'academic_session_id' => $session->id,
            'name' => 'Exam With Marks',
            'exam_type' => 'term',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
            'status' => 'draft',
        ]);

        $examSubject = $examWithMarks->subjects()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);

        Mark::create([
            'examination_subject_id' => $examSubject->id,
            'student_id' => $student->id,
            'marks_obtained' => 80,
        ]);

        $this->actingAs($admin)
            ->from(route('exams.show', $examWithMarks))
            ->delete(route('exams.destroy', $examWithMarks))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('examinations', ['id' => $examWithMarks->id]);

        // A published examination is preserved even with no marks.
        $published = Examination::create([
            'academic_session_id' => $session->id,
            'name' => 'Published Exam',
            'exam_type' => 'term',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
            'status' => 'published',
        ]);

        $this->actingAs($admin)
            ->from(route('exams.show', $published))
            ->delete(route('exams.destroy', $published))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('examinations', ['id' => $published->id]);

        // Direct HTTP requests from unauthorised roles are rejected.
        $teacherRole = Role::query()->where('slug', 'teacher')->firstOrFail();
        $teacher = User::create([
            'role_id' => $teacherRole->id,
            'name' => 'Exam Guard Teacher',
            'email' => 'exam.guard.teacher@example.com',
            'password' => 'Secret@12345',
            'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->delete(route('exams.destroy', $published))
            ->assertForbidden();

        $this->assertDatabaseHas('examinations', ['id' => $published->id]);
    }

    public function test_only_authorised_roles_can_reverse_salary_payments(): void
    {
        $session = $this->seedCore();

        $staff = Staff::create([
            'employee_no' => 'EMP-AUTH-001',
            'first_name' => 'Auth',
            'last_name' => 'Guard',
            'gender' => 'female',
            'phone' => '+880 1700 777888',
            'joining_date' => $session->start_date->toDateString(),
            'employment_type' => 'full_time',
            'status' => 'active',
            'basic_salary' => 25000,
        ]);

        $payroll = Payroll::create([
            'academic_session_id' => $session->id,
            'staff_id' => $staff->id,
            'month' => $session->start_date->format('Y').'-09',
            'basic_salary' => 25000,
            'total_allowances' => 0,
            'total_deductions' => 0,
            'net_salary' => 25000,
            'status' => 'paid',
        ]);

        $payment = SalaryPayment::create([
            'payroll_id' => $payroll->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'status' => 'completed',
        ]);

        $teacherRole = Role::query()->where('slug', 'teacher')->firstOrFail();
        $teacher = User::create([
            'role_id' => $teacherRole->id,
            'name' => 'Payroll Guard Teacher',
            'email' => 'payroll.guard.teacher@example.com',
            'password' => 'Secret@12345',
            'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->post(route('payroll.reverse', $payroll), ['reason' => 'Unauthorised reversal attempt'])
            ->assertForbidden();

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame('paid', $payroll->fresh()->status);
        $this->assertDatabaseCount('salary_payment_reversals', 0);

        // The accountant role holds payroll.manage and may reverse.
        $accountantRole = Role::query()->where('slug', 'accountant')->firstOrFail();
        $accountant = User::create([
            'role_id' => $accountantRole->id,
            'name' => 'Payroll Accountant',
            'email' => 'payroll.accountant@example.com',
            'password' => 'Secret@12345',
            'is_active' => true,
        ]);

        $this->actingAs($accountant)
            ->post(route('payroll.reverse', $payroll), ['reason' => 'Approved correction by accountant'])
            ->assertRedirect();

        $this->assertSame('reversed', $payment->fresh()->status);
        $this->assertSame('processed', $payroll->fresh()->status);
    }

    public function test_application_timezone_honours_the_configured_school_timezone(): void
    {
        $this->seedCore();

        $expected = env('APP_TIMEZONE', 'Asia/Dhaka');

        $this->assertSame($expected, config('app.timezone'));
        $this->assertContains(config('app.timezone'), timezone_identifiers_list());
        $this->assertSame(config('app.timezone'), date_default_timezone_get());
    }

    private function admin(): User
    {
        return User::query()->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
    }

    /**
     * @return array{0: StudentFeeAssignment, 1: FeeStructure}
     */
    private function assignFee(int $studentId, AcademicSession $session, int $classId): array
    {
        $student = Student::query()->findOrFail($studentId);
        $category = FeeCategory::query()->firstOrFail();

        $structure = FeeStructure::create([
            'academic_session_id' => $session->id,
            'class_id' => $classId,
            'name' => 'Test Fee Structure',
            'effective_from' => $session->start_date->toDateString(),
            'effective_to' => $session->end_date->toDateString(),
            'status' => 'active',
        ]);

        $structure->items()->create([
            'fee_category_id' => $category->id,
            'amount' => 1000,
            'discount_amount' => 0,
            'frequency' => 'monthly',
            'due_day' => 10,
            'is_optional' => false,
        ]);

        app(FeeAssignmentService::class)->assignStructure($student, $structure->load('items'), $session);

        $assignment = StudentFeeAssignment::query()
            ->where('student_id', $student->id)
            ->where('fee_structure_id', $structure->id)
            ->orderBy('due_date')
            ->firstOrFail();

        return [$assignment, $structure];
    }
}

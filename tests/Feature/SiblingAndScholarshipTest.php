<?php

namespace Tests\Feature;

use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\User;
use App\Services\FeeAssignmentService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class SiblingAndScholarshipTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    private function admin(): User
    {
        return User::query()->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
    }

    public function test_admission_links_the_selected_sibling_and_keeps_the_guardian(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        [$elder] = $this->createStudent($session, 'Elder');

        $guardian = $elder->guardians()->create([
            'name' => 'Rahim Uddin',
            'relation' => 'Father',
            'phone' => '+880 1711 223344',
            'email' => 'rahim@example.test',
            'occupation' => 'Engineer',
            'address' => 'Dhanmondi 27, Dhaka',
            'is_primary' => true,
        ]);

        $class = SchoolClass::query()->where('numeric_level', '>=', 3)->orderBy('numeric_level')->firstOrFail();
        $section = $class->sections()->orderBy('name')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admissions.store'), [
            'first_name' => 'Younger',
            'last_name' => 'Sibling',
            'name_bn' => 'ছোট শিক্ষার্থী',
            'has_sibling' => '1',
            'sibling_student_id' => $elder->id,
            'gender' => 'male',
            'date_of_birth' => '2016-02-02',
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'admission_date' => $session->start_date->toDateString(),
            'guardian_name' => $guardian->name,
            'guardian_relation' => $guardian->relation,
            'guardian_phone' => $guardian->phone,
            'guardian_email' => $guardian->email,
            'guardian_occupation' => $guardian->occupation,
            'guardian_address' => $guardian->address,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $younger = Student::query()->where('first_name', 'Younger')->firstOrFail();

        $this->assertTrue($younger->has_sibling);
        $this->assertSame($elder->id, $younger->sibling_student_id);
        $this->assertSame('ছোট শিক্ষার্থী', $younger->name_bn);

        // The guardian copied from the sibling is stored for the new student as well.
        $this->assertSame($guardian->phone, $younger->primaryGuardian()?->phone);
        $this->assertSame('Rahim Uddin', $younger->primaryGuardian()?->name);
    }

    public function test_sibling_box_stays_inactive_when_the_answer_is_no(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        [$elder] = $this->createStudent($session, 'NoSiblingElder');
        $class = SchoolClass::query()->where('numeric_level', '>=', 3)->orderBy('numeric_level')->firstOrFail();
        $section = $class->sections()->orderBy('name')->firstOrFail();

        $this->actingAs($admin)->post(route('admissions.store'), [
            'first_name' => 'NoSibling',
            'last_name' => 'Student',
            'has_sibling' => '0',
            'gender' => 'female',
            'date_of_birth' => '2016-05-05',
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'admission_date' => $session->start_date->toDateString(),
            'guardian_name' => 'Ayesha Begum',
            'guardian_relation' => 'Mother',
            'guardian_phone' => '+880 1799 887766',
        ])->assertSessionHasNoErrors();

        $student = Student::query()->where('first_name', 'NoSibling')->firstOrFail();

        $this->assertFalse($student->has_sibling);
        $this->assertNull($student->sibling_student_id);
    }

    public function test_sibling_search_returns_the_guardian_for_auto_fill(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        [$elder] = $this->createStudent($session, 'Searchable');

        $elder->guardians()->create([
            'name' => 'Karim Hasan',
            'relation' => 'Father',
            'phone' => '+880 1733 445566',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($admin)->getJson(route('admissions.sibling-search', ['q' => 'Searchable']));

        $response->assertOk();
        $response->assertJsonPath('0.id', $elder->id);
        $response->assertJsonPath('0.guardian.name', 'Karim Hasan');
        $response->assertJsonPath('0.guardian.phone', '+880 1733 445566');

        // Terms shorter than two characters return nothing.
        $this->actingAs($admin)->getJson(route('admissions.sibling-search', ['q' => 'S']))
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_percentage_scholarship_reduces_the_payable_balance(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        [$student] = $this->createStudent($session, 'PercentScholarship');
        $assignment = $this->createFeeRow($student, $session, 1000);

        $this->actingAs($admin)->post(route('fees.assignments.scholarship', $assignment), [
            'scholarship_type' => 'percent',
            'scholarship_value' => 25,
        ])->assertSessionHasNoErrors();

        $assignment->refresh();

        $this->assertSame('percent', $assignment->scholarship_type);
        $this->assertSame('250.00', $assignment->scholarship_amount);
        $this->assertSame(750.0, $assignment->net_amount);
        $this->assertSame(750.0, $assignment->balance);
    }

    public function test_fixed_scholarship_is_capped_at_the_payable_amount(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        [$student] = $this->createStudent($session, 'FixedScholarship');
        $assignment = $this->createFeeRow($student, $session, 1000);

        $this->actingAs($admin)->post(route('fees.assignments.scholarship', $assignment), [
            'scholarship_type' => 'fixed',
            'scholarship_value' => 400,
        ])->assertSessionHasNoErrors();

        $assignment->refresh();
        $this->assertSame('400.00', $assignment->scholarship_amount);
        $this->assertSame(600.0, $assignment->net_amount);

        // An oversized fixed value is capped instead of making the row negative.
        $this->actingAs($admin)->post(route('fees.assignments.scholarship', $assignment), [
            'scholarship_type' => 'fixed',
            'scholarship_value' => 99999,
        ])->assertSessionHasNoErrors();

        $assignment->refresh();
        $this->assertSame('1000.00', $assignment->scholarship_amount);
        $this->assertSame(0.0, $assignment->net_amount);
        $this->assertSame('waived', $assignment->status);
    }

    public function test_a_percentage_scholarship_above_one_hundred_is_rejected(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        [$student] = $this->createStudent($session, 'BadScholarship');
        $assignment = $this->createFeeRow($student, $session, 1000);

        $this->actingAs($admin)->post(route('fees.assignments.scholarship', $assignment), [
            'scholarship_type' => 'percent',
            'scholarship_value' => 140,
        ])->assertSessionHasErrors('scholarship_value');

        $this->assertSame('0.00', $assignment->fresh()->scholarship_amount);
    }

    public function test_scholarship_can_be_removed_again(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        [$student] = $this->createStudent($session, 'RemoveScholarship');
        $assignment = $this->createFeeRow($student, $session, 1000);

        $this->actingAs($admin)->post(route('fees.assignments.scholarship', $assignment), [
            'scholarship_type' => 'percent',
            'scholarship_value' => 30,
        ]);

        $this->actingAs($admin)->post(route('fees.assignments.scholarship', $assignment), [
            'scholarship_type' => 'none',
            'scholarship_value' => 0,
        ])->assertSessionHasNoErrors();

        $assignment->refresh();

        $this->assertNull($assignment->scholarship_type);
        $this->assertSame('0.00', $assignment->scholarship_amount);
        $this->assertSame(1000.0, $assignment->net_amount);
    }

    public function test_structure_scholarship_template_is_applied_when_fees_are_assigned(): void
    {
        $session = $this->seedCore();

        [$student] = $this->createStudent($session, 'TemplateScholarship');
        $class = SchoolClass::query()->where('numeric_level', '>=', 3)->firstOrFail();
        $category = FeeCategory::query()->firstOrFail();

        $structure = FeeStructure::create([
            'academic_session_id' => $session->id,
            'class_id' => $class->id,
            'name' => 'Scholarship Structure',
            'effective_from' => $session->start_date->toDateString(),
            'effective_to' => $session->end_date->toDateString(),
            'status' => 'active',
        ]);

        $structure->items()->create([
            'fee_category_id' => $category->id,
            'amount' => 1200,
            'discount_amount' => 200,
            'scholarship_type' => 'percent',
            'scholarship_value' => 50,
            'frequency' => 'one_time',
            'due_day' => 5,
            'is_optional' => false,
        ]);

        app(FeeAssignmentService::class)->assignStructure($student, $structure->load('items'), $session);

        $assignment = StudentFeeAssignment::query()
            ->where('student_id', $student->id)
            ->where('fee_structure_id', $structure->id)
            ->firstOrFail();

        // gross 1200 − discount 200 = 1000 payable, then 50% scholarship = 500 net.
        $this->assertSame('500.00', $assignment->scholarship_amount);
        $this->assertSame('percent', $assignment->scholarship_type);
        $this->assertSame(500.0, $assignment->net_amount);
    }

    public function test_receipt_records_the_scholarship_settled_by_a_payment(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        [$student] = $this->createStudent($session, 'ReceiptScholarship');
        $assignment = $this->createFeeRow($student, $session, 1000);

        app(FeeAssignmentService::class)->applyScholarship($assignment, 'percent', 20, $admin);
        $assignment->refresh();

        $payment = app(PaymentService::class)->collect(
            $student,
            $session,
            [$assignment->id => (float) $assignment->balance],
            ['payment_method' => 'cash', 'paid_at' => now()],
            $admin
        );

        $this->assertSame(800.0, (float) $payment->amount);
        $this->assertSame(200.0, (float) $payment->discount_amount);
        $this->assertSame('paid', $assignment->fresh()->status);

        $this->actingAs($admin)->get(route('payments.receipt', $payment))
            ->assertOk()
            ->assertSee('Total Scholarship / Discount');
    }

    public function test_waiver_and_scholarship_stack_without_double_counting(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        [$student] = $this->createStudent($session, 'StackedConcession');
        $assignment = $this->createFeeRow($student, $session, 1000);

        app(FeeAssignmentService::class)->applyScholarship($assignment, 'fixed', 300, $admin);
        $assignment->refresh();
        $this->assertSame(700.0, $assignment->net_amount);

        app(FeeAssignmentService::class)->applyWaiver($assignment->fresh(), 100, 'Hardship', $admin);
        $assignment->refresh();

        $this->assertSame('300.00', $assignment->scholarship_amount);
        $this->assertSame('100.00', $assignment->waiver_amount);
        $this->assertSame(600.0, $assignment->net_amount);
        $this->assertSame(600.0, $assignment->balance);
    }

    public function test_collected_revenue_is_not_reduced_twice_by_a_scholarship(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();

        [$student] = $this->createStudent($session, 'NoDoubleDiscount');
        $assignment = $this->createFeeRow($student, $session, 1000);

        app(FeeAssignmentService::class)->applyScholarship($assignment, 'percent', 100, $admin);
        $assignment->refresh();

        // A 100% scholarship clears the row without any cash changing hands.
        $this->assertSame(0.0, $assignment->net_amount);
        $this->assertSame('waived', $assignment->status);
        $this->assertSame(0, Payment::query()->where('student_id', $student->id)->count());
    }

    private function createFeeRow(Student $student, $session, float $amount): StudentFeeAssignment
    {
        return StudentFeeAssignment::create([
            'student_id' => $student->id,
            'fee_category_id' => FeeCategory::query()->firstOrFail()->id,
            'academic_session_id' => $session->id,
            'period' => null,
            'amount' => $amount,
            'discount_amount' => 0,
            'waiver_amount' => 0,
            'scholarship_amount' => 0,
            'net_amount' => $amount,
            'due_date' => $session->start_date->copy()->addDays(10)->toDateString(),
            'status' => 'pending',
            'note' => 'test fee row',
        ]);
    }
}

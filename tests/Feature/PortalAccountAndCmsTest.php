<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\WebsiteContentController;
use App\Models\ParentStudentLink;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentPortalLink;
use App\Models\User;
use App\Models\WebsiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class PortalAccountAndCmsTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    public function test_admission_creates_student_and_sibling_parent_accounts_with_identifier_logins(): void
    {
        $session = $this->seedCore();
        $admin = $this->admin();
        $class = SchoolClass::query()->where('name', 'Class 1')->firstOrFail();
        $section = $class->sections()->orderBy('name')->firstOrFail();

        $this->actingAs($admin)->post(route('admissions.store'), $this->admissionPayload($session->id, $class->id, $section->id, [
            'first_name' => 'Amina',
            'last_name' => 'Account',
            'email' => 'amina.account@example.test',
            'guardian_name' => 'Rahim Account',
            'guardian_phone' => '+880 1712 345679',
            'guardian_email' => 'rahim.account@example.test',
        ]))->assertRedirect();

        $student = Student::query()->where('email', 'amina.account@example.test')->firstOrFail();
        $portalUser = StudentPortalLink::query()->where('student_id', $student->id)->firstOrFail()->user;

        $this->assertMatchesRegularExpression('/^202601\d{3}$/', (string) $student->student_code);
        $this->assertSame($student->student_code, $portalUser->username);
        $this->assertSame('student', $portalUser->role->slug);
        $this->assertTrue(Hash::check(portal_default_password(), $portalUser->password));

        $parent = User::query()
            ->whereHas('role', fn ($query) => $query->where('slug', 'parent'))
            ->where('phone_normalized', '01712345679')
            ->firstOrFail();

        $this->assertSame('01712345679', $parent->username);
        $this->assertTrue(Hash::check(portal_default_password(), $parent->password));
        $this->assertDatabaseHas('parent_student_links', [
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'is_active' => true,
        ]);

        // A sibling with the same guardian mobile shares the parent account.
        $siblingResponse = $this->actingAs($admin)->post(route('admissions.store'), $this->admissionPayload($session->id, $class->id, $section->id, [
            'first_name' => 'Bashir',
            'last_name' => 'Account',
            'email' => 'bashir.account@example.test',
            'guardian_name' => 'Rahim Account',
            'guardian_phone' => '01712345679',
            'guardian_email' => 'rahim.account@example.test',
        ]))->assertRedirect();

        $siblingResponse->assertSessionHas('success', fn (string $message) => str_contains($message, 'password was not changed'));
        $this->assertFalse((bool) session('account_details')['parent_created']);

        $sibling = Student::query()->where('email', 'bashir.account@example.test')->firstOrFail();

        $this->assertNotSame($student->student_code, $sibling->student_code);
        $this->assertSame(1, User::query()
            ->whereHas('role', fn ($query) => $query->where('slug', 'parent'))
            ->where('phone_normalized', '01712345679')
            ->count());
        $this->assertSame(2, ParentStudentLink::query()->where('user_id', $parent->id)->count());

        // Student portal accepts the permanent student ID, not just an email.
        $this->actingAs($admin)->post(route('logout'))->assertRedirect();
        $this->assertGuest();

        $this->post(route('student.login.store'), [
            'email' => $student->student_code,
            'password' => portal_default_password(),
        ])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($portalUser);

        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();

        // Parent portal accepts the normalised mobile number.
        $this->post(route('parent.login.store'), [
            'email' => '+880 1712-345679',
            'password' => portal_default_password(),
        ])->assertRedirect(route('parent.dashboard'));
        $this->assertAuthenticatedAs($parent);
    }

    public function test_staff_creation_provisions_an_account_that_can_login_by_username_or_email(): void
    {
        $this->seedCore();
        $admin = $this->admin();
        $teacherRole = Role::query()->where('slug', 'teacher')->firstOrFail();

        $this->actingAs($admin)->post(route('staff.store'), [
            'first_name' => 'Jahid',
            'last_name' => 'Hasan',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'phone' => '+880 1812 345678',
            'email' => 'jahid.hasan@example.test',
            'role_id' => $teacherRole->id,
            'username' => 'jahid.hasan',
            'address' => 'Dhanmondi, Dhaka',
            'joining_date' => '2026-01-05',
            'employment_type' => 'full_time',
            'status' => 'active',
            'basic_salary' => 32000,
        ])->assertRedirect();

        $staff = Staff::query()->where('email', 'jahid.hasan@example.test')->firstOrFail();
        $account = $staff->user;

        $this->assertNotNull($account);
        $this->assertSame('jahid.hasan', $account->username);
        $this->assertSame('teacher', $account->role->slug);
        $this->assertTrue(Hash::check(portal_default_password(), $account->password));

        // Manually created users also receive a generated username and the
        // configured default password when the password field is blank.
        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Auto Account',
            'email' => 'auto.account@example.test',
            'phone' => '01812345678',
            'role_id' => $teacherRole->id,
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $autoUser = User::query()->where('email', 'auto.account@example.test')->firstOrFail();
        $this->assertNotEmpty($autoUser->username);
        $this->assertSame('01812345678', $autoUser->phone_normalized);
        $this->assertTrue(Hash::check(portal_default_password(), $autoUser->password));

        $this->actingAs($admin)->post(route('logout'))->assertRedirect();
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => 'jahid.hasan',
            'password' => portal_default_password(),
        ])->assertRedirect(route('teacher.dashboard'));
        $this->assertAuthenticatedAs($account);

        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => 'jahid.hasan@example.test',
            'password' => portal_default_password(),
        ])->assertRedirect(route('teacher.dashboard'));
        $this->assertAuthenticatedAs($account);
    }

    public function test_website_sections_design_and_homepage_are_managed_from_administration(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('website-admin.index'))
            ->assertOk()
            ->assertSee('Website Content Management');

        foreach (array_keys(WebsiteContentController::SECTIONS) as $section) {
            $this->actingAs($admin)->get(route('website-admin.'.$section))->assertOk();
        }

        $this->actingAs($admin)->put(route('website-admin.design.update'), [
            'website_name_bn' => 'পরীক্ষা বিদ্যালয়',
            'website_name_en' => 'TEST ACADEMY',
            'website_tagline' => 'পরীক্ষার ট্যাগলাইন',
            'website_logo_mark' => 'TA',
            'website_topbar_text' => 'পরীক্ষার টপবার',
            'website_header_cta_label' => 'ভর্তি হোন',
            'website_header_cta_url' => '/admission/apply',
            'website_footer_about' => 'পরীক্ষার ফুটার পরিচিতি',
            'website_footer_copyright' => '© :year পরীক্ষা বিদ্যালয়',
            'website_footer_note' => 'পরীক্ষার নোট',
            'website_facebook' => 'https://facebook.com/example',
            'website_x' => '',
            'website_youtube' => '',
            'website_header_style' => 'split',
            'website_footer_style' => 'accent',
            'website_theme' => 'maroon',
            'website_show_topbar' => '1',
        ])->assertRedirect();

        $this->assertSame('split', SchoolSetting::get('website_header_style'));
        $this->assertSame('accent', SchoolSetting::get('website_footer_style'));
        $this->assertSame('maroon', SchoolSetting::get('website_theme'));

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('header-style-split', false)
            ->assertSee('footer-style-accent', false)
            ->assertSee('theme-maroon', false)
            ->assertSee('পরীক্ষার ফুটার পরিচিতি')
            ->assertSee('TEST ACADEMY')
            ->assertSee('© '.now()->format('Y').' পরীক্ষা বিদ্যালয়', false);

        $this->actingAs($admin)->post(route('website-content.store'), [
            'type' => 'slider',
            'title' => 'Admission Open 2026',
            'excerpt' => 'Apply for the new academic session.',
            'body' => 'Slide body',
            'body_format' => 'text',
            'link_url' => '/admission/apply',
            'button_label' => 'Apply now',
            'status' => 'published',
            'sort_order' => 1,
        ])->assertRedirect(route('website-content.index'));

        $slider = WebsiteContent::query()->ofType('slider')->firstOrFail();
        $this->assertSame('/admission/apply', $slider->link_url);
        $this->assertSame('Apply now', $slider->button_label);

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('Admission Open 2026')
            ->assertSee('Apply now');

        // Single-block sections cannot be duplicated through a direct POST.
        $this->actingAs($admin)->post(route('website-content.store'), [
            'type' => 'principal_message',
            'title' => 'Principal Message',
            'body' => 'Message body',
            'body_format' => 'text',
            'status' => 'published',
        ])->assertRedirect();

        $principal = WebsiteContent::query()->ofType('principal_message')->firstOrFail();

        $this->actingAs($admin)->post(route('website-content.store'), [
            'type' => 'principal_message',
            'title' => 'Duplicate Message',
            'body' => 'Another body',
            'body_format' => 'text',
            'status' => 'published',
        ])->assertRedirect(route('website-content.edit', $principal));

        $this->assertSame(1, WebsiteContent::query()->ofType('principal_message')->count());

        $this->get(route('website.principal-message'))
            ->assertOk()
            ->assertSee('Principal Message');
    }

    public function test_portal_users_can_change_their_default_password_from_the_portal(): void
    {
        $this->seedCore();
        $studentRole = Role::query()->where('slug', 'student')->firstOrFail();

        $user = User::create([
            'role_id' => $studentRole->id,
            'name' => 'Password Student',
            'username' => '202699001',
            'password' => portal_default_password(),
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('password.change'))
            ->assertOk()
            ->assertSee('Change password')
            ->assertSee('Student Portal');

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => portal_default_password(),
            'password' => 'NewSecret@123',
            'password_confirmation' => 'NewSecret@123',
        ])->assertRedirect(route('password.change'));

        $this->assertTrue(Hash::check('NewSecret@123', $user->fresh()->password));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function admissionPayload(int $sessionId, int $classId, int $sectionId, array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Portal',
            'last_name' => 'Student',
            'gender' => 'female',
            'date_of_birth' => '2015-04-12',
            'phone' => '+880 1700 000000',
            'address' => 'Dhanmondi, Dhaka',
            'city' => 'Dhaka',
            'admission_date' => '2026-01-05',
            'academic_session_id' => $sessionId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'guardian_name' => 'Portal Guardian',
            'guardian_relation' => 'Father',
            'guardian_phone' => '+880 1700 000001',
        ], $overrides);
    }

    private function admin(): User
    {
        return User::query()->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
    }
}

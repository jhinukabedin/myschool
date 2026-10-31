<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\Department;
use App\Models\Designation;
use App\Models\ExpenseCategory;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\GradeRule;
use App\Models\GradingScheme;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Models\WebsiteMenu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CoreDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdministrator();
        $this->seedSessions();
        $this->call(RoutineDefaultSeeder::class);
        $this->seedAcademicStructure();
        $this->seedFees();
        $this->seedGradeRules();
        $this->seedGradingSchemes();
        $this->seedWebsiteMenus();
        $this->seedStaffLookups();
        $this->seedExpenseCategories();
    }

    private function seedAdministrator(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@edusphere.test');
        $password = env('ADMIN_PASSWORD');

        if (! $password) {
            if (app()->environment('production')) {
                throw new RuntimeException(
                    'Set ADMIN_EMAIL and ADMIN_PASSWORD environment variables before seeding production.'
                );
            }

            $password = 'Admin@12345';
        }

        $role = Role::where('slug', 'super-admin')->firstOrFail();

        User::updateOrCreate(
            ['email' => $email],
            [
                'role_id' => $role->id,
                'name' => 'System Administrator',
                'phone' => '+880 1700 000001',
                'password' => Hash::make($password),
                'is_active' => true,
            ]
        );

        if (app()->environment('local') && ! env('ADMIN_PASSWORD')) {
            $this->command?->warn("Local administrator: {$email} / {$password} — change this password after first login.");
        }
    }

    private function seedSessions(): void
    {
        $sessions = [
            ['name' => '2024', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'is_current' => false, 'status' => 'closed'],
            ['name' => '2025', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'is_current' => false, 'status' => 'closed'],
            ['name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true, 'status' => 'active'],
        ];

        foreach ($sessions as $session) {
            AcademicSession::updateOrCreate(['name' => $session['name']], $session);
        }

        SchoolSetting::put('current_session_id', (string) AcademicSession::current()->value('id'), 'academic');
    }

    private function seedAcademicStructure(): void
    {
        $classes = [
            ['name' => 'Play Group', 'numeric_level' => 0],
            ['name' => 'Nursery', 'numeric_level' => 1],
            ['name' => 'KG', 'numeric_level' => 2],
            ['name' => 'Class 1', 'numeric_level' => 3],
            ['name' => 'Class 2', 'numeric_level' => 4],
            ['name' => 'Class 3', 'numeric_level' => 5],
            ['name' => 'Class 4', 'numeric_level' => 6],
            ['name' => 'Class 5', 'numeric_level' => 7],
            ['name' => 'Class 6', 'numeric_level' => 8],
            ['name' => 'Class 7', 'numeric_level' => 9],
            ['name' => 'Class 8', 'numeric_level' => 10],
            ['name' => 'Class 9', 'numeric_level' => 11],
            ['name' => 'Class 10', 'numeric_level' => 12],
        ];

        foreach ($classes as $class) {
            SchoolClass::updateOrCreate(['name' => $class['name']], $class);
        }

        foreach (['A', 'B'] as $name) {
            Section::updateOrCreate(['name' => $name]);
        }

        $sections = Section::all();
        foreach (SchoolClass::all() as $class) {
            $class->sections()->syncWithoutDetaching($sections->pluck('id')->all());
        }

        $subjects = [
            ['code' => 'BAN', 'name' => 'Bangla', 'type' => 'theory'],
            ['code' => 'ENG', 'name' => 'English', 'type' => 'theory'],
            ['code' => 'MAT', 'name' => 'Mathematics', 'type' => 'theory'],
            ['code' => 'SCI', 'name' => 'Science', 'type' => 'both'],
            ['code' => 'SST', 'name' => 'Social Science', 'type' => 'theory'],
            ['code' => 'REL', 'name' => 'Religion & Moral Education', 'type' => 'theory'],
            ['code' => 'ICT', 'name' => 'Information & Communication Technology', 'type' => 'practical'],
            ['code' => 'PE', 'name' => 'Physical Education', 'type' => 'practical'],
        ];

        foreach ($subjects as $subject) {
            Subject::updateOrCreate(['code' => $subject['code']], $subject + [
                'full_marks' => 100,
                'pass_marks' => 33,
            ]);
        }

        $session = AcademicSession::where('name', '2026')->first();
        $subjectIds = Subject::pluck('id');

        foreach (SchoolClass::where('numeric_level', '>=', 3)->get() as $class) {
            foreach ($subjectIds as $subjectId) {
                ClassSubject::updateOrCreate([
                    'academic_session_id' => $session->id,
                    'class_id' => $class->id,
                    'subject_id' => $subjectId,
                ]);
            }
        }
    }

    private function seedFees(): void
    {
        $categories = [
            ['name' => 'Tuition Fee', 'type' => 'monthly', 'is_optional' => false],
            ['name' => 'Admission Fee', 'type' => 'admission', 'is_optional' => false],
            ['name' => 'Examination Fee', 'type' => 'examination', 'is_optional' => false],
            ['name' => 'Library Fee', 'type' => 'annual', 'is_optional' => false],
            ['name' => 'Development Fee', 'type' => 'annual', 'is_optional' => false],
            ['name' => 'Transport Fee', 'type' => 'transport', 'is_optional' => true],
        ];

        foreach ($categories as $category) {
            FeeCategory::updateOrCreate(['name' => $category['name']], $category);
        }

        $session = AcademicSession::where('name', '2026')->first();
        $categoriesByName = FeeCategory::all()->keyBy('name');

        foreach (SchoolClass::all() as $index => $class) {
            $tuition = 1200 + ($class->numeric_level * 150);
            $structure = FeeStructure::updateOrCreate(
                ['academic_session_id' => $session->id, 'class_id' => $class->id, 'name' => $class->name.' — Standard Fees'],
                [
                    'description' => 'Standard monthly and annual fees for '.$class->name,
                    'effective_from' => '2026-01-01',
                    'effective_to' => '2026-12-31',
                    'status' => 'active',
                ]
            );

            $items = [
                ['name' => 'Tuition Fee', 'amount' => $tuition, 'frequency' => 'monthly', 'due_day' => 10],
                ['name' => 'Admission Fee', 'amount' => 5000, 'frequency' => 'one_time', 'due_day' => 5],
                ['name' => 'Examination Fee', 'amount' => 1000, 'frequency' => 'exam', 'due_day' => 5],
                ['name' => 'Library Fee', 'amount' => 500, 'frequency' => 'annual', 'due_day' => 15],
                ['name' => 'Development Fee', 'amount' => 2000, 'frequency' => 'annual', 'due_day' => 20],
                ['name' => 'Transport Fee', 'amount' => 800, 'frequency' => 'monthly', 'due_day' => 10, 'optional' => true],
            ];

            foreach ($items as $item) {
                $structure->items()->updateOrCreate(
                    ['fee_category_id' => $categoriesByName[$item['name']]->id],
                    [
                        'amount' => $item['amount'],
                        'discount_amount' => 0,
                        'frequency' => $item['frequency'],
                        'due_day' => $item['due_day'],
                        'is_optional' => (bool) ($item['optional'] ?? false),
                    ]
                );
            }
        }
    }

    private function seedGradeRules(): void
    {
        $rules = [
            ['name' => 'A+', 'min_percent' => 80, 'max_percent' => 100, 'grade' => 'A+', 'grade_point' => 5.00, 'is_fail' => false],
            ['name' => 'A', 'min_percent' => 70, 'max_percent' => 79.99, 'grade' => 'A', 'grade_point' => 4.00, 'is_fail' => false],
            ['name' => 'A-', 'min_percent' => 60, 'max_percent' => 69.99, 'grade' => 'A-', 'grade_point' => 3.50, 'is_fail' => false],
            ['name' => 'B', 'min_percent' => 50, 'max_percent' => 59.99, 'grade' => 'B', 'grade_point' => 3.00, 'is_fail' => false],
            ['name' => 'C', 'min_percent' => 40, 'max_percent' => 49.99, 'grade' => 'C', 'grade_point' => 2.00, 'is_fail' => false],
            ['name' => 'D', 'min_percent' => 33, 'max_percent' => 39.99, 'grade' => 'D', 'grade_point' => 1.00, 'is_fail' => false],
            ['name' => 'F', 'min_percent' => 0, 'max_percent' => 32.99, 'grade' => 'F', 'grade_point' => 0.00, 'is_fail' => true],
        ];

        foreach ($rules as $rule) {
            GradeRule::updateOrCreate(['min_percent' => $rule['min_percent'], 'max_percent' => $rule['max_percent']], $rule);
        }
    }

    private function seedGradingSchemes(): void
    {
        $session = AcademicSession::where('name', '2026')->first();
        $globalRules = GradeRule::query()->whereNull('grading_scheme_id')->ordered()->get();

        if ($globalRules->isEmpty()) {
            return;
        }

        $schemes = [
            [
                'name' => 'Institution Standard 2026',
                'exam_type' => null,
                'academic_session_id' => $session?->id,
                'is_default' => true,
                'description' => 'Default school grading used for term and annual examinations.',
            ],
            [
                'name' => 'SSC Standard 2026',
                'exam_type' => 'ssc',
                'academic_session_id' => $session?->id,
                'is_default' => false,
                'description' => 'SSC / equivalent grade boundaries. Assign explicitly to SSC examinations.',
            ],
            [
                'name' => 'HSC Standard 2026',
                'exam_type' => 'hsc',
                'academic_session_id' => $session?->id,
                'is_default' => false,
                'description' => 'HSC / equivalent grade boundaries. Assign explicitly to HSC examinations.',
            ],
        ];

        foreach ($schemes as $data) {
            $scheme = GradingScheme::updateOrCreate(
                ['name' => $data['name'], 'academic_session_id' => $data['academic_session_id']],
                $data + ['is_active' => true]
            );

            if (! $scheme->rules()->exists()) {
                foreach ($globalRules as $rule) {
                    $scheme->rules()->create($rule->only([
                        'name', 'min_percent', 'max_percent', 'grade', 'grade_point', 'is_fail',
                    ]));
                }
            }
        }
    }

    private function seedWebsiteMenus(): void
    {
        $header = WebsiteMenu::updateOrCreate(
            ['name' => 'Main Navigation', 'location' => 'header'],
            ['is_active' => true]
        );

        $headerItems = [
            ['title' => 'প্রচ্ছদ', 'route_name' => 'website.home'],
            ['title' => 'বিদ্যালয় পরিচিতি', 'route_name' => 'website.about'],
            ['title' => 'একাডেমিক', 'route_name' => 'website.academics'],
            ['title' => 'নোটিশ', 'route_name' => 'website.notices'],
            ['title' => 'ফলাফল', 'url' => '/student/login'],
            ['title' => 'গ্যালারি', 'route_name' => 'website.gallery'],
            ['title' => 'ভর্তি', 'route_name' => 'website.admission'],
            ['title' => 'যোগাযোগ', 'route_name' => 'website.contact'],
        ];

        foreach ($headerItems as $index => $item) {
            $header->items()->updateOrCreate(
                ['title' => $item['title'], 'parent_id' => null],
                $item + ['sort_order' => $index + 1, 'target' => '_self', 'is_active' => true]
            );
        }

        $footer = WebsiteMenu::updateOrCreate(
            ['name' => 'Footer Navigation', 'location' => 'footer'],
            ['is_active' => true]
        );

        $footerItems = [
            ['title' => 'বিদ্যালয় পরিচিতি', 'route_name' => 'website.about'],
            ['title' => 'নোটিশ বোর্ড', 'route_name' => 'website.notices'],
            ['title' => 'একাডেমিক ক্যালেন্ডার', 'route_name' => 'website.calendar'],
            ['title' => 'ডাউনলোড', 'route_name' => 'website.downloads'],
            ['title' => 'গোপনীয়তা নীতি', 'route_name' => 'website.privacy'],
        ];

        foreach ($footerItems as $index => $item) {
            $footer->items()->updateOrCreate(
                ['title' => $item['title'], 'parent_id' => null],
                $item + ['sort_order' => $index + 1, 'target' => '_self', 'is_active' => true]
            );
        }
    }

    private function seedStaffLookups(): void
    {
        foreach (['Academic', 'Accounts', 'Administration', 'Science', 'Library'] as $department) {
            Department::updateOrCreate(['name' => $department]);
        }

        foreach (['Principal', 'Vice Principal', 'Senior Teacher', 'Teacher', 'Accountant', 'Librarian', 'Office Assistant'] as $designation) {
            Designation::updateOrCreate(['name' => $designation]);
        }
    }

    private function seedExpenseCategories(): void
    {
        foreach (['Teacher Salary', 'Utility Bills', 'Maintenance', 'Stationery', 'Transport', 'Events', 'IT & Software', 'Miscellaneous'] as $category) {
            ExpenseCategory::updateOrCreate(['name' => $category]);
        }
    }
}

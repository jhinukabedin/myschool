<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\AdmissionEnquiry;
use App\Models\ClassSubject;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Enrollment;
use App\Models\Examination;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeStructure;
use App\Models\GradingScheme;
use App\Models\Mark;
use App\Models\Notice;
use App\Models\ParentStudentLink;
use App\Models\Payroll;
use App\Models\Role;
use App\Models\SalaryStructure;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\StudentPortalLink;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\DocumentNumberService;
use App\Services\ExamResultService;
use App\Services\FeeAssignmentService;
use App\Services\PaymentService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    private User $admin;

    private AcademicSession $session;

    public function run(): void
    {
        if (Student::withTrashed()->count() > 0) {
            $this->command?->warn('Demo data already exists; skipping DemoDataSeeder.');

            return;
        }

        $this->admin = User::where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
        $this->session = AcademicSession::where('name', '2026')->firstOrFail();

        $this->seedUsersAndStaff();
        $students = $this->seedActiveStudents();
        $this->seedPortalAccounts($students);
        $this->seedFeesAndPayments($students);
        $this->seedAttendance();
        $this->seedExaminations();
        $this->seedExpenses();
        $this->seedPayroll();
        $this->seedNotices();
        $this->seedEnquiries();
        $this->seedArchivedStudents();
    }

    private function seedUsersAndStaff(): void
    {
        $roles = Role::pluck('id', 'slug');
        $users = [
            ['name' => 'Nadia Rahman', 'email' => 'teacher@edusphere.test', 'role' => 'teacher'],
            ['name' => 'Farhan Kabir', 'email' => 'accountant@edusphere.test', 'role' => 'accountant'],
            ['name' => 'Shamima Akter', 'email' => 'registrar@edusphere.test', 'role' => 'registrar'],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'role_id' => $roles[$data['role']],
                    'password' => Hash::make(env('DEMO_PASSWORD', 'Admin@12345')),
                    'is_active' => true,
                ]
            );
        }

        $departments = Department::pluck('id', 'name');
        $designations = Designation::pluck('id', 'name');
        $numbers = app(DocumentNumberService::class);

        $staff = [
            ['first_name' => 'Nadia', 'last_name' => 'Rahman', 'department' => 'Science', 'designation' => 'Senior Teacher', 'basic' => 62000, 'user' => 'teacher@edusphere.test'],
            ['first_name' => 'Farhan', 'last_name' => 'Kabir', 'department' => 'Accounts', 'designation' => 'Accountant', 'basic' => 48000, 'user' => 'accountant@edusphere.test'],
            ['first_name' => 'Shamima', 'last_name' => 'Akter', 'department' => 'Administration', 'designation' => 'Vice Principal', 'basic' => 75000, 'user' => 'registrar@edusphere.test'],
            ['first_name' => 'Imtiaz', 'last_name' => 'Hossain', 'department' => 'Academic', 'designation' => 'Principal', 'basic' => 95000, 'user' => null],
            ['first_name' => 'Rokeya', 'last_name' => 'Sultana', 'department' => 'Academic', 'designation' => 'Teacher', 'basic' => 42000, 'user' => null],
            ['first_name' => 'Jahid', 'last_name' => 'Hasan', 'department' => 'Library', 'designation' => 'Librarian', 'basic' => 36000, 'user' => null],
        ];

        foreach ($staff as $index => $data) {
            $user = $data['user'] ? User::where('email', $data['user'])->first() : null;

            $record = Staff::create([
                'employee_no' => $numbers->employeeNumber(2026),
                'user_id' => $user?->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => in_array($data['first_name'], ['Nadia', 'Shamima', 'Rokeya']) ? 'female' : 'male',
                'date_of_birth' => now()->subYears(30 + $index)->subDays($index * 30)->toDateString(),
                'phone' => '+880 1711 00'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'email' => $user?->email ?? strtolower($data['first_name'].'.'.$data['last_name']).'@edusphere.test',
                'address' => 'Dhanmondi, Dhaka',
                'department_id' => $departments[$data['department']] ?? null,
                'designation_id' => $designations[$data['designation']] ?? null,
                'joining_date' => '2022-01-'.str_pad((string) (($index + 1) * 3), 2, '0', STR_PAD_LEFT),
                'employment_type' => 'full_time',
                'status' => 'active',
                'basic_salary' => $data['basic'],
            ]);

            SalaryStructure::create([
                'staff_id' => $record->id,
                'basic_salary' => $data['basic'],
                'house_allowance' => round($data['basic'] * 0.30, 2),
                'transport_allowance' => round($data['basic'] * 0.10, 2),
                'medical_allowance' => round($data['basic'] * 0.05, 2),
                'other_allowance' => 1500,
                'tax_deduction' => round($data['basic'] * 0.05, 2),
                'provident_fund' => round($data['basic'] * 0.07, 2),
                'other_deduction' => 0,
                'effective_from' => '2026-01-01',
            ]);
        }
    }

    private function seedActiveStudents(): array
    {
        $firstNames = [
            'Aarav', 'Ayesha', 'Rehan', 'Mira', 'Sami', 'Nusrat', 'Tanvir', 'Farhana',
            'Rafid', 'Zara', 'Imran', 'Sadia', 'Arif', 'Maliha', 'Fahim', 'Raisa',
            'Nabil', 'Tasnim', 'Shakib', 'Munia', 'Adnan', 'Ritika', 'Sohan', 'Jannat',
            'Mahin', 'Puja', 'Riyad', 'Sumaiya', 'Ayaan', 'Anika', 'Tahmid', 'Mehjabin',
            'Sabbir', 'Nabila', 'Rasel', 'Tania', 'Foysal', 'Lamia', 'Hasan', 'Naima',
            'Sajid', 'Ishrat', 'Rony', 'Tisha', 'Zayed', 'Sneha',
        ];
        $lastNames = [
            'Hossain', 'Ahmed', 'Rahman', 'Islam', 'Chowdhury', 'Akter', 'Khan',
            'Sarker', 'Karim', 'Jahan', 'Haque', 'Alam', 'Mia', 'Sultana',
        ];
        $guardianRelations = ['Father', 'Mother', 'Guardian'];
        $students = [];
        $counter = 0;
        $numbers = app(DocumentNumberService::class);
        $sections = Section::orderBy('name')->get();

        foreach (SchoolClass::ordered()->get() as $class) {
            $perSection = $class->numeric_level >= 3 ? 2 : 1;
            $classSections = $class->numeric_level >= 3 ? $sections : $sections->take(1);
            $roll = [];

            foreach ($classSections as $section) {
                for ($i = 0; $i < $perSection; $i++) {
                    $first = $firstNames[$counter % count($firstNames)];
                    $last = $lastNames[$counter % count($lastNames)];
                    $admissionDate = Carbon::create(2026, 1, 1)->addDays(($counter * 11) % 240);
                    $currencyPhone = '+880 18'.str_pad((string) (10000000 + $counter), 8, '0', STR_PAD_LEFT);

                    $student = Student::create([
                        'admission_no' => $numbers->admissionNumber(2026),
                        'first_name' => $first,
                        'last_name' => $last,
                        'gender' => $counter % 2 === 0 ? 'male' : 'female',
                        'date_of_birth' => now()->subYears(5 + $class->numeric_level)->subDays($counter)->toDateString(),
                        'blood_group' => ['A+', 'B+', 'O+', 'AB+', null][$counter % 5],
                        'religion' => 'Islam',
                        'nationality' => 'Bangladeshi',
                        'phone' => $currencyPhone,
                        'email' => null,
                        'address' => 'House '.($counter + 1).', Road '.($counter % 12 + 1).', Dhanmondi',
                        'city' => 'Dhaka',
                        'state' => 'Dhaka',
                        'postal_code' => '1205',
                        'admission_date' => $admissionDate->toDateString(),
                        'previous_school' => $counter % 3 === 0 ? 'Sunrise Kindergarten' : null,
                        'medical_notes' => null,
                        'status' => 'active',
                    ]);

                    $student->guardians()->create([
                        'name' => $first.' '.$last,
                        'relation' => $guardianRelations[$counter % 3],
                        'phone' => $currencyPhone,
                        'email' => null,
                        'occupation' => ['Business', 'Service', 'Teacher', 'Engineer'][$counter % 4],
                        'address' => 'Dhanmondi, Dhaka',
                        'is_primary' => true,
                    ]);

                    $roll[$section->id] = ($roll[$section->id] ?? 0) + 1;

                    Enrollment::create([
                        'student_id' => $student->id,
                        'academic_session_id' => $this->session->id,
                        'class_id' => $class->id,
                        'section_id' => $section->id,
                        'roll_no' => $roll[$section->id],
                        'status' => 'active',
                    ]);

                    $students[] = $student;
                    $counter++;
                }
            }
        }

        return $students;
    }

    private function seedPortalAccounts(array $students): void
    {
        if (count($students) < 2) {
            return;
        }

        $roles = Role::pluck('id', 'slug');
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
                'created_by' => $this->admin->id,
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

        foreach (array_slice($students, 0, 2) as $child) {
            $guardian = $child->guardians()->first();

            ParentStudentLink::updateOrCreate(
                ['user_id' => $parentUser->id, 'student_id' => $child->id],
                [
                    'student_guardian_id' => $guardian?->id,
                    'relationship' => $guardian?->relation ?? 'Guardian',
                    'verified_at' => now(),
                    'is_active' => true,
                    'created_by' => $this->admin->id,
                ]
            );
        }
    }

    private function seedFeesAndPayments(array $students): void
    {
        $feeService = app(FeeAssignmentService::class);
        $paymentService = app(PaymentService::class);
        $payments = [];

        foreach ($students as $index => $student) {
            $enrollment = Enrollment::where('student_id', $student->id)
                ->where('academic_session_id', $this->session->id)
                ->first();

            if (! $enrollment) {
                continue;
            }

            $structure = FeeStructure::where('academic_session_id', $this->session->id)
                ->where('class_id', $enrollment->class_id)
                ->where('status', 'active')
                ->first();

            if (! $structure) {
                continue;
            }

            $optional = $index % 4 === 0
                ? $structure->items()->where('is_optional', true)->pluck('id')->all()
                : [];

            $feeService->assignStructure($student, $structure, $this->session, $optional, $this->admin);

            $targetPaidRows = [12, 9, 6, 4, 11, 8, 0, 5, 10, 3][$index % 10];

            if ($targetPaidRows === 0) {
                continue;
            }

            $outstanding = StudentFeeAssignment::query()
                ->where('student_id', $student->id)
                ->where('academic_session_id', $this->session->id)
                ->whereIn('status', ['pending', 'partial'])
                ->orderBy('due_date')
                ->withSum(['paymentAllocations as paid_total' => fn ($query) => $query
                    ->whereHas('payment', fn ($payment) => $payment->where('status', 'completed'))], 'amount')
                ->get()
                ->filter(fn ($assignment) => (float) $assignment->net_amount - (float) $assignment->paid_amount > 0.5)
                ->take($targetPaidRows);

            if ($outstanding->isEmpty()) {
                continue;
            }

            $lines = [];
            foreach ($outstanding as $rowIndex => $assignment) {
                $balance = round((float) $assignment->net_amount - (float) $assignment->paid_amount, 2);
                $lines[$assignment->id] = $index % 7 === 0 && $rowIndex === 0
                    ? round($balance / 2, 2)
                    : $balance;
            }

            $paidAt = now()
                ->subDays(($index * 3) % 47)
                ->setTime(10 + ($index % 7), ($index * 7) % 60);

            $payment = $paymentService->collect(
                $student,
                $this->session,
                $lines,
                [
                    'payment_method' => ['cash', 'bank', 'mobile_banking'][$index % 3],
                    'reference_no' => $index % 3 === 0 ? null : 'TRX-'.strtoupper(substr(md5((string) $index), 0, 10)),
                    'paid_at' => $paidAt,
                    'note' => 'Demo collection',
                ],
                $this->admin
            );

            $payments[] = $payment;
        }

        // One audited reversal so the fee deletion log has a real example.
        if (isset($payments[5])) {
            $paymentService->reverse(
                $payments[5],
                'cancellation',
                'Duplicate cash entry identified during daily reconciliation.',
                $this->admin
            );
        }

        // One approved waiver for a student with unpaid dues.
        $waiverAssignment = StudentFeeAssignment::query()
            ->where('academic_session_id', $this->session->id)
            ->whereIn('status', ['pending', 'partial'])
            ->where('net_amount', '>=', 500)
            ->first();

        if ($waiverAssignment) {
            $feeService->applyWaiver(
                $waiverAssignment,
                500,
                'Hardship concession approved by the principal.',
                $this->admin
            );
        }
    }

    private function seedAttendance(): void
    {
        $attendanceService = app(AttendanceService::class);
        $statusesPool = ['present', 'present', 'present', 'present', 'present', 'present', 'late', 'absent', 'excused'];
        $dates = [];
        $cursor = now()->copy();

        while (count($dates) < 6) {
            if (! in_array($cursor->dayOfWeek, [5, 6], true)) {
                $dates[] = $cursor->toDateString();
            }
            $cursor->subDay();
        }

        foreach ($dates as $dateIndex => $date) {
            foreach (SchoolClass::all() as $class) {
                foreach (Section::all() as $section) {
                    $enrollments = Enrollment::where('academic_session_id', $this->session->id)
                        ->where('class_id', $class->id)
                        ->where('section_id', $section->id)
                        ->where('status', 'active')
                        ->get();

                    if ($enrollments->isEmpty()) {
                        continue;
                    }

                    $statuses = [];
                    foreach ($enrollments as $enrollment) {
                        $statuses[$enrollment->student_id] = $statusesPool[($enrollment->student_id + $dateIndex) % count($statusesPool)];
                    }

                    $attendanceService->mark($this->session, $class, $section, $date, $statuses, $this->admin);
                }
            }
        }
    }

    private function seedExaminations(): void
    {
        $exam = Examination::create([
            'academic_session_id' => $this->session->id,
            'name' => 'First Term Examination 2026',
            'exam_type' => 'term',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-20',
            'status' => 'published',
        ]);

        Examination::create([
            'academic_session_id' => $this->session->id,
            'name' => 'Mid Term Examination 2026',
            'exam_type' => 'mid_term',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-18',
            'status' => 'draft',
        ]);

        $scheme = GradingScheme::query()
            ->where('name', 'Institution Standard 2026')
            ->where('academic_session_id', $this->session->id)
            ->first();

        if ($scheme) {
            $exam->update(['grading_scheme_id' => $scheme->id]);
        }

        $resultService = app(ExamResultService::class);
        $rules = $resultService->rulesForExam($exam->fresh());
        $dayOffset = 0;

        foreach (SchoolClass::where('numeric_level', '>=', 3)->ordered()->get() as $class) {
            $classSubjects = ClassSubject::where('academic_session_id', $this->session->id)
                ->where('class_id', $class->id)
                ->with('subject')
                ->get();

            $enrollments = Enrollment::where('academic_session_id', $this->session->id)
                ->where('class_id', $class->id)
                ->where('status', 'active')
                ->get();

            foreach ($classSubjects as $classSubject) {
                $examSubject = $exam->subjects()->create([
                    'class_id' => $class->id,
                    'subject_id' => $classSubject->subject_id,
                    'full_marks' => $classSubject->subject->full_marks,
                    'pass_marks' => $classSubject->subject->pass_marks,
                    'exam_date' => Carbon::parse('2026-09-10')->addDays($dayOffset % 9)->toDateString(),
                    'start_time' => '10:00',
                    'duration_minutes' => 120,
                ]);

                foreach ($enrollments as $markIndex => $enrollment) {
                    $absent = ($enrollment->student_id + $markIndex + $dayOffset) % 37 === 0;
                    $marks = $absent ? null : min(100, max(30, 48 + (($enrollment->student_id * 7 + $dayOffset * 13) % 50)));

                    $resultService->enterMark($examSubject, $enrollment->student, $marks, $absent, $this->admin, $rules);
                }

                $dayOffset++;
            }
        }

        // Demo marks are finalized and published so the student and parent
        // portals have real, snapshot-backed results to display.
        Mark::query()
            ->whereIn('examination_subject_id', $exam->subjects()->pluck('id'))
            ->update([
                'status' => 'approved',
                'submitted_at' => now(),
                'submitted_by' => $this->admin->id,
                'approved_at' => now(),
                'approved_by' => $this->admin->id,
            ]);

        $exam->update([
            'result_published_at' => now(),
            'result_locked_at' => now(),
            'published_by' => $this->admin->id,
            'locked_by' => $this->admin->id,
            'result_note' => 'Demo results published for portal preview.',
        ]);

        $resultService->publishSnapshots($exam->fresh(), $this->admin, $rules);
    }

    private function seedExpenses(): void
    {
        $categories = ExpenseCategory::pluck('id', 'name');
        $names = $categories->keys()->all();
        $titles = [
            'Electricity and water bill', 'Masjid maintenance', 'Classroom stationery',
            'Science lab supplies', 'Annual sports day', 'School bus fuel and servicing',
            'Internet and software subscription', 'Library book purchase', 'Building repairs',
            'First-aid supplies', 'Examination stationery', 'Staff welfare refreshments',
            'Garden maintenance', 'CCTV servicing', 'Water purifier filters',
        ];

        for ($i = 0; $i < 15; $i++) {
            $categoryName = match (true) {
                $i % 5 === 0 => 'Utility Bills',
                $i % 5 === 1 => 'Maintenance',
                $i % 5 === 2 => 'Stationery',
                $i % 5 === 3 => 'Transport',
                default => 'Events',
            };

            Expense::create([
                'expense_category_id' => $categories[$categoryName],
                'academic_session_id' => $this->session->id,
                'title' => $titles[$i],
                'amount' => 3500 + ($i * 2750),
                'expense_date' => now()->subDays(($i * 9) % 180)->toDateString(),
                'payment_method' => ['cash', 'bank', 'mobile_banking'][$i % 3],
                'reference_no' => $i % 4 === 0 ? 'EXP-'.strtoupper(substr(md5((string) $i), 0, 8)) : null,
                'description' => 'Recorded by the accounts office.',
                'recorded_by' => $this->admin->id,
            ]);
        }
    }

    private function seedPayroll(): void
    {
        $payrollService = app(PayrollService::class);
        $lastMonth = now()->subMonthNoOverflow()->format('Y-m');

        $payrollService->generate($this->session, $lastMonth, $this->admin);

        $payrolls = Payroll::where('academic_session_id', $this->session->id)
            ->where('month', $lastMonth)
            ->orderBy('id')
            ->get();

        foreach ($payrolls as $index => $payroll) {
            $payrollService->process($payroll, $this->admin);

            if ($index < 4) {
                $payrollService->pay($payroll, [
                    'payment_method' => $index % 2 === 0 ? 'bank' : 'cash',
                    'reference_no' => 'SAL-'.strtoupper(substr(md5((string) $index), 0, 8)),
                    'paid_at' => now()->subMonthNoOverflow()->day(28)->setTime(16, 0),
                ], $this->admin);
            }
        }

        $payrollService->generate($this->session, now()->format('Y-m'), $this->admin);
    }

    private function seedNotices(): void
    {
        $notices = [
            [
                'title' => 'Annual Sports Day — 12 November 2026',
                'body' => 'Our annual sports day will be held on 12 November 2026 at the school playground. All students must report by 8:00 AM in house colours. Parents are warmly invited to attend the closing ceremony.',
                'audience' => 'all',
                'status' => 'published',
                'days_ago' => 2,
            ],
            [
                'title' => 'Second Installment Fee Deadline',
                'body' => 'The deadline for the second installment of tuition fees is 31 October 2026. Please clear outstanding dues at the accounts office or through mobile banking. Receipts will be issued immediately.',
                'audience' => 'students',
                'status' => 'published',
                'days_ago' => 5,
            ],
            [
                'title' => 'Parent–Teacher Meeting for Classes 6–10',
                'body' => 'A parent–teacher meeting for Classes 6 to 10 will take place on Saturday, 31 October 2026, from 9:00 AM to 12:00 PM. Subject teachers will share progress reports and discuss individual learning plans.',
                'audience' => 'classes',
                'status' => 'published',
                'days_ago' => 7,
            ],
            [
                'title' => 'Staff Development Workshop',
                'body' => 'A workshop on inclusive classroom practices will be held for all teaching staff on 30 October 2026 in the seminar room. Attendance is mandatory.',
                'audience' => 'staff',
                'status' => 'published',
                'days_ago' => 9,
            ],
            [
                'title' => 'Library Week Reading Challenge',
                'body' => 'Students are invited to join the reading challenge during Library Week. Record every book you read and submit the log to the librarian by 20 November.',
                'audience' => 'students',
                'status' => 'published',
                'days_ago' => 12,
            ],
            [
                'title' => 'Draft — Winter Uniform Guidelines',
                'body' => 'Draft guidelines for the winter uniform are under review by the school committee and will be published after approval.',
                'audience' => 'all',
                'status' => 'draft',
                'days_ago' => 1,
            ],
        ];

        $classes = SchoolClass::whereIn('name', ['Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10'])->pluck('id');

        foreach ($notices as $data) {
            $notice = Notice::create([
                'title' => $data['title'],
                'body' => $data['body'],
                'audience' => $data['audience'],
                'publish_at' => $data['status'] === 'published' ? now()->subDays($data['days_ago']) : null,
                'expires_at' => $data['status'] === 'published' ? now()->addDays(30) : null,
                'status' => $data['status'],
                'created_by' => $this->admin->id,
            ]);

            if ($data['audience'] === 'classes') {
                $notice->classes()->sync($classes->all());
            }
        }
    }

    private function seedEnquiries(): void
    {
        $names = [
            ['Samin Yasar', 'Yasar Ahmed', '01712000111', 'new', 'Walk-in'],
            ['Rida Islam', 'Nusrat Islam', '01712000112', 'contacted', 'Facebook'],
            ['Ayaan Khan', 'Rashed Khan', '01712000113', 'follow_up', 'Referral'],
            ['Meherun Nesa', 'Salma Nesa', '01712000114', 'follow_up', 'Google'],
            ['Tahsin Chowdhury', 'Anwar Chowdhury', '01712000115', 'admitted', 'Walk-in'],
            ['Nusaiba Rahman', 'Mizanur Rahman', '01712000116', 'closed', 'Phone'],
            ['Zayan Mahmud', 'Kamrul Mahmud', '01712000117', 'new', 'Facebook'],
            ['Afia Sultana', 'Rezaul Karim', '01712000118', 'contacted', 'Referral'],
        ];

        $classes = SchoolClass::whereIn('name', ['Play Group', 'Nursery', 'KG', 'Class 1', 'Class 2'])->get();

        foreach ($names as $index => $row) {
            AdmissionEnquiry::create([
                'enquiry_no' => 'ENQ-2026-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'student_name' => $row[0],
                'guardian_name' => $row[1],
                'phone' => $row[2],
                'email' => null,
                'class_id' => $classes[$index % $classes->count()]->id,
                'follow_up_date' => now()->addDays($index - 2)->toDateString(),
                'source' => $row[4],
                'status' => $row[3],
                'notes' => 'Initial enquiry recorded by the admissions desk.',
                'created_by' => $this->admin->id,
            ]);
        }
    }

    private function seedArchivedStudents(): void
    {
        $session2025 = AcademicSession::where('name', '2025')->first();
        $classTen = SchoolClass::where('name', 'Class 10')->first();
        $classNine = SchoolClass::where('name', 'Class 9')->first();
        $sectionA = Section::where('name', 'A')->first();
        $numbers = app(DocumentNumberService::class);

        for ($i = 0; $i < 7; $i++) {
            $status = $i < 5 ? 'passed_out' : 'transferred';

            $student = Student::create([
                'admission_no' => $numbers->admissionNumber(2025),
                'first_name' => ['Sadia', 'Rafiq', 'Mim', 'Tanvir', 'Nabila', 'Sabbir', 'Tania'][$i],
                'last_name' => ['Hossain', 'Ahmed', 'Rahman', 'Islam', 'Akter', 'Khan', 'Chowdhury'][$i],
                'gender' => $i % 2 === 0 ? 'female' : 'male',
                'date_of_birth' => now()->subYears(16 + ($i % 2))->toDateString(),
                'religion' => 'Islam',
                'nationality' => 'Bangladeshi',
                'phone' => '+880 1911 00'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'address' => 'Mohammadpur, Dhaka',
                'city' => 'Dhaka',
                'state' => 'Dhaka',
                'postal_code' => '1207',
                'admission_date' => '2024-01-05',
                'status' => $status,
            ]);

            $student->guardians()->create([
                'name' => $student->first_name.' '.$student->last_name,
                'relation' => 'Father',
                'phone' => $student->phone,
                'is_primary' => true,
            ]);

            Enrollment::create([
                'student_id' => $student->id,
                'academic_session_id' => $session2025?->id ?? $this->session->id,
                'class_id' => $i < 5 ? $classTen->id : $classNine->id,
                'section_id' => $sectionA->id,
                'roll_no' => $i + 1,
                'status' => $status === 'passed_out' ? 'completed' : 'transferred',
                'remarks' => $status === 'passed_out' ? 'Completed Class 10 in 2025.' : 'Transferred to another school.',
            ]);
        }
    }
}

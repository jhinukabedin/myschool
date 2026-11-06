<?php

return [
    'currency' => 'à§³',
    'currency_code' => 'BDT',
    'date_format' => 'd M Y',
    'timezone' => 'Asia/Dhaka',

    /*
    |--------------------------------------------------------------------------
    | Fee billing window
    |--------------------------------------------------------------------------
    | How many months ahead of the current month are shown/treated as payable
    | by default. Past-due months are always included. Future months beyond
    | this window stay hidden (and out of outstanding totals) until a user
    | with fee-collection permission explicitly opts in to advance payment.
    */
    'fee_window_months_ahead' => 1,

    /*
    |--------------------------------------------------------------------------
    | Portal accounts
    |--------------------------------------------------------------------------
    | A single configurable default password is assigned to automatically
    | created student, parent and staff accounts. Users change it after their
    | first login from Account > Change password.
    */
    'default_password' => env('DEFAULT_USER_PASSWORD', 'School@12345'),

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    | Canonical list of granular permissions. Seeded into the database and
    | registered as Laravel gates so @can() and middleware share one source.
    */
    'permissions' => [
        'dashboard.view' => ['name' => 'View dashboard', 'module' => 'Dashboard'],

        'students.view' => ['name' => 'View students', 'module' => 'Students'],
        'students.create' => ['name' => 'Admit / create students', 'module' => 'Students'],
        'students.update' => ['name' => 'Edit students', 'module' => 'Students'],
        'students.archive' => ['name' => 'Archive / change student status', 'module' => 'Students'],
        'students.promote' => ['name' => 'Promote students', 'module' => 'Students'],
        'enquiries.view' => ['name' => 'View admission enquiries', 'module' => 'Students'],
        'enquiries.manage' => ['name' => 'Manage admission enquiries', 'module' => 'Students'],

        'academics.view' => ['name' => 'View academic setup', 'module' => 'Academics'],
        'academics.manage' => ['name' => 'Manage sessions, classes and subjects', 'module' => 'Academics'],

        'fees.view' => ['name' => 'View fee structures and dues', 'module' => 'Fees'],
        'fees.manage' => ['name' => 'Manage fee structures and assignments', 'module' => 'Fees'],
        'fees.collect' => ['name' => 'Collect fees and issue receipts', 'module' => 'Fees'],
        'fees.reverse' => ['name' => 'Cancel or refund payments', 'module' => 'Fees'],
        'fees.waive' => ['name' => 'Approve waivers and adjustments', 'module' => 'Fees'],

        'scholarships.view' => ['name' => 'View scholarships', 'module' => 'Fees'],
        'scholarships.create' => ['name' => 'Create scholarships', 'module' => 'Fees'],
        'scholarships.update' => ['name' => 'Edit scholarships', 'module' => 'Fees'],
        'scholarships.delete' => ['name' => 'Delete scholarships', 'module' => 'Fees'],
        'scholarships.approve' => ['name' => 'Approve scholarships', 'module' => 'Fees'],
        'scholarships.suspend' => ['name' => 'Suspend / reactivate scholarships', 'module' => 'Fees'],
        'scholarships.apply_to_invoices' => ['name' => 'Apply scholarship to existing unpaid invoices', 'module' => 'Fees'],

        'attendance.view' => ['name' => 'View attendance', 'module' => 'Attendance'],
        'attendance.manage' => ['name' => 'Mark attendance', 'module' => 'Attendance'],
        'attendance.report' => ['name' => 'View attendance reports', 'module' => 'Attendance'],

        'exams.view' => ['name' => 'View examinations', 'module' => 'Academics'],
        'exams.manage' => ['name' => 'Manage examinations and schedules', 'module' => 'Academics'],
        'marks.enter' => ['name' => 'Enter marks', 'module' => 'Academics'],
        'marks.submit' => ['name' => 'Submit marks for approval', 'module' => 'Academics'],
        'marks.approve' => ['name' => 'Approve and finalize marks', 'module' => 'Academics'],
        'marks.correct' => ['name' => 'Correct finalized marks', 'module' => 'Academics'],
        'results.view' => ['name' => 'View and print results', 'module' => 'Academics'],
        'assignments.view' => ['name' => 'View teacher assignments', 'module' => 'Academics'],
        'assignments.manage' => ['name' => 'Manage teacher assignments', 'module' => 'Academics'],
        'routines.view' => ['name' => 'View class routines', 'module' => 'Academics'],
        'routines.create' => ['name' => 'Create class routines', 'module' => 'Academics'],
        'routines.edit' => ['name' => 'Edit class routines and time slots', 'module' => 'Academics'],
        'routines.delete' => ['name' => 'Delete class routines', 'module' => 'Academics'],
        'routines.copy' => ['name' => 'Copy class routines', 'module' => 'Academics'],
        'routines.print' => ['name' => 'Print or download class routines', 'module' => 'Academics'],

        'finance.view' => ['name' => 'View expenses and finance', 'module' => 'Finance'],
        'finance.manage' => ['name' => 'Manage expenses', 'module' => 'Finance'],

        'staff.view' => ['name' => 'View staff profiles', 'module' => 'Staff'],
        'staff.manage' => ['name' => 'Manage staff profiles', 'module' => 'Staff'],
        'payroll.view' => ['name' => 'View salary information', 'module' => 'Payroll'],
        'payroll.manage' => ['name' => 'Process payroll and salary payments', 'module' => 'Payroll'],

        'notices.view' => ['name' => 'View notices', 'module' => 'Communication'],
        'notices.manage' => ['name' => 'Manage notices', 'module' => 'Communication'],
        'communication.whatsapp' => ['name' => 'Send WhatsApp reminders', 'module' => 'Communication'],

        'reports.view' => ['name' => 'View reports', 'module' => 'Reports'],
        'reports.export' => ['name' => 'Export reports', 'module' => 'Reports'],

        'settings.view' => ['name' => 'View settings', 'module' => 'Administration'],
        'settings.manage' => ['name' => 'Manage school settings', 'module' => 'Administration'],
        'users.manage' => ['name' => 'Manage users and roles', 'module' => 'Administration'],
        'audit.view' => ['name' => 'View audit logs', 'module' => 'Administration'],
        'website.view' => ['name' => 'View website content and messages', 'module' => 'Website'],
        'website.manage' => ['name' => 'Manage website content and messages', 'module' => 'Website'],
        'website.publish' => ['name' => 'Publish, unpublish and restore website content', 'module' => 'Website'],
        'website.media' => ['name' => 'Manage the website media library', 'module' => 'Website'],
        'website.menus' => ['name' => 'Manage public website menus', 'module' => 'Website'],
        'website.code' => ['name' => 'Edit approved website code snippets', 'module' => 'Website'],
        'portals.manage' => ['name' => 'Create and link student/parent portal accounts', 'module' => 'Portals'],
        'subscription.view' => ['name' => 'View subscription and module access status', 'module' => 'Administration'],
        'subscription.manage' => ['name' => 'Enable or lock school modules', 'module' => 'Administration'],
        'access.manage' => ['name' => 'Give permissions to other users', 'module' => 'Administration'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Self-managed roles
    |--------------------------------------------------------------------------
    | Users in these roles use the permissions of their role directly (the
    | School Administrator's create, edit and delete rights come from the role,
    | which the super administrator controls). Every other staff user only has
    | the permissions explicitly given to them, one by one.
    */
    'self_managed_roles' => ['admin'],

    /*
    |--------------------------------------------------------------------------
    | Subscription modules
    |--------------------------------------------------------------------------
    | The super administrator can enable each paid module. The website content
    | module is core and always available. Module locks are applied on top of
    | normal role permissions, so a user needs both an enabled module and the
    | matching role permission to open a page.
    */
    'modules' => [
        'students' => [
            'name' => 'Student Management',
            'description' => 'Admissions, student records, promotion and portal accounts.',
            'sort_order' => 10,
        ],
        'academics' => [
            'name' => 'Academic Management',
            'description' => 'Sessions, classes, sections, subjects, routines and shifts.',
            'sort_order' => 20,
        ],
        'attendance' => [
            'name' => 'Attendance',
            'description' => 'Student attendance registers and reports.',
            'sort_order' => 30,
        ],
        'exams' => [
            'name' => 'Examination & Results',
            'description' => 'Examinations, marks entry, grading and result publishing.',
            'sort_order' => 40,
        ],
        'fees' => [
            'name' => 'Fees & Payments',
            'description' => 'Fee structures, collection, receipts, scholarships and reminders.',
            'sort_order' => 50,
        ],
        'finance' => [
            'name' => 'Finance & Accounts',
            'description' => 'Expenses, other income and income-versus-expense reporting.',
            'sort_order' => 60,
        ],
        'staff' => [
            'name' => 'Staff Management',
            'description' => 'Staff profiles, attendance, leave and qualifications.',
            'sort_order' => 70,
        ],
        'payroll' => [
            'name' => 'Payroll',
            'description' => 'Salary structures, payroll processing, payslips and reports.',
            'sort_order' => 80,
        ],
        'communication' => [
            'name' => 'Communication',
            'description' => 'Notice board, WhatsApp reminders and contact messages.',
            'sort_order' => 90,
        ],
        'reports' => [
            'name' => 'Reports',
            'description' => 'Session, collection, finance and management reports.',
            'sort_order' => 100,
        ],
        'administration' => [
            'name' => 'Administration',
            'description' => 'School settings, users, roles, audit log and portal accounts.',
            'sort_order' => 110,
        ],
        'core' => [
            'name' => 'System Access',
            'description' => 'Subscription, user access and role management. Always available.',
            'core' => true,
            'sort_order' => 115,
        ],
        'website' => [
            'name' => 'Website Content',
            'description' => 'Public website pages, menus, media and result search.',
            'core' => true,
            'sort_order' => 120,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    | Each feature is a menu-level area inside a module. A route belongs to the
    | feature whose prefix matches it most specifically. Access then works in
    | layers:
    |   1. Core features (module "core" or "website") are always open.
    |   2. A feature is open when its module is enabled in the subscription.
    |   3. Otherwise it opens only when the super administrator has unlocked
    |      that feature for the user's role (role_feature_grants).
    |   4. The role must still hold the matching permission.
    */
    'features' => [
        'subscription' => ['name' => 'Subscription & Modules', 'module' => 'core', 'routes' => ['subscription.'], 'page' => 'subscription.index'],

        'student-records' => ['name' => 'Student records & admissions', 'module' => 'students', 'routes' => ['students.', 'admissions.', 'enquiries.'], 'page' => 'students.index'],
        'promotion' => ['name' => 'Promotion', 'module' => 'students', 'routes' => ['promotion.'], 'page' => 'promotion.index'],

        'subjects' => ['name' => 'Subjects & class-subject map', 'module' => 'academics', 'routes' => ['subjects.', 'class-subjects.'], 'page' => 'subjects.index'],
        'classes-sections' => ['name' => 'Classes & sections', 'module' => 'academics', 'routes' => ['classes.', 'sections.'], 'page' => 'classes.index'],
        'sessions' => ['name' => 'Academic sessions', 'module' => 'academics', 'routes' => ['sessions.'], 'page' => 'sessions.index'],
        'teacher-assignments' => ['name' => 'Teacher assignments', 'module' => 'academics', 'routes' => ['assignments.'], 'page' => 'assignments.index'],
        'class-routine' => ['name' => 'Class routine', 'module' => 'academics', 'routes' => ['routines.'], 'page' => 'routines.index'],
        'holidays-calendar' => ['name' => 'Holidays & academic calendar', 'module' => 'academics', 'routes' => ['holidays.'], 'page' => 'holidays.index'],
        'shifts' => ['name' => 'Shifts', 'module' => 'academics', 'routes' => ['shifts.'], 'page' => 'shifts.index'],

        'attendance' => ['name' => 'Attendance register & report', 'module' => 'attendance', 'routes' => ['attendance.'], 'page' => 'attendance.index'],

        'exams' => ['name' => 'Exams, marks & results', 'module' => 'exams', 'routes' => ['exams.'], 'page' => 'exams.index'],

        'fee-structure' => ['name' => 'Fee structures', 'module' => 'fees', 'routes' => ['fees.structures.'], 'page' => 'fees.structures.index'],
        'fee-collection' => ['name' => 'Fee collection & payment history', 'module' => 'fees', 'routes' => ['fees.collection.', 'fees.quick.', 'fees.payment-history', 'fees.deletion-log', 'payments.'], 'page' => 'fees.collection.index'],
        'scholarships' => ['name' => 'Scholarships', 'module' => 'fees', 'routes' => ['fees.scholarships.'], 'page' => 'fees.scholarships.index'],
        'fee-reminders' => ['name' => 'WhatsApp fee reminders', 'module' => 'fees', 'routes' => ['fees.reminders.'], 'page' => 'fees.reminders.index'],

        'expenses' => ['name' => 'Expenses', 'module' => 'finance', 'routes' => ['expenses.'], 'page' => 'expenses.index'],
        'other-income' => ['name' => 'Other income', 'module' => 'finance', 'routes' => ['other-incomes.'], 'page' => 'other-incomes.index'],

        'reports' => ['name' => 'Reports & analytics', 'module' => 'reports', 'routes' => ['reports.'], 'page' => 'reports.index'],
        'session-report' => ['name' => 'Session report', 'module' => 'reports', 'routes' => ['session-report.'], 'page' => 'session-report.index'],

        'staff-management' => ['name' => 'Staff profiles, attendance & leave', 'module' => 'staff', 'routes' => ['staff.'], 'page' => 'staff.index'],
        'payroll' => ['name' => 'Salary & payroll', 'module' => 'payroll', 'routes' => ['payroll.'], 'page' => 'payroll.index'],

        'notices' => ['name' => 'Notice board', 'module' => 'communication', 'routes' => ['notices.'], 'page' => 'notices.index'],

        'user-access' => ['name' => 'User access & permissions', 'module' => 'core', 'routes' => ['access.'], 'page' => 'access.index'],
        'users-roles' => ['name' => 'Users & roles', 'module' => 'administration', 'routes' => ['users.', 'roles.'], 'page' => 'users.index'],
        'audit-log' => ['name' => 'Audit log', 'module' => 'administration', 'routes' => ['audit.'], 'page' => 'audit.index'],
        'school-settings' => ['name' => 'School settings', 'module' => 'administration', 'routes' => ['settings.', 'admin.'], 'page' => 'settings.index'],
        'portal-accounts' => ['name' => 'Student & parent portal accounts', 'module' => 'administration', 'routes' => ['portal-accounts.'], 'page' => 'portal-accounts.index'],

        'website' => ['name' => 'Website content', 'module' => 'website', 'routes' => ['website-admin.', 'website-content.', 'website-menus.', 'media.', 'messages.'], 'page' => 'website-admin.index'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles that never receive feature unlocks
    |--------------------------------------------------------------------------
    | Portal accounts stay in their own portals. Super admin already has access.
    */
    'portal_only_roles' => ['student', 'parent'],

    'roles' => [
        'super-admin' => [
            'name' => 'Super Administrator',
            'description' => 'Unrestricted access to every module.',
            'permissions' => '*',
        ],
        'admin' => [
            'name' => 'Administrator',
            'description' => 'Runs day-to-day academic and administrative operations.',
            'permissions' => [
                'dashboard.view',
                'students.view', 'students.create', 'students.update', 'students.archive',
                'students.promote', 'enquiries.view', 'enquiries.manage',
                'academics.view', 'academics.manage',
                'fees.view', 'fees.manage', 'fees.collect', 'fees.reverse', 'fees.waive',
                'scholarships.view', 'scholarships.create', 'scholarships.update', 'scholarships.delete',
                'scholarships.approve', 'scholarships.suspend', 'scholarships.apply_to_invoices',
                'attendance.view', 'attendance.manage', 'attendance.report',
                'exams.view', 'exams.manage', 'marks.enter', 'marks.submit', 'marks.approve', 'marks.correct', 'results.view',
                'assignments.view', 'assignments.manage',
                'routines.view', 'routines.create', 'routines.edit', 'routines.delete', 'routines.copy', 'routines.print',
                'finance.view', 'finance.manage',
                'staff.view', 'staff.manage', 'payroll.view', 'payroll.manage',
                'notices.view', 'notices.manage', 'communication.whatsapp',
                'reports.view', 'reports.export',
                'settings.view', 'audit.view', 'website.view', 'website.manage',
                'website.publish', 'website.media', 'website.menus', 'website.code', 'portals.manage',
                'subscription.view', 'settings.manage', 'access.manage',
            ],
        ],
        'principal' => [
            'name' => 'Principal',
            'description' => 'Oversees academics, attendance, exams, staff and reports with read-only finance access.',
            'permissions' => [
                'dashboard.view',
                'students.view', 'students.create', 'students.update', 'students.archive', 'students.promote',
                'enquiries.view', 'enquiries.manage',
                'academics.view', 'academics.manage',
                'attendance.view', 'attendance.manage', 'attendance.report',
                'exams.view', 'exams.manage', 'marks.enter', 'marks.submit', 'marks.approve', 'marks.correct',
                'results.view', 'assignments.view', 'assignments.manage',
                'routines.view', 'routines.create', 'routines.edit', 'routines.delete', 'routines.copy', 'routines.print',
                'fees.view', 'finance.view', 'scholarships.view',
                'staff.view', 'payroll.view',
                'notices.view', 'notices.manage', 'communication.whatsapp',
                'reports.view', 'reports.export',
                'settings.view', 'audit.view', 'website.view',
            ],
            'is_system' => false,
        ],
        'data-entry' => [
            'name' => 'Data Entry',
            'description' => 'Enters admissions, attendance, marks and student details without delete or finance rights.',
            'permissions' => [
                'dashboard.view',
                'students.view', 'students.create', 'students.update',
                'enquiries.view', 'enquiries.manage',
                'academics.view', 'attendance.view', 'attendance.manage',
                'exams.view', 'marks.enter', 'results.view',
                'fees.view', 'notices.view',
            ],
            'is_system' => false,
        ],
        'accountant' => [
            'name' => 'Accountant',
            'description' => 'Handles fee collection, expenses and financial reporting.',
            'permissions' => [
                'dashboard.view', 'students.view', 'fees.view', 'fees.collect',
                'fees.reverse', 'fees.waive', 'finance.view', 'finance.manage',
                'payroll.view', 'payroll.manage', 'reports.view', 'reports.export',
                'notices.view',
            ],
        ],
        'teacher' => [
            'name' => 'Teacher',
            'description' => 'Marks attendance, enters marks and views results.',
            'permissions' => [
                'dashboard.view', 'students.view', 'academics.view',
                'attendance.view', 'attendance.manage', 'attendance.report',
                'exams.view', 'marks.enter', 'marks.submit', 'results.view', 'notices.view',
                'routines.view',
            ],
        ],
        'registrar' => [
            'name' => 'Registrar',
            'description' => 'Manages admissions, student records and promotion.',
            'permissions' => [
                'dashboard.view', 'students.view', 'students.create', 'students.update',
                'students.archive', 'students.promote', 'enquiries.view', 'enquiries.manage',
                'academics.view', 'assignments.view', 'fees.view', 'notices.view', 'reports.view',
                'routines.view', 'website.view',
            ],
        ],
        'staff' => [
            'name' => 'Staff',
            'description' => 'Read-only dashboard and notices access.',
            'permissions' => ['dashboard.view', 'notices.view'],
        ],
        'student' => [
            'name' => 'Student',
            'description' => 'Student portal access only. Records are restricted to the linked student.',
            'permissions' => [],
        ],
        'parent' => [
            'name' => 'Parent / Guardian',
            'description' => 'Parent portal access only. Records are restricted to explicitly linked children.',
            'permissions' => [],
        ],
        'website-admin' => [
            'name' => 'Website Administrator',
            'description' => 'Manages the public website, menus, media and approved code snippets.',
            'permissions' => [
                'dashboard.view', 'website.view', 'website.manage', 'website.publish',
                'website.media', 'website.menus', 'website.code',
            ],
        ],
        'website-editor' => [
            'name' => 'Website Editor',
            'description' => 'Creates and publishes pages, news, events and gallery items.',
            'permissions' => ['dashboard.view', 'website.view', 'website.manage', 'website.publish', 'website.media'],
        ],
        'media-manager' => [
            'name' => 'Media Manager',
            'description' => 'Uploads, previews and removes website media without access to academic or financial records.',
            'permissions' => ['dashboard.view', 'website.view', 'website.media'],
        ],
    ],
];

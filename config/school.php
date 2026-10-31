<?php

return [
    'currency' => '৳',
    'currency_code' => 'BDT',
    'date_format' => 'd M Y',
    'timezone' => 'Asia/Dhaka',

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
    ],

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
            ],
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

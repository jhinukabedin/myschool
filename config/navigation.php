<?php

/*
|--------------------------------------------------------------------------
| Navigation catalogue
|--------------------------------------------------------------------------
| Every menu shown in the admin sidebar lives here. The sidebar renders this
| list, and Subscription & Modules lists the same items grouped by module.
| Add a new menu here once and it appears in both places automatically.
|
| key    - stable identifier used for the per-menu switch (menu_{key})
| module - subscription module that owns the menu (config/school.php modules)
| route  - route name, or [route name, parameter] for routes with arguments
| pattern- route name pattern(s) that keep the menu highlighted / gated
| can    - permission required to see the menu
*/

return [

    'groups' => [
        [
            'label' => 'Student Management',
            'items' => [
                ['key' => 'enquiries', 'title' => 'Admission Enquiry', 'icon' => 'message-circle', 'module' => 'students', 'route' => 'enquiries.index', 'pattern' => 'enquiries.*', 'can' => 'enquiries.view'],
                ['key' => 'admissions', 'title' => 'New Admission', 'icon' => 'user-plus', 'module' => 'students', 'route' => 'admissions.create', 'pattern' => 'admissions.*', 'can' => 'students.create'],
                ['key' => 'students', 'title' => 'All Students', 'icon' => 'users', 'module' => 'students', 'route' => 'students.index', 'pattern' => 'students.index|students.show|students.edit', 'can' => 'students.view'],
                ['key' => 'promotion', 'title' => 'Promotion', 'icon' => 'trending-up', 'module' => 'students', 'route' => 'promotion.index', 'pattern' => 'promotion.*', 'can' => 'students.promote'],
                ['key' => 'passed-out', 'title' => 'Passed Out Archive', 'icon' => 'archive', 'module' => 'students', 'route' => 'students.archive', 'pattern' => 'students.archive', 'can' => 'students.view'],
                ['key' => 'session-report', 'title' => 'Session Report', 'icon' => 'file-bar-chart', 'module' => 'reports', 'route' => 'session-report.index', 'pattern' => 'session-report.*', 'can' => 'reports.view'],
            ],
        ],
        [
            'label' => 'Academic Management',
            'items' => [
                ['key' => 'my-teaching', 'title' => 'My Teaching', 'icon' => 'book-open', 'module' => 'academics', 'route' => 'teacher.dashboard', 'pattern' => 'teacher.dashboard', 'can' => 'marks.enter'],
                ['key' => 'subjects', 'title' => 'Subjects', 'icon' => 'book-open', 'module' => 'academics', 'route' => 'subjects.index', 'pattern' => 'subjects.*', 'can' => 'academics.view'],
                ['key' => 'classes', 'title' => 'Classes', 'icon' => 'building', 'module' => 'academics', 'route' => 'classes.index', 'pattern' => 'classes.*', 'can' => 'academics.manage'],
                ['key' => 'sections', 'title' => 'Sections', 'icon' => 'list', 'module' => 'academics', 'route' => 'sections.index', 'pattern' => 'sections.*', 'can' => 'academics.manage'],
                ['key' => 'class-subjects', 'title' => 'Class-Subject Map', 'icon' => 'list-checks', 'module' => 'academics', 'route' => 'class-subjects.index', 'pattern' => 'class-subjects.*', 'can' => 'academics.view'],
                ['key' => 'teacher-assignments', 'title' => 'Teacher Assignments', 'icon' => 'users', 'module' => 'academics', 'route' => 'assignments.index', 'pattern' => 'assignments.*', 'can' => 'assignments.view'],
                ['key' => 'class-routine', 'title' => 'Class Routine', 'icon' => 'clipboard-list', 'module' => 'academics', 'route' => 'routines.index', 'pattern' => 'routines.*', 'can' => 'routines.view'],
                ['key' => 'holidays', 'title' => 'Holidays & Calendar', 'icon' => 'calendar', 'module' => 'academics', 'route' => 'holidays.index', 'pattern' => 'holidays.*', 'can' => 'academics.view'],
                ['key' => 'shifts', 'title' => 'Shifts', 'icon' => 'clock', 'module' => 'academics', 'route' => 'shifts.index', 'pattern' => 'shifts.*', 'can' => 'academics.manage'],
            ],
        ],
        [
            'label' => 'Attendance Management',
            'items' => [
                ['key' => 'attendance', 'title' => 'Attendance', 'icon' => 'calendar-check', 'module' => 'attendance', 'route' => 'attendance.index', 'pattern' => 'attendance.index', 'can' => 'attendance.view'],
                ['key' => 'attendance-report', 'title' => 'Attendance Report', 'icon' => 'clipboard-list', 'module' => 'attendance', 'route' => 'attendance.report', 'pattern' => 'attendance.report', 'can' => 'attendance.report'],
            ],
        ],
        [
            'label' => 'Examination & Results',
            'items' => [
                ['key' => 'exams', 'title' => 'Exams', 'icon' => 'graduation-cap', 'module' => 'exams', 'route' => 'exams.index', 'pattern' => 'exams.index|exams.create|exams.edit|exams.show', 'can' => 'exams.view'],
                ['key' => 'marks-distribution', 'title' => 'Marks Distribution', 'icon' => 'sliders', 'module' => 'exams', 'route' => 'exams.distributions', 'pattern' => 'exams.distributions*', 'can' => 'exams.manage'],
                ['key' => 'exam-schedule', 'title' => 'Exam Schedule', 'icon' => 'calendar', 'module' => 'exams', 'route' => 'exams.schedule', 'pattern' => 'exams.schedule', 'can' => 'exams.view'],
                ['key' => 'marks-entry', 'title' => 'Marks Entry', 'icon' => 'pencil', 'module' => 'exams', 'route' => 'exams.marks-entry', 'pattern' => 'exams.marks-entry|exams.marks-store', 'can' => 'marks.enter'],
                ['key' => 'class-result', 'title' => 'Class Result', 'icon' => 'file-bar-chart', 'module' => 'exams', 'route' => 'exams.class-result', 'pattern' => 'exams.class-result|exams.result-sheet', 'can' => 'results.view'],
            ],
        ],
        [
            'label' => 'Fees & Payments',
            'items' => [
                ['key' => 'fee-structure', 'title' => 'Fee Structure', 'icon' => 'wallet', 'module' => 'fees', 'route' => 'fees.structures.index', 'pattern' => 'fees.structures.*', 'can' => 'fees.view'],
                ['key' => 'fee-collection', 'title' => 'Fee Collection', 'icon' => 'receipt', 'module' => 'fees', 'route' => 'fees.collection.index', 'pattern' => 'fees.collection.*', 'can' => 'fees.collect'],
                ['key' => 'scholarships', 'title' => 'Scholarships', 'icon' => 'graduation-cap', 'module' => 'fees', 'route' => 'fees.scholarships.index', 'pattern' => 'fees.scholarships.*', 'can' => 'scholarships.view'],
                ['key' => 'quick-collect', 'title' => 'Quick Fee Collect', 'icon' => 'zap', 'module' => 'fees', 'route' => 'fees.quick.index', 'pattern' => 'fees.quick.*', 'can' => 'fees.collect'],
                ['key' => 'payment-history', 'title' => 'Payment History', 'icon' => 'clock', 'module' => 'fees', 'route' => 'fees.payment-history', 'pattern' => 'fees.payment-history', 'can' => 'fees.view'],
                ['key' => 'fee-reminders', 'title' => 'WhatsApp Reminder', 'icon' => 'message-circle', 'module' => 'fees', 'route' => 'fees.reminders.index', 'pattern' => 'fees.reminders.*', 'can' => 'fees.view'],
                ['key' => 'fee-deletion-log', 'title' => 'Fee Deletion Log', 'icon' => 'trash', 'module' => 'fees', 'route' => 'fees.deletion-log', 'pattern' => 'fees.deletion-log', 'can' => 'fees.view'],
            ],
        ],
        [
            'label' => 'Finance & Accounts',
            'items' => [
                ['key' => 'expenses', 'title' => 'Expenses', 'icon' => 'credit-card', 'module' => 'finance', 'route' => 'expenses.index', 'pattern' => 'expenses.*', 'can' => 'finance.view'],
                ['key' => 'other-income', 'title' => 'Other Income', 'icon' => 'plus-circle', 'module' => 'finance', 'route' => 'other-incomes.index', 'pattern' => 'other-incomes.*', 'can' => 'finance.view'],
                ['key' => 'income-expense', 'title' => 'Income vs Expense', 'icon' => 'trending-down', 'module' => 'reports', 'route' => ['reports.show', 'income-expense'], 'pattern' => 'reports.show', 'can' => 'reports.view'],
                ['key' => 'reports', 'title' => 'All Reports', 'icon' => 'pie-chart', 'module' => 'reports', 'route' => 'reports.index', 'pattern' => 'reports.index', 'can' => 'reports.view'],
            ],
        ],
        [
            'label' => 'Staff & Payroll',
            'items' => [
                ['key' => 'staff-management', 'title' => 'Staff Management', 'icon' => 'briefcase', 'module' => 'staff', 'route' => 'staff.index', 'pattern' => 'staff.*', 'can' => 'staff.view'],
                ['key' => 'staff-attendance', 'title' => 'Staff Attendance', 'icon' => 'calendar-check', 'module' => 'staff', 'route' => 'staff.attendance.index', 'pattern' => 'staff.attendance.*', 'can' => 'staff.view'],
                ['key' => 'staff-leave', 'title' => 'Staff Leave', 'icon' => 'calendar', 'module' => 'staff', 'route' => 'staff.leaves.index', 'pattern' => 'staff.leaves.*', 'can' => 'staff.view'],
                ['key' => 'payroll', 'title' => 'Salary Management', 'icon' => 'banknote', 'module' => 'payroll', 'route' => 'payroll.index', 'pattern' => 'payroll.index|payroll.generate|payroll.process|payroll.pay|payroll.slip', 'can' => 'payroll.view'],
                ['key' => 'payroll-report', 'title' => 'Salary Report', 'icon' => 'file-text', 'module' => 'payroll', 'route' => 'payroll.report', 'pattern' => 'payroll.report', 'can' => 'payroll.view'],
            ],
        ],
        [
            'label' => 'Communication',
            'items' => [
                ['key' => 'notices', 'title' => 'Notices', 'icon' => 'bell', 'module' => 'communication', 'route' => 'notices.index', 'pattern' => 'notices.*', 'can' => 'notices.view'],
            ],
        ],
        [
            'label' => 'Website Management',
            'items' => [
                ['key' => 'website-dashboard', 'title' => 'Website Dashboard', 'icon' => 'layout-dashboard', 'module' => 'website', 'route' => 'website-admin.index', 'pattern' => 'website-admin.index', 'can' => 'website.view'],
                ['key' => 'website-header', 'title' => 'Header Area', 'icon' => 'layout', 'module' => 'website', 'route' => 'website-admin.header', 'pattern' => 'website-admin.header', 'can' => 'website.view'],
                ['key' => 'website-footer', 'title' => 'Footer Area', 'icon' => 'layout', 'module' => 'website', 'route' => 'website-admin.footer', 'pattern' => 'website-admin.footer', 'can' => 'website.view'],
                ['key' => 'website-hero', 'title' => 'Hero Section', 'icon' => 'layout', 'module' => 'website', 'route' => 'website-admin.hero', 'pattern' => 'website-admin.hero|website-admin.sliders|website-admin.featured-menu', 'can' => 'website.view'],
                ['key' => 'website-introduction', 'title' => 'Institution Introduction', 'icon' => 'message-circle', 'module' => 'website', 'route' => 'website-admin.introduction', 'pattern' => 'website-admin.introduction', 'can' => 'website.view'],
                ['key' => 'website-notice', 'title' => 'Notice', 'icon' => 'bell', 'module' => 'website', 'route' => 'website-admin.notice', 'pattern' => 'website-admin.notice', 'can' => 'website.view'],
                ['key' => 'website-result-search', 'title' => 'Result Search', 'icon' => 'search', 'module' => 'website', 'route' => 'website-admin.result-search', 'pattern' => 'website-admin.result-search', 'can' => 'website.view'],
                ['key' => 'website-management-message', 'title' => 'Message of Management', 'icon' => 'user', 'module' => 'website', 'route' => 'website-admin.administration-message', 'pattern' => 'website-admin.administration-message|website-admin.principal-message|website-admin.president-message', 'can' => 'website.view'],
                ['key' => 'website-academic-corner', 'title' => 'Academic Corner', 'icon' => 'graduation-cap', 'module' => 'website', 'route' => 'website-admin.academic-corner', 'pattern' => 'website-admin.academic-corner', 'can' => 'website.view'],
                ['key' => 'website-admission-info', 'title' => 'Admission Info', 'icon' => 'user-plus', 'module' => 'website', 'route' => 'website-admin.admission-info', 'pattern' => 'website-admin.admission-info', 'can' => 'website.view'],
                ['key' => 'website-class-routine', 'title' => 'Class Routine', 'icon' => 'list', 'module' => 'website', 'route' => 'website-admin.class-routine', 'pattern' => 'website-admin.class-routine', 'can' => 'website.view'],
                ['key' => 'website-academic-calendar', 'title' => 'Academic Calendar', 'icon' => 'calendar', 'module' => 'website', 'route' => 'website-admin.academic-calendar', 'pattern' => 'website-admin.academic-calendar', 'can' => 'website.view'],
                ['key' => 'website-syllabus', 'title' => 'Syllabus', 'icon' => 'file-text', 'module' => 'website', 'route' => 'website-admin.syllabus', 'pattern' => 'website-admin.syllabus', 'can' => 'website.view'],
                ['key' => 'website-assignments', 'title' => 'Assignments', 'icon' => 'clipboard-list', 'module' => 'website', 'route' => 'website-admin.assignments', 'pattern' => 'website-admin.assignments', 'can' => 'website.view'],
                ['key' => 'website-news', 'title' => 'News & Event', 'icon' => 'file-text', 'module' => 'website', 'route' => 'website-admin.news', 'pattern' => 'website-admin.news|website-admin.events', 'can' => 'website.view'],
                ['key' => 'website-gallery', 'title' => 'Image Gallery', 'icon' => 'eye', 'module' => 'website', 'route' => 'website-admin.gallery', 'pattern' => 'website-admin.gallery', 'can' => 'website.view'],
                ['key' => 'website-video-gallery', 'title' => 'Video Gallery', 'icon' => 'video', 'module' => 'website', 'route' => 'website-admin.video-gallery', 'pattern' => 'website-admin.video-gallery', 'can' => 'website.view'],
                ['key' => 'website-downloads', 'title' => 'Downloads', 'icon' => 'download', 'module' => 'website', 'route' => 'website-admin.downloads', 'pattern' => 'website-admin.downloads', 'can' => 'website.view'],
                ['key' => 'website-content', 'title' => 'Content Library', 'icon' => 'book-open', 'module' => 'website', 'route' => 'website-content.index', 'pattern' => 'website-content.index', 'can' => 'website.view'],
                ['key' => 'website-menus', 'title' => 'Menus', 'icon' => 'menu', 'module' => 'website', 'route' => 'website-menus.index', 'pattern' => 'website-menus.*', 'can' => 'website.menus'],
                ['key' => 'website-media', 'title' => 'Media', 'icon' => 'eye', 'module' => 'website', 'route' => 'media.index', 'pattern' => 'media.*', 'can' => 'website.media'],
                ['key' => 'website-create-page', 'title' => 'Create New Page', 'icon' => 'plus', 'module' => 'website', 'route' => 'website-admin.create-page', 'pattern' => 'website-admin.create-page', 'can' => 'website.view'],
                ['key' => 'website-sidebar', 'title' => 'Sidebar', 'icon' => 'layout', 'module' => 'website', 'route' => 'website-admin.sidebar', 'pattern' => 'website-admin.sidebar', 'can' => 'website.view'],
            ],
        ],
        [
            'label' => 'Administration',
            'items' => [
                ['key' => 'subscription', 'title' => 'Subscription & Modules', 'icon' => 'key', 'module' => 'core', 'route' => 'subscription.index', 'pattern' => 'subscription.*', 'can' => 'subscription.view'],
                ['key' => 'user-access', 'title' => 'User Access', 'icon' => 'user-check', 'module' => 'core', 'route' => 'access.index', 'pattern' => 'access.*', 'can' => 'access.manage'],
                ['key' => 'user-accounts', 'title' => 'User Accounts', 'icon' => 'users', 'module' => 'core', 'route' => 'users.index', 'pattern' => 'users.*', 'can' => 'users.manage'],
                ['key' => 'roles-access', 'title' => 'Roles & Access', 'icon' => 'shield', 'module' => 'core', 'route' => 'roles.index', 'pattern' => 'roles.*', 'can' => 'users.manage'],
                ['key' => 'audit-log', 'title' => 'Audit Log', 'icon' => 'activity', 'module' => 'core', 'route' => 'audit.index', 'pattern' => 'audit.*', 'can' => 'audit.view'],
                ['key' => 'admin-settings', 'title' => 'Admin Settings', 'icon' => 'user-cog', 'module' => 'administration', 'route' => 'admin.index', 'pattern' => 'admin.*|sessions.*|classes.*|sections.*', 'can' => 'settings.view'],
                ['key' => 'portal-accounts', 'title' => 'Portal Accounts', 'icon' => 'user-check', 'module' => 'administration', 'route' => 'portal-accounts.index', 'pattern' => 'portal-accounts.*', 'can' => 'portals.manage'],
                ['key' => 'school-settings', 'title' => 'Settings', 'icon' => 'settings', 'module' => 'administration', 'route' => 'settings.index', 'pattern' => 'settings.*', 'can' => 'settings.view'],
            ],
        ],
    ],

];

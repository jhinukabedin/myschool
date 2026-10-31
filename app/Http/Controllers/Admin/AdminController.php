<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.index', [
            'stats' => [
                'sessions' => AcademicSession::count(),
                'classes' => SchoolClass::count(),
                'students' => Student::count(),
                'staff' => Staff::count(),
                'users' => User::count(),
                'audit_events' => AuditLog::count(),
            ],
            'recentAudit' => AuditLog::with('user')->latest()->limit(8)->get(),
            'latestUsers' => User::with('role')->latest()->limit(5)->get(),
            'session' => current_session(),
        ]);
    }
}

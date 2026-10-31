<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\Mark;
use App\Models\Section;
use App\Models\Staff;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Centralises every rule that decides whether a user may read or change marks.
 *
 * Security is enforced here - never by hiding form fields. Controllers call
 * these methods before any write, so forged request IDs cannot bypass them.
 */
class MarksAuthorizationService
{
    public function activeStaff(User $user): ?Staff
    {
        $staff = $user->staff;

        if (! $staff || $staff->trashed() || $staff->status !== 'active') {
            return null;
        }

        return $staff;
    }

    /**
     * Administrative users may act outside their own assignments, but only when
     * they hold an explicit permission. Every such action is audited by callers.
     */
    public function isAdministrative(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('exams.manage')
            || $user->hasPermission('marks.approve');
    }

    public function assignmentFor(User $user, ExaminationSubject $examSubject, ?Section $section = null): ?TeacherAssignment
    {
        $staff = $this->activeStaff($user);

        if (! $staff) {
            return null;
        }

        $sessionId = $examSubject->examination?->academic_session_id;

        if (! $sessionId) {
            return null;
        }

        return TeacherAssignment::query()
            ->forSession($sessionId)
            ->where('staff_id', $staff->id)
            ->where('class_id', $examSubject->class_id)
            ->where('subject_id', $examSubject->subject_id)
            ->when($section, fn ($query) => $query->where(
                fn ($inner) => $inner->whereNull('section_id')->orWhere('section_id', $section->id)
            ))
            ->effective()
            ->first();
    }

    /**
     * @throws AuthorizationException
     */
    public function authorizeEntry(User $user, ExaminationSubject $examSubject, ?Section $section = null): TeacherAssignment|bool
    {
        if (! $user->hasPermission('marks.enter')) {
            throw new AuthorizationException('You do not have permission to enter marks.');
        }

        if ($this->isAdministrative($user)) {
            return true;
        }

        $assignment = $this->assignmentFor($user, $examSubject, $section);

        if (! $assignment) {
            throw new AuthorizationException('You are not assigned to this class, section and subject for the selected academic session.');
        }

        return $assignment;
    }

    /**
     * An examination whose results are published is locked unless the actor is
     * explicitly allowed to correct finalized marks.
     *
     * @throws ValidationException
     */
    public function ensureExamIsEditable(Examination $exam, User $user): void
    {
        if ($exam->status === 'published' && ! $user->hasPermission('marks.correct')) {
            throw ValidationException::withMessages([
                'examination' => 'Results for this examination are finalized. An authorised administrator must reopen or correct them.',
            ]);
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function ensureStudentEnrolled(ExaminationSubject $examSubject, int $studentId, ?Section $section = null): Enrollment
    {
        $enrollment = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('academic_session_id', $examSubject->examination->academic_session_id)
            ->where('class_id', $examSubject->class_id)
            ->where('status', 'active')
            ->when($section, fn ($query) => $query->where('section_id', $section->id))
            ->first();

        if (! $enrollment) {
            throw new AuthorizationException('The student is not actively enrolled in this class, section and academic session.');
        }

        return $enrollment;
    }

    /**
     * Teachers may not rewrite marks entered by another teacher unless they hold
     * the explicit correction permission. Administrators are exempt.
     *
     * @throws AuthorizationException
     */
    public function ensureCanChangeExistingMark(User $user, Mark $mark): void
    {
        if ($this->isAdministrative($user) || $user->hasPermission('marks.correct')) {
            return;
        }

        if ($mark->entered_by !== null && $mark->entered_by !== $user->id) {
            throw new AuthorizationException('You cannot change marks entered by another teacher.');
        }

        if (in_array($mark->status, ['submitted', 'approved'], true)) {
            throw new AuthorizationException('These marks have been submitted and can only be changed through the authorised correction workflow.');
        }
    }
}

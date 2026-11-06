# Dynamic subject marks distribution

## Architecture

- Reuses `examination_subjects.components` and `marks.component_marks` for exam snapshots and entered component marks.
- `subject_mark_defaults` stores global per-subject defaults; `class_subject_mark_overrides` stores class-specific overrides. Resolution priority is exam snapshot → class override → global default → existing subject full/pass marks.
- The Marks Distribution page is under Exams. Exam create/edit has General Information, Subject & Marks, Exam Schedule and Settings tabs.
- `components[].requires_pass` marks independently required components. Overall percentage grade rules remain separate.

## Historical data safety

- Creating/loading an exam stores resolved full marks, pass marks, components and schedule on its `examination_subjects` rows.
- Updating a reusable default never edits exam snapshots.
- If an exam paper already has marks, distribution changes require a confirmation checkbox and server validation. Exams with published result snapshots are locked against distribution changes.
- Changing an exam's academic session after adding its first exam subject is rejected.

## Validation and tests

- Component entries are numeric, nonnegative, uniquely named and sum to subject full marks. Component pass marks cannot exceed component full marks; overall pass marks cannot exceed subject full marks.
- Added feature tests for resolution fallback, exam snapshots, explicit confirmation for edits after marks, duplicate/sum validation and component-wise pass outcomes.
- Targeted Feature tests passed: `MarksDistributionTest.php` and the existing `ExamComponentTest.php` (5 tests, 56 assertions). PHP lint, Blade compilation, route registration, migration status and production asset build also pass.

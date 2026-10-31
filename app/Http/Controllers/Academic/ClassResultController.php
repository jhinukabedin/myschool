<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Examination;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Services\ExamResultService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassResultController extends Controller
{
    public function __construct(private readonly ExamResultService $results) {}

    public function index(Request $request): View
    {
        return view('academics.results.index', $this->data($request));
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->data($request);

        abort_unless($data['exam'] && $data['class'], 404, 'Choose an examination and class before exporting results.');

        $filename = Str::slug($data['exam']->name.' '.$data['class']->name.' results').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Examination', $data['exam']->name]);
            fputcsv($out, ['Class', $data['class']->name]);
            fputcsv($out, ['Section', $data['section']?->name ?? 'All sections']);
            fputcsv($out, ['Session', $data['session']?->name]);
            fputcsv($out, ['Grading scheme', $data['exam']->gradingScheme?->name ?? 'Institution default']);
            fputcsv($out, ['Status', $data['exam']->result_published_at ? 'Published '.$data['exam']->result_published_at->format('Y-m-d H:i') : 'Unpublished']);
            fputcsv($out, []);
            fputcsv($out, ['Rank', 'Student', 'Admission No', 'Roll', 'Section', 'GPA', 'Grade', 'Total', 'Full total', 'Percentage', 'Result', 'Failed subjects', 'Missing subjects', 'Special statuses']);

            foreach ($data['results'] as $result) {
                fputcsv($out, [
                    $result['rank'],
                    $result['student']->full_name,
                    $result['student']->admission_no,
                    $result['enrollment']->roll_no,
                    $result['enrollment']->section?->name,
                    number_format((float) $result['gpa'], 2),
                    $result['grade'],
                    number_format((float) $result['total'], 2),
                    number_format((float) $result['full_total'], 2),
                    number_format((float) $result['percentage'], 2),
                    $result['result'],
                    $result['failed_subjects'],
                    $result['missing_subjects'],
                    implode(', ', $result['special_statuses']),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Request $request): array
    {
        $session = current_session();
        $exams = Examination::query()
            ->with('gradingScheme')
            ->when($session, fn ($query) => $query->where('academic_session_id', $session->id))
            ->orderByDesc('start_date')
            ->get();
        $exam = $request->filled('exam_id')
            ? $exams->firstWhere('id', $request->integer('exam_id'))
            : $exams->first();
        $class = $request->filled('class_id')
            ? SchoolClass::find($request->integer('class_id'))
            : null;
        $section = $request->filled('section_id') ? Section::find($request->integer('section_id')) : null;

        $results = ($exam && $class)
            ? $this->results->meritList($exam->load('subjects.subject'), $class, $section)
            : collect();

        return [
            'session' => $session,
            'exams' => $exams,
            'exam' => $exam,
            'class' => $class,
            'section' => $section,
            'classes' => SchoolClass::ordered()->get(),
            'sections' => Section::orderBy('name')->get(),
            'results' => $results,
        ];
    }

    public function show(Examination $exam, Student $student): View
    {
        $enrollment = Enrollment::query()
            ->with(['class', 'section', 'academicSession'])
            ->where('student_id', $student->id)
            ->where('academic_session_id', $exam->academic_session_id)
            ->firstOrFail();

        $exam->load(['subjects.subject', 'academicSession']);
        $class = $enrollment->class;
        $result = $this->results->studentResult($exam, $student, $class);

        return view('academics.results.sheet', [
            'exam' => $exam,
            'student' => $student,
            'enrollment' => $enrollment,
            'result' => $result,
            'class' => $class,
        ]);
    }
}

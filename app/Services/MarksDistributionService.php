<?php

namespace App\Services;

use App\Models\ClassSubjectMarkOverride;
use App\Models\Subject;
use App\Models\SubjectMarkDefault;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MarksDistributionService
{
    /** Resolve class override, then global subject default, then subject base marks. */
    public function resolve(int $classId, Subject $subject, ?Collection $classOverrides = null, ?Collection $globalDefaults = null): array
    {
        $override = $classOverrides
            ? $classOverrides->get($classId.'_'.$subject->id)
            : ClassSubjectMarkOverride::query()->where('class_id', $classId)->where('subject_id', $subject->id)->first();

        if ($override) {
            return [...$this->values($override), 'source' => 'Class override'];
        }

        $default = $globalDefaults
            ? $globalDefaults->get($subject->id)
            : SubjectMarkDefault::query()->where('subject_id', $subject->id)->first();
        if ($default) {
            return [...$this->values($default), 'source' => 'Global default'];
        }

        return [
            'full_marks' => (float) $subject->full_marks,
            'pass_marks' => (float) $subject->pass_marks,
            'components' => [],
            'source' => 'Subject default',
        ];
    }

    /** @return array{full_marks: float, pass_marks: float, components: array} */
    public function normalize(array $input): array
    {
        $full = round((float) ($input['full_marks'] ?? 0), 2);
        $pass = round((float) ($input['pass_marks'] ?? 0), 2);
        $components = collect($input['components'] ?? [])
            ->map(fn (array $component) => [
                'name' => trim((string) ($component['name'] ?? '')),
                'full_marks' => round((float) ($component['full_marks'] ?? 0), 2),
                'pass_marks' => round((float) ($component['pass_marks'] ?? 0), 2),
                'requires_pass' => filter_var($component['requires_pass'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->filter(fn (array $component) => $component['name'] !== '' || $component['full_marks'] > 0)
            ->values();

        if ($full <= 0 || $pass < 0 || $pass > $full) {
            throw ValidationException::withMessages(['distribution' => 'Full marks must be positive and pass marks must be between zero and full marks.']);
        }

        if ($components->contains(fn (array $component) => $component['name'] === ''
            || $component['full_marks'] <= 0
            || $component['pass_marks'] < 0
            || $component['pass_marks'] > $component['full_marks']
            || ($component['requires_pass'] && $component['pass_marks'] <= 0))) {
            throw ValidationException::withMessages(['distribution' => 'Each component needs a unique name, positive full marks, and valid pass marks.']);
        }

        $names = $components->map(fn ($component) => mb_strtolower($component['name']));
        if ($names->unique()->count() !== $names->count()) {
            throw ValidationException::withMessages(['distribution' => 'Component names cannot be duplicated within a subject.']);
        }

        if ($components->isNotEmpty() && abs(round((float) $components->sum('full_marks'), 2) - $full) > 0.001) {
            throw ValidationException::withMessages(['distribution' => "Component full marks must add up to the subject's full marks ({$full})."]);
        }

        return ['full_marks' => $full, 'pass_marks' => $pass, 'components' => $components->all() ?: []];
    }

    private function values(SubjectMarkDefault|ClassSubjectMarkOverride $model): array
    {
        return [
            'full_marks' => (float) $model->full_marks,
            'pass_marks' => (float) $model->pass_marks,
            'components' => array_values($model->components ?? []),
        ];
    }
}

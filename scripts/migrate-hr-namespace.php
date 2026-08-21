<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$models = [
    'ApplicationStageHistory', 'Attendance', 'Candidate', 'Department', 'Designation',
    'Employee', 'EmployeeSalary', 'EmployeeSalaryComponent', 'EmployeeSalaryRevision',
    'EmploymentRecord', 'Interview', 'InterviewFeedback', 'InterviewMeeting',
    'InterviewMeetingProviderSetting', 'JobApplication', 'JobOffer', 'JobOpening',
    'LeaveBalance', 'LeaveRequest', 'LeaveType', 'OvertimePolicy', 'PayrollItem',
    'PayrollItemLine', 'PayrollPeriod', 'PayrollRun', 'PerformanceCycle',
    'PerformanceReview', 'PublicHoliday', 'RecruitmentActivity', 'RecruitmentStage',
    'TaxTable', 'TaxTableBand', 'WorkLocation', 'WorkSchedule', 'WorkScheduleDay',
];

$factories = [
    'AttendanceFactory', 'CandidateFactory', 'DepartmentFactory', 'DesignationFactory',
    'EmployeeFactory', 'EmployeeSalaryFactory', 'EmployeeSalaryRevisionFactory',
    'InterviewFactory', 'InterviewFeedbackFactory', 'InterviewMeetingFactory',
    'JobApplicationFactory', 'JobOfferFactory', 'JobOpeningFactory',
    'LeaveRequestFactory', 'LeaveTypeFactory', 'PayrollItemFactory', 'PayrollRunFactory',
    'PerformanceCycleFactory', 'PerformanceReviewFactory', 'RecruitmentStageFactory',
    'WorkLocationFactory',
];

@mkdir("{$root}/app/Models/HR", 0777, true);
@mkdir("{$root}/database/factories/HR", 0777, true);

foreach ($models as $model) {
    $src = "{$root}/app/Models/Tenant/{$model}.php";
    $dest = "{$root}/app/Models/HR/{$model}.php";

    if (is_file($src)) {
        rename($src, $dest);
        echo "Moved model {$model}\n";
    }
}

foreach ($factories as $factory) {
    $src = "{$root}/database/factories/Tenant/{$factory}.php";
    $dest = "{$root}/database/factories/HR/{$factory}.php";

    if (is_file($src)) {
        rename($src, $dest);
        echo "Moved factory {$factory}\n";
    }
}

$replacements = [];

foreach ($models as $model) {
    $replacements["App\\Models\\Tenant\\{$model}"] = "App\\Models\\HR\\{$model}";
}

foreach ($factories as $factory) {
    $replacements["Database\\Factories\\Tenant\\{$factory}"] = "Database\\Factories\\HR\\{$factory}";
}

foreach (glob("{$root}/app/Models/HR/*.php") as $path) {
    $content = file_get_contents($path);
    $content = str_replace('namespace App\Models\Tenant;', 'namespace App\Models\HR;', $content);
    $content = str_replace('Database\\Factories\\Tenant\\', 'Database\\Factories\\HR\\', $content);

    if (str_contains($content, 'User::') && ! str_contains($content, 'use App\Models\Tenant\User;')) {
        $content = preg_replace(
            '/(namespace App\\\\Models\\\\HR;\r?\n\r?\n)/',
            "$1use App\\Models\\Tenant\\User;\n",
            $content,
            1,
        );
    }

    if (str_contains($content, 'JournalEntry::') && ! str_contains($content, 'use App\Models\Tenant\JournalEntry;')) {
        $content = preg_replace(
            '/(namespace App\\\\Models\\\\HR;\r?\n\r?\n)/',
            "$1use App\\Models\\Tenant\\JournalEntry;\n",
            $content,
            1,
        );
    }

    file_put_contents($path, $content);
}

foreach (glob("{$root}/database/factories/HR/*.php") as $path) {
    $content = file_get_contents($path);
    $content = str_replace('namespace Database\Factories\Tenant;', 'namespace Database\Factories\HR;', $content);

    foreach ($models as $model) {
        $content = str_replace("App\\Models\\Tenant\\{$model}", "App\\Models\\HR\\{$model}", $content);
    }

    file_put_contents($path, $content);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();

    if (str_contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)
        || str_contains($path, DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR)
        || str_contains($path, DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR)) {
        continue;
    }

    $content = file_get_contents($path);
    $updated = strtr($content, $replacements);

    if ($updated !== $content) {
        file_put_contents($path, $updated);
    }
}

echo "Done.\n";

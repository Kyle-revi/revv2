<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAttemptGrant;
use App\Models\ClassModel;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ModuleSubpart;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\TestBankQuestion;
use App\Models\User;
use App\Services\AiSettingsResolver;
use App\Services\CloudflareAI;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Smalot\PdfParser\Parser;

class ClassManagerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function canManageClassStudents(ClassModel $class): bool
    {
        $actor = Auth::user();

        return $actor !== null
            && ($class->created_by === $actor->id || in_array($actor->role, ['admin', 'superadmin'], true));
    }

    private function normalizeExtractedContext(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_substr($text, 0, 8000)));
    }

    private function extractPdfContext(UploadedFile $file): ?string
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            return null;
        }

        $parser = new Parser;
        $pdf = $parser->parseFile($realPath);

        return $this->normalizeExtractedContext($pdf->getText());
    }

    private function extractTxtContext(UploadedFile $file): ?string
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            return null;
        }

        $text = File::get($realPath);

        return $this->normalizeExtractedContext($text);
    }

    private function extractDocxContext(UploadedFile $file): ?string
    {
        if (! class_exists('ZipArchive')) {
            return null;
        }

        $realPath = $file->getRealPath();
        if ($realPath === false) {
            return null;
        }

        $zip = new \ZipArchive;
        if ($zip->open($realPath) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return null;
        }

        $text = strip_tags(str_replace(['</w:p>', '</w:tr>', '</w:tab>'], ["\n", "\n", "\t"], $xml));

        return $this->normalizeExtractedContext(html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function extractContextFileText(UploadedFile $file): ?string
    {
        return match (strtolower((string) $file->getClientOriginalExtension())) {
            'pdf' => $this->extractPdfContext($file),
            'doc' => $this->extractPdfContext($file),
            'txt' => $this->extractTxtContext($file),
            'docx' => $this->extractDocxContext($file),
            default => null,
        };
    }

    private function normalizeQuizQuestionsPayload(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        $questionCandidates = [];

        if (array_is_list($decoded)) {
            $questionCandidates = $decoded;
        } elseif (isset($decoded['questions']) && is_array($decoded['questions'])) {
            $questionCandidates = $decoded['questions'];
        } elseif (isset($decoded['items']) && is_array($decoded['items'])) {
            $questionCandidates = $decoded['items'];
        } else {
            $questionCandidates = [$decoded];
        }

        $normalized = [];
        foreach ($questionCandidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $questionText = trim((string) ($candidate['question'] ?? $candidate['question_text'] ?? $candidate['text'] ?? ''));
            if ($questionText === '') {
                continue;
            }

            $evidence = trim((string) ($candidate['evidence'] ?? $candidate['evidence_text'] ?? ''));
            $questionType = strtolower(trim((string) ($candidate['question_type'] ?? $candidate['type'] ?? '')));
            if ($questionType !== '' && ! in_array($questionType, ['what', 'why', 'how'], true)) {
                $questionType = '';
            }

            $optionsRaw = $candidate['options'] ?? null;
            if (! is_array($optionsRaw)) {
                continue;
            }

            if (array_is_list($optionsRaw)) {
                if (count($optionsRaw) < 4) {
                    continue;
                }

                $options = [
                    'A' => trim((string) ($optionsRaw[0] ?? '')),
                    'B' => trim((string) ($optionsRaw[1] ?? '')),
                    'C' => trim((string) ($optionsRaw[2] ?? '')),
                    'D' => trim((string) ($optionsRaw[3] ?? '')),
                ];
            } else {
                $options = [
                    'A' => trim((string) ($optionsRaw['A'] ?? $optionsRaw['a'] ?? '')),
                    'B' => trim((string) ($optionsRaw['B'] ?? $optionsRaw['b'] ?? '')),
                    'C' => trim((string) ($optionsRaw['C'] ?? $optionsRaw['c'] ?? '')),
                    'D' => trim((string) ($optionsRaw['D'] ?? $optionsRaw['d'] ?? '')),
                ];
            }

            if (in_array('', $options, true)) {
                continue;
            }

            $correctRaw = strtoupper(trim((string) ($candidate['correct'] ?? $candidate['answer'] ?? $candidate['correct_option'] ?? '')));

            // Handle pipe-separated correct answers (e.g., "A|B" -> take first valid one)
            $correctParts = explode('|', $correctRaw);
            $correctLetter = null;
            foreach ($correctParts as $part) {
                $part = trim($part);
                if (in_array($part, ['A', 'B', 'C', 'D'], true)) {
                    $correctLetter = $part;
                    break;
                }
            }

            if ($correctLetter === null) {
                continue;
            }

            $normalized[] = [
                'question' => $questionText,
                'options' => $options,
                'correct' => $correctLetter,
                'evidence' => $evidence,
                'question_type' => $questionType !== '' ? $questionType : null,
            ];
        }

        return $normalized;
    }

    private function extractLikelyJsonBlock(string $raw): ?string
    {
        $startArray = strpos($raw, '[');
        $startObject = strpos($raw, '{');

        if ($startArray === false && $startObject === false) {
            return null;
        }

        if ($startArray === false) {
            $start = $startObject;
            $opening = '{';
            $closing = '}';
        } elseif ($startObject === false || $startArray < $startObject) {
            $start = $startArray;
            $opening = '[';
            $closing = ']';
        } else {
            $start = $startObject;
            $opening = '{';
            $closing = '}';
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($raw);

        for ($i = $start; $i < $length; $i++) {
            $char = $raw[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === $opening) {
                $depth++;
            } elseif ($char === $closing) {
                $depth--;
                if ($depth === 0) {
                    return substr($raw, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    private function parseAiQuizQuestions(string $raw): array
    {
        $cleaned = trim($raw);

        $attempts = [$cleaned];

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $cleaned, $blockMatch) === 1) {
            $attempts[] = trim((string) ($blockMatch[1] ?? ''));
        }

        $jsonBlock = $this->extractLikelyJsonBlock($cleaned);
        if ($jsonBlock !== null) {
            $attempts[] = $jsonBlock;
        }

        foreach ($attempts as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $decoded = json_decode($candidate, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            $normalized = $this->normalizeQuizQuestionsPayload($decoded);
            if (! empty($normalized)) {
                return $normalized;
            }
        }

        return [];
    }

    protected function cleanExtractedPdfText(string $rawText): string
    {
        // 1. Strip Bibliography / References section at the end of academic papers
        $cleaned = preg_replace('/\n\s*(?:\[?\d+\]?\.?\s*)?(?:REFERENCES|BIBLIOGRAPHY|WORKS CITED)\s*[:\n].*$/is', '', $rawText);
        if (! is_string($cleaned) || mb_strlen(trim($cleaned)) < 50) {
            $cleaned = $rawText;
        }

        // 2. Remove common journal headers, footers, ISSNs, URLs, emails, watermarks
        $noisePatterns = [
            '/(?:International\s+Journal\s+for\s+[^\n\r]+)/iu',
            '/IJFMR\w*/iu',
            '/E?-?ISSN:\s*[\d\-]+/iu',
            '/ISBN:\s*[\d\-]+/iu',
            '/Volume\s+\d+,\s*Issue\s+\d+[^,\n\r]*/iu',
            '/Website:\s*www\.[^\s]+/iu',
            '/Email:\s*[\w\.\-]+@[\w\.\-]+/iu',
            '/https?:\/\/[^\s]+/iu',
            '/www\.[^\s]+/iu',
            '/\bPage\s+\d+(?:\s+of\s+\d+)?\b/iu',
            '/\b\d+\s*\|\s*P\s*a\s*g\s*e\b/iu',
            '/©\s*\d{4}[^\n\r]*/u',
            '/All\s+Rights?\s+Reserved[^\n\r]*/iu',
        ];

        foreach ($noisePatterns as $pattern) {
            $cleaned = preg_replace($pattern, ' ', (string) $cleaned);
        }

        return trim(preg_replace('/\s+/', ' ', (string) $cleaned));
    }

    protected function sliceDocumentIntoSections(string $text, int $desiredSections = 6): array
    {
        $desiredSections = max(1, $desiredSections);
        $textLength = mb_strlen($text);

        if ($textLength <= 1200 || $desiredSections <= 1) {
            return [$text];
        }

        $targetChunkSize = (int) ceil($textLength / $desiredSections);
        $targetChunkSize = max(350, min(2500, $targetChunkSize));

        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($sentences)) {
            return [$text];
        }

        $sections = [];
        $currentSection = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($currentSection) + mb_strlen($sentence) > $targetChunkSize && mb_strlen($currentSection) >= 300) {
                $sections[] = trim($currentSection);
                $currentSection = $sentence;
            } else {
                $currentSection .= ($currentSection !== '' ? ' ' : '').$sentence;
            }
        }

        if (trim($currentSection) !== '') {
            $sections[] = trim($currentSection);
        }

        return ! empty($sections) ? $sections : [$text];
    }

    private function getStemEchoThreshold(?string $questionType): float
    {
        return 95.0;
    }

    private function getCrossBatchDuplicateThreshold(): float
    {
        return 75.0;
    }

    private function normalizeForComparison(mixed $value): string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', mb_strtolower((string) $value)));

        return preg_replace('/[^\pL\pN\s]/u', '', $normalized) ?? $normalized;
    }

    private function normalizeQuestionStemForDeduplication(string $text): string
    {
        $normalized = mb_strtolower(trim($text));
        $pattern = '/^(what\s+is\s+the|what\s+are\s+the|why\s+does\s+the|why\s+is\s+the|how\s+does\s+the|how\s+is\s+the|which\s+of\s+the\s+following\s+best\s+describes|which\s+of\s+the\s+following\s+describes|which\s+of\s+the\s+following\s+is|which\s+of\s+the\s+following|according\s+to\s+the|according\s+to|based\s+on\s+the\s+text|in\s+terms\s+of)\s+/u';
        $normalized = preg_replace($pattern, '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^\pL\pN\s]/u', '', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized));
    }

    private function isGroundedInSource(string $evidence, string $sourceText): bool
    {
        $normalizedEvidence = $this->normalizeForComparison($evidence);
        $normalizedSource = $this->normalizeForComparison($sourceText);

        if ($normalizedEvidence === '' || $normalizedSource === '') {
            return false;
        }

        // Direct match
        if (str_contains($normalizedSource, $normalizedEvidence)) {
            return true;
        }

        // Token-based presence: extract significant words (>= 3 chars)
        $words = array_filter(
            explode(' ', $normalizedEvidence),
            fn (string $w): bool => mb_strlen($w) >= 3
        );

        if (empty($words)) {
            return true;
        }

        $found = 0;
        foreach ($words as $w) {
            if (str_contains($normalizedSource, $w)) {
                $found++;
            }
        }

        // Grounded if at least 35% of the significant words in the evidence are present in the source
        return ($found / count($words)) >= 0.35;
    }

    private function shouldReplaceExistingQuestions(int $requested, int $generated, float $minimumAcceptanceRatio = 0.8): bool
    {
        if ($requested <= 0) {
            return false;
        }

        return $generated >= (int) ceil($requested * $minimumAcceptanceRatio);
    }

    private function deduplicateQuestionBatch(array $questions): array
    {
        $deduplicated = [];
        $removed = [];

        foreach ($questions as $index => $question) {
            $questionText = (string) ($question['question'] ?? '');
            $normalizedQuestion = $this->normalizeForComparison($questionText);
            $stemCleanedQuestion = $this->normalizeQuestionStemForDeduplication($questionText);
            $questionType = (string) ($question['question_type'] ?? 'what');

            if ($normalizedQuestion === '') {
                $deduplicated[] = $question;

                continue;
            }

            $isDuplicate = false;
            foreach ($deduplicated as $existingIndex => $existingQuestion) {
                $existingText = (string) ($existingQuestion['question'] ?? '');
                $normalizedExisting = $this->normalizeForComparison($existingText);
                $stemCleanedExisting = $this->normalizeQuestionStemForDeduplication($existingText);

                if ($normalizedExisting === '') {
                    continue;
                }

                // 1. Direct similar_text on full normalized string
                similar_text($normalizedExisting, $normalizedQuestion, $similarity);

                // 2. Similar_text on prefix-stripped stem
                similar_text($stemCleanedExisting, $stemCleanedQuestion, $stemSimilarity);

                // 3. Jaccard word-level overlap
                $words1 = array_unique(array_filter(explode(' ', $normalizedExisting), fn ($w) => mb_strlen($w) >= 3));
                $words2 = array_unique(array_filter(explode(' ', $normalizedQuestion), fn ($w) => mb_strlen($w) >= 3));
                $jaccard = 0.0;
                if (! empty($words1) && ! empty($words2)) {
                    $intersection = count(array_intersect($words1, $words2));
                    $union = count(array_unique(array_merge($words1, $words2)));
                    $jaccard = $union > 0 ? ($intersection / $union) * 100 : 0;
                }

                // 4. Correct Answer text comparison
                $ans1 = mb_strtolower(trim((string) ($existingQuestion['options'][$existingQuestion['correct'] ?? ''] ?? '')));
                $ans2 = mb_strtolower(trim((string) ($question['options'][$question['correct'] ?? ''] ?? '')));
                $hasAnswer = ($ans1 !== '' && $ans2 !== '');
                $ansSimilarity = 0.0;
                if ($hasAnswer) {
                    similar_text($ans1, $ans2, $ansSimilarity);
                }

                // 5. Shared Options Pool Overlap check
                $opts1 = array_filter(array_map('mb_strtolower', array_map('trim', array_values($existingQuestion['options'] ?? []))));
                $opts2 = array_filter(array_map('mb_strtolower', array_map('trim', array_values($question['options'] ?? []))));
                $sharedOptions = 0;
                foreach ($opts1 as $o1) {
                    foreach ($opts2 as $o2) {
                        if ($o1 !== '' && $o2 !== '') {
                            similar_text($o1, $o2, $optSim);
                            if ($optSim >= 85.0) {
                                $sharedOptions++;
                                break;
                            }
                        }
                    }
                }

                // 6. Token containment check for core question stem
                $isContained = false;
                $shorterStem = mb_strlen($stemCleanedExisting) <= mb_strlen($stemCleanedQuestion) ? $stemCleanedExisting : $stemCleanedQuestion;
                $longerStem = mb_strlen($stemCleanedExisting) <= mb_strlen($stemCleanedQuestion) ? $stemCleanedQuestion : $stemCleanedExisting;
                if (mb_strlen($shorterStem) >= 15 && str_contains($longerStem, $shorterStem)) {
                    $isContained = true;
                } else {
                    $wordsShorter = mb_strlen($stemCleanedExisting) <= mb_strlen($stemCleanedQuestion) ? $words1 : $words2;
                    $wordsLonger = mb_strlen($stemCleanedExisting) <= mb_strlen($stemCleanedQuestion) ? $words2 : $words1;
                    if (count($wordsShorter) >= 3 && count(array_diff($wordsShorter, $wordsLonger)) === 0) {
                        $isContained = true;
                    }
                }
                $maxStemSim = max($similarity, $stemSimilarity);
                $conditionFired = null;

                // Condition 1: Nearly exact question stem (>= 92%)
                if ($maxStemSim >= 92.0) {
                    $isDuplicate = true;
                    $conditionFired = 1;
                }
                // Condition 2: Highly similar question stem (>= 75%) AND similar answer or options (>= 40%)
                elseif ($maxStemSim >= 75.0 && (! $hasAnswer || $ansSimilarity >= 40.0)) {
                    $isDuplicate = true;
                    $conditionFired = 2;
                }
                // Condition 3: Same or near-identical correct answer (>= 80%) with phrase length >= 12 chars or topic overlap
                elseif ($ansSimilarity >= 80.0 && (mb_strlen($ans1) >= 12 || $maxStemSim >= 25.0 || $jaccard >= 20.0)) {
                    $isDuplicate = true;
                    $conditionFired = 3;
                }
                // Condition 4: High options pool overlap (3 or more options are identical) AND some topical corroboration
                elseif ($sharedOptions >= 3 && ($maxStemSim >= 25.0 || $jaccard >= 15.0)) {
                    $isDuplicate = true;
                    $conditionFired = 4;
                }
                // Condition 5: Rephrased question on same topic (both stem similarity and jaccard) AND matching correct answer (>= 75%)
                elseif ($ansSimilarity >= 75.0 && $maxStemSim >= 45.0 && $jaccard >= 30.0) {
                    $isDuplicate = true;
                    $conditionFired = 5;
                }
                // Condition 6: Stem containment / subset
                elseif ($isContained) {
                    $isDuplicate = true;
                    $conditionFired = 6;
                }

                if ($isDuplicate) {
                    $removed[] = [
                        'dropped_index' => $index,
                        'kept_index' => $existingIndex,
                        'dropped_question' => $questionText,
                        'kept_question' => $existingText,
                        'similarity' => round($maxStemSim, 2),
                        'jaccard' => round($jaccard, 2),
                        'ans_similarity' => round($ansSimilarity, 2),
                        'question_type' => $questionType,
                        'condition_fired' => $conditionFired,
                    ];
                    break;
                }
            }

            if (! $isDuplicate) {
                $deduplicated[] = $question;
            }
        }

        return [
            'questions' => $deduplicated,
            'duplicates' => $removed,
        ];
    }

    private function aiQuestionRejectionReason(mixed $question, array $choiceLetters, ?string $questionType = null, ?string $sourceText = null): ?string
    {
        if (! is_array($question)
            || ! isset($question['question'], $question['options'], $question['correct'])
            || ! is_string($question['question'])
            || ! is_string($question['correct'])
            || ! is_array($question['options'])
            || count($question['options']) !== count($choiceLetters)
            || ! in_array(strtoupper($question['correct']), $choiceLetters, true)) {
            return 'invalid_structure';
        }

        $normalize = static fn (mixed $value): string => trim((string) preg_replace('/\s+/u', ' ', mb_strtolower((string) $value)));
        $normalizedQuestion = $normalize($question['question']);
        $normalizedOptions = array_map($normalize, array_values($question['options']));

        if ($normalizedQuestion === ''
            || in_array('', $normalizedOptions, true)) {
            return 'empty_question_or_option';
        }

        if (count($normalizedOptions) !== count(array_unique($normalizedOptions))) {
            return 'duplicate_options';
        }

        if ($sourceText !== null) {
            $evidence = trim((string) ($question['evidence'] ?? $question['evidence_text'] ?? ''));
            if ($evidence === '' || ! $this->isGroundedInSource($evidence, $sourceText)) {
                return 'ungrounded';
            }
        }

        $similarityThreshold = $this->getStemEchoThreshold($questionType);

        if (mb_strlen($normalizedQuestion) >= 20) {
            foreach ($normalizedOptions as $option) {
                similar_text($normalizedQuestion, $option, $similarity);
                if ($similarity > $similarityThreshold) {
                    return 'stem_echo';
                }
            }
        }

        return null;
    }

    private function isCleanAiQuestion(mixed $question, array $choiceLetters, ?string $questionType = null, ?string $sourceText = null): bool
    {
        if ($this->aiQuestionRejectionReason($question, $choiceLetters, $questionType, $sourceText) !== null) {
            return false;
        }

        return true;
    }

    /**
     * Show manage classes / search page.
     */
    public function index()
    {
        $user = Auth::user();

        $classes = match ($user->role) {
            'admin' => ClassModel::query()
                ->withCount('students')
                ->orderBy('classes.created_at', 'desc')
                ->get(['id', 'name', 'code', 'school_year', 'description']),

            'teacher' => ClassModel::query()
                ->where('created_by', $user->id)
                ->withCount('students')
                ->orderBy('classes.created_at', 'desc')
                ->get(['id', 'name', 'code', 'school_year', 'description']),

            default => $user->classes()
                ->select('classes.id', 'classes.name', 'classes.code', 'classes.school_year', 'classes.description')
                ->withCount('students')
                ->orderBy('class_user.created_at', 'desc') // explicit pivot ordering
                ->get(),
        };

        return view('pages.teacher.manageclass', compact('classes'));
    }

    // Keep the rest of your controller methods unchanged.

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50|unique:classes,code',
            'school_year' => 'nullable',
            'year_level' => 'nullable|integer|min:1|max:4',
            'description' => 'nullable|string|max:500',
        ]);

        $schoolYearInput = $request->input('school_year');
        $cleanSchoolYear = null;
        if (! empty($schoolYearInput)) {
            if (preg_match('/\b(19\d\d|20\d\d)\b/', (string) $schoolYearInput, $matches)) {
                $cleanSchoolYear = (int) $matches[1];
            }
        }

        ClassModel::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'school_year' => $cleanSchoolYear,
            'year_level' => $validated['year_level'] ?? null,
            'description' => $validated['description'] ?? null,
            'program' => Auth::user()?->program ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('manageclass')
            ->with('success', 'Class created successfully!');
    }

    public function destroy(ClassModel $class)
    {
        // Verify the teacher owns this class
        if ($class->created_by !== Auth::id() && ! Auth::user()->isAdmin()) {
            return redirect()->route('manageclass')
                ->with('error', 'You do not have permission to delete this class.');
        }

        $studentCount = $class->users()->where('role', 'student')->count();
        $className = $class->name;

        // Delete related records first (cascade)
        $class->modules()->delete();
        $class->users()->detach();
        $class->delete();

        $message = $studentCount > 0
            ? "Class '{$className}' and all its data (including {$studentCount} enrolled students) have been deleted."
            : "Class '{$className}' has been deleted.";

        return redirect()->route('manageclass')
            ->with('success', $message);
    }

    public function searchStudents(Request $request)
    {
        $term = trim($request->input('q', $request->input('term', '')));

        // Start searching from 1 character for instant feel
        if (strlen($term) < 1) {
            return response()->json(['results' => []]);
        }

        $users = User::query()
            ->where('role', 'student')
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('idnumber', 'like', "%{$term}%");
            })
            ->select('id', 'name', 'idnumber')
            ->limit(15)
            ->get()
            ->map(function ($user) {
                // Display only name + ID (no email for teachers)
                $display = $user->name
                    ? "{$user->name} (ID: {$user->idnumber})"
                    : "ID: {$user->idnumber}";

                return [
                    'id' => $user->id,
                    'text' => $display,
                ];
            });

        return response()->json(['results' => $users]);

    }

    public function addStudents(Request $request, ClassModel $class)
    {
        $actor = Auth::user();

        if ($class->created_by !== $actor->id && $actor->role !== 'admin') {
            abort(403, 'You are not authorized to add students to this class.');
        }

        $validated = $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'student')),
            ],
        ]);

        $class->users()->syncWithoutDetaching($validated['student_ids']);

        return redirect()->back()
            ->with('success', count($validated['student_ids']).' student(s) added to '.$class->name);
    }

    /**
     * Render the user search page (HTML).
     */
    public function searchPage()
    {
        $user = Auth::user();

        $classes = match ($user->role) {
            'admin' => ClassModel::query()->latest()->get(['id', 'name']),
            'teacher' => ClassModel::query()->where('created_by', $user->id)->latest()->get(['id', 'name']),
            default => $user->classes()->select('classes.id', 'classes.name')->latest('classes.created_at')->get(),
        };

        return view('pages.users.search', compact('classes')); // ← correct
    }

    /**
     * Generic user search used by the chat UI.
     * Visibility is role-based:
     * - Admin can search all users.
     * - Others can only search users that are connected through at least one shared class.
     *
     * Query params:
     * - q|term: free text (name or idnumber)
     * - role (optional): filter by exact role
     * - class_id (optional): narrow results to a specific class
     */
    public function searchUsers(Request $request)
    {
        $user = Auth::user();

        $term = trim($request->input('q', $request->input('term', '')));
        $role = $request->input('role');        // optional: filter by exact role
        $classId = $request->input('class_id'); // optional: still allow narrowing to a specific class

        if ($term !== '' && strlen($term) < 1) {
            return response()->json(['results' => []]);
        }

        $query = User::query()
            ->select(['id', 'idnumber', 'name', 'role', 'email', 'program']);

        // Role filter (if provided)
        if ($role) {
            $query->where('role', $role);
        }

        // Search term filter
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('idnumber', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        // ========================
        // CLASS FILTER (optional)
        // ========================
        if ($classId) {
            // Verify class exists
            $classExists = ClassModel::query()->where('id', $classId)->exists();
            if (! $classExists) {
                return response()->json(['results' => []]);
            }

            // Restrict results to users who are either:
            // - Enrolled in the class, OR
            // - Created the class
            $enrolledIds = DB::table('class_user')
                ->where('class_id', $classId)
                ->pluck('user_id');

            $creatorIds = ClassModel::query()
                ->where('id', $classId)
                ->pluck('created_by');

            $allowedIds = $enrolledIds->merge($creatorIds)->unique()->values();

            $query->whereIn('id', $allowedIds);

            $scopeClassIds = collect([$classId]);
        } else {
            $scopeClassIds = null;   // No class restriction → show all users
        }

        // ========================
        // Execute query
        // ========================
        $users = $query
            ->limit(15)
            ->get()
            ->map(function (User $u) use ($scopeClassIds) {
                $displayName = $u->name ?: $u->idnumber;

                // Determine which classes to show for this user in the result
                $classQuery = ClassModel::query()
                    ->where(function ($q) use ($u) {
                        $q->where('created_by', $u->id)
                            ->orWhereHas('users', fn ($uq) => $uq->where('users.id', $u->id));
                    });

                if ($scopeClassIds) {
                    $classQuery->whereIn('id', $scopeClassIds->all());
                }

                $classNames = $classQuery
                    ->orderBy('name')
                    ->pluck('name')
                    ->filter()
                    ->values();

                $classCount = $classNames->count();
                $classNamesShort = $classNames->take(5)->implode(', ');
                if ($classCount > 5) {
                    $classNamesShort .= ', ...';
                }

                return [
                    'id' => $u->id,
                    'text' => "{$displayName} ({$u->role}) - {$u->idnumber}".($u->program ? " [{$u->program}]" : ''),
                    'role' => $u->role,
                    'program' => $u->program,
                    'name' => $u->name,
                    'idnumber' => $u->idnumber,
                    'class_count' => $classCount,
                    'class_names' => $classNamesShort,
                ];
            });

        return response()->json(['results' => $users->values()]);
    }

    public function removeStudent(ClassModel $class, User $student)
    {
        abort_unless($this->canManageClassStudents($class), 403, 'You are not authorized to remove students from this class.');

        abort_unless($student->role === 'student', 422, 'Only student accounts can be removed from classes.');

        $class->users()->detach($student->id);

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $student->idnumber.' removed from '.$class->name,
            ]);
        }

        return redirect()->back()
            ->with('success', $student->idnumber.' removed from '.$class->name);
    }

    public function generateInvite(ClassModel $class)
    {
        $url = URL::temporarySignedRoute(
            'class.join',
            now()->addDays(7),
            ['class' => $class->id]
        );

        return response()->json([
            'success' => true,
            'url' => $url,
            'message' => 'Invite link generated (valid for 7 days)',
        ]);
    }

    public function generate24HourJoinLink(ClassModel $class): JsonResponse
    {
        if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403);
        }

        $url = URL::temporarySignedRoute(
            'class.join.permanent',
            now()->addHours(24),
            ['class' => $class->id]
        );

        return response()->json([
            'success' => true,
            'url' => $url,
            'expires' => now()->addHours(24)->toIso8601String(),
        ]);
    }

    public function getClassStudents(ClassModel $class)
    {
        abort_unless($this->canManageClassStudents($class), 403, 'You are not authorized to view students in this class.');

        $students = $class->students()
            ->select('users.id', 'users.idnumber', 'users.name', 'users.email', 'users.program')
            ->orderBy('users.name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'text' => $user->name ? "{$user->name} (ID: {$user->idnumber})" : "ID: {$user->idnumber}",
                    'program' => $user->program ?? null,
                ];
            });

        return response()->json([
            'students' => $students,
            'count' => $students->count(),
        ]);
    }

    public function storeModule(Request $request)
    {
        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'title' => 'required_if:type,document,assignment,lecture|string|max:150',
            'description' => 'nullable|string',
            'file' => 'required_if:type,document|file|max:102400|mimes:pdf,doc,docx,ppt,pptx,mov',
            'subpart_titles' => 'required_if:type,lecture|array|min:1',
            'subpart_titles.*' => 'required_if:type,lecture|string|max:150',
            'files' => 'required_if:type,lecture|array|min:1',
            'files.*' => 'file|max:102400|mimes:pdf,doc,docx,ppt,pptx,mov',
            'type' => 'required|in:document,quiz,assignment,lecture',
            'visibility' => 'nullable|in:all,selected,except',
            'visible_user_ids' => 'nullable|array',
            'visible_user_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validated['type'] === 'lecture'
            && count($validated['subpart_titles']) !== count($request->file('files', []))) {
            abort(422, 'Each Lecture content file must have a title.');
        }

        $class = ClassModel::findOrFail($validated['class_id']);

        if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403, 'You are not authorized to upload modules to this class.');
        }

        $visibility = $validated['visibility'] ?? 'all';
        $visibleUserIds = $validated['visible_user_ids'] ?? [];

        if (in_array($visibility, ['selected', 'except']) && ! empty($visibleUserIds)) {
            $enrolledIds = $class->users()->pluck('users.id')->toArray();
            $visibleUserIds = array_values(array_intersect($visibleUserIds, $enrolledIds));
        }

        $filePath = null;

        // Handle file upload for legacy document type only. Lectures store content below the parent.
        if ($validated['type'] === 'document' && $request->hasFile('file')) {
            $file = $request->file('file');
            $extension = strtolower($file->extension());

            // Create unique filename
            $uniqueName = time().'_'.Str::random(8).'.'.$extension;
            $subPath = "modules/{$class->id}";
            $filePath = $file->storeAs($subPath, $uniqueName, 'public');
            $fullPath = storage_path('app/public/'.$filePath);

            // Ensure directory exists
            $dir = storage_path('app/public/'.$subPath);
            if (! file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            \Log::info('File stored', [
                'path' => $fullPath,
                'size' => filesize($fullPath),
                'mime' => mime_content_type($fullPath),
                'extension' => $extension,
            ]);
        }

        // Create the module (quiz has no file)
        $fileType = null;
        if ($filePath) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $fileType = match ($ext) {
                'pdf' => 'pdf',
                'ppt' => 'ppt',
                'pptx' => 'pptx',
                'docx' => 'docx',
                'mov' => 'mov',
                default => null,
            };
        }

        $module = Module::create([
            'class_id' => $validated['class_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'is_quiz' => $validated['type'] === 'quiz',
            'is_assignment' => $validated['type'] === 'assignment',
            'is_lecture' => $validated['type'] === 'lecture',
            'visibility' => $visibility,
        ]);

        if ($validated['type'] === 'lecture') {
            $firstFilePath = null;
            $firstFileType = null;

            foreach ($request->file('files', []) as $index => $file) {
                $extension = strtolower($file->extension());
                $uniqueName = time().'_'.Str::random(8).'.'.$extension;
                $filePath = $file->storeAs("modules/{$module->id}/subparts", $uniqueName, 'public');
                $fileType = match ($extension) {
                    'pdf' => 'pdf',
                    'ppt' => 'ppt',
                    'pptx' => 'pptx',
                    'docx' => 'docx',
                    'mov' => 'mov',
                    default => null,
                };

                if ($index === 0) {
                    $firstFilePath = $filePath;
                    $firstFileType = $fileType;
                }

                $rawTitle = trim($validated['subpart_titles'][$index]);
                $formattedTitle = preg_match('/^\d+\.\d+/i', $rawTitle)
                    ? $rawTitle
                    : '1.'.($index + 1).' '.$rawTitle;

                ModuleSubpart::create([
                    'module_id' => $module->id,
                    'title' => $formattedTitle,
                    'file_path' => $filePath,
                    'file_type' => $fileType,
                    'order' => $index + 1,
                ]);
            }

            if ($firstFilePath && ! $module->file_path) {
                $module->update([
                    'file_path' => $firstFilePath,
                    'file_type' => $firstFileType,
                ]);
            }
        }

        // Visibility is set at creation time only — editing is a future feature.
        if (in_array($visibility, ['selected', 'except']) && ! empty($visibleUserIds)) {
            $module->visibleTo()->sync($visibleUserIds);
        }

        // If it's a quiz, redirect to quiz creation page
        if ($validated['type'] === 'quiz') {
            return redirect()->route('quiz.create', $module)
                ->with('success', 'Quiz module created. Now add questions.');
        }

        return response()->json([
            'success' => 'Module added successfully!',
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'file' => $filePath,
            ],
        ]);
    }

    // Student view: modules for a class with progress
    // List of classes the current student is enrolled in
    /**
     * Show the student's list of enrolled classes
     */
    /**
     * Show the current user's list of enrolled classes
     */
    /**
     * Show the current user's list of enrolled classes (role-based access)
     */
    /**
     * Show the current user's list of enrolled classes (role-based view)
     */
    public function myClasses()
    {
        $user = Auth::user();

        // Define allowed roles for this student-like dashboard
        $allowedRoles = ['student', 'psych', 'accountancy', 'educ'];

        // Block teachers and admins (they use /manageclass)
        if (! in_array($user->role, $allowedRoles)) {
            return redirect()->route('manageclass')
                ->with('info', 'Use the management dashboard for your role.');
        }

        // Get enrolled classes
        $classes = $user->classes()
            ->withCount('users as total_students')
            ->withCount('modules as total_modules')
            ->with('creator:id,idnumber,name')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalModules = (int) $classes->sum('total_modules');

        // Student accounts resolve by program track; legacy role-based student tracks still work.
        $track = $user->role === 'student' ? ($user->program ?? 'accountancy') : $user->role;
        $view = "pages.{$track}.my-classes";

        // Safe fallback to an existing student track view.
        if (! view()->exists($view)) {
            $view = 'pages.accountancy.my-classes';
        }

        return view($view, [
            'enrolledClasses' => $classes,
            'totalModules' => $totalModules,
        ]);
    }

    /**
     * Show modules + progress for a specific class (student view)
     */
    public function studentModules(ClassModel $class)
    {
        $user = Auth::user();

        // Security: must be enrolled
        if (! $class->users()->where('user_id', $user->id)->exists()) {
            abort(403, 'You are not enrolled in this class.');
        }

        $modules = $class->modules()
            ->where(function ($query) use ($user) {
                $query->where('visibility', 'all')
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('visibility', 'selected')
                            ->whereHas('visibleTo', fn ($q) => $q->where('users.id', $user->id));
                    })
                    ->orWhere(function ($sub) use ($user) {
                        $sub->where('visibility', 'except')
                            ->whereDoesntHave('visibleTo', fn ($q) => $q->where('users.id', $user->id));
                    });
            })
            ->with([
                'subparts.lessons.progress' => fn ($query) => $query->where('user_id', $user->id),
                'subparts.progress' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->orderByRaw("
                CASE 
                    WHEN quiz_stage = 'pre_test' 
                         OR assessment_purpose IN ('pre_test', 'pre_assessment') 
                         OR LOWER(title) LIKE '%pre-test%' 
                         OR LOWER(title) LIKE '%pre-assessment%' 
                         OR LOWER(title) LIKE '%pre test%' 
                         OR LOWER(title) LIKE '%pre assessment%' 
                         OR (is_quiz = 1 AND (is_formal_assessment = 0 OR is_formal_assessment IS NULL)) THEN 0
                    WHEN is_lecture = 1 OR is_quiz = 0 OR file_path IS NOT NULL THEN 1
                    WHEN quiz_stage = 'post_test' 
                         OR assessment_purpose IN ('post_test', 'post_assessment') 
                         OR LOWER(title) LIKE '%post-test%' 
                         OR LOWER(title) LIKE '%post-assessment%' 
                         OR LOWER(title) LIKE '%post test%' 
                         OR LOWER(title) LIKE '%post assessment%' 
                         OR LOWER(title) LIKE '%final assessment%' 
                         OR LOWER(title) LIKE '%pre-board%' 
                         OR LOWER(title) LIKE '%pre board%' THEN 3
                    ELSE 2
                END
            ")
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $modules->each(function (Module $module): void {
            $module->subparts->each(function ($subpart): void {
                $subpartProgress = $subpart->progress->first();
                $subpart->student_progress = (float) ($subpartProgress?->progress ?? 0);
                $subpart->student_completed = (bool) ($subpartProgress?->completed ?? false);

                $subpart->lessons->each(function ($lesson): void {
                    $lessonProgress = $lesson->progress->first();
                    $lesson->student_progress = (float) ($lessonProgress?->progress ?? 0);
                    $lesson->student_completed = (bool) ($lessonProgress?->completed ?? false);
                });
            });
        });

        $progress = [];
        $completed = [];
        $totalProgress = 0;
        $moduleCount = $modules->count();

        foreach ($modules as $module) {
            $prog = ModuleProgress::firstOrCreate(
                ['module_id' => $module->id, 'user_id' => $user->id],
                ['progress' => 0, 'completed' => false]
            );

            $progress[$module->id] = $prog->progress;
            $completed[$module->id] = (bool) $prog->completed;
            $totalProgress += $prog->progress;
        }

        $activeAssessmentAttempt = $user->getActiveFormalAssessment($class->id);
        $hasActiveAssessment = $activeAssessmentAttempt !== null;

        $activeAssessmentLabel = 'Assessment';
        if ($activeAssessmentAttempt) {
            $actMod = $activeAssessmentAttempt->module;
            if ($activeAssessmentAttempt->mock_board_id) {
                $activeAssessmentLabel = 'Mock Board Exam';
            } elseif ($actMod?->is_formal_assessment) {
                $activeAssessmentLabel = 'Formal Assessment';
            } elseif ($activeAssessmentAttempt->quiz_stage === 'pre_test' || $actMod?->quiz_stage === 'pre_test' || $actMod?->assessment_purpose === 'pre_test' || preg_match('/pre[- ]?(test|assessment)/i', $actMod?->title ?? '')) {
                $activeAssessmentLabel = 'Pre-Test';
            } elseif ($activeAssessmentAttempt->quiz_stage === 'post_test' || $actMod?->quiz_stage === 'post_test' || $actMod?->assessment_purpose === 'post_test' || preg_match('/post[- ]?(test|assessment)|final assessment/i', $actMod?->title ?? '')) {
                $activeAssessmentLabel = 'Post-Test';
            }
        }

        // Lock modules if they are not yet open (upcoming), closed (overdue / inactive),
        // or if student currently has an active formal assessment in progress (locks lectures).
        $locked = [];
        $availabilityInfo = [];

        foreach ($modules as $module) {
            $isUpcoming = $module->isUpcoming();
            $isClosed = $module->isClosed();
            $isInactive = ! ($module->is_active ?? true);
            $isLockedByAssessment = $hasActiveAssessment && ! $module->is_formal_assessment && ! $module->is_quiz && empty($module->quiz_stage);

            if ($isUpcoming || $isClosed || $isLockedByAssessment) {
                $locked[$module->id] = true;
            }

            $availabilityInfo[$module->id] = [
                'is_upcoming' => $isUpcoming,
                'is_closed' => $isClosed,
                'is_inactive' => $isInactive,
                'is_locked_by_assessment' => $isLockedByAssessment,
                'is_open' => $module->isOpen() && ! $isLockedByAssessment,
                'status_label' => $isLockedByAssessment ? "Locked ({$activeAssessmentLabel} in Progress)" : $module->statusLabel(),
                'available_at' => $module->available_at?->format('M d, Y g:i A'),
                'due_date' => $module->due_date?->format('M d, Y g:i A'),
            ];
        }

        // Load completed quiz attempts so the view can show locked results.
        $quizModuleIds = $modules->filter(fn ($m) => (bool) $m->is_quiz || (bool) $m->is_formal_assessment || ! empty($m->quiz_stage))->pluck('id');
        $quizAttempts = [];

        if ($quizModuleIds->isNotEmpty()) {
            QuizAttempt::where('user_id', $user->id)
                ->whereIn('module_id', $quizModuleIds)
                ->where('total', '>', 0)
                ->orderBy('attempt_count', 'asc')
                ->get()
                ->each(function (QuizAttempt $attempt) use (&$quizAttempts) {
                    $attemptData = [
                        'attempt_id' => $attempt->id,
                        'score' => $attempt->score,
                        'total' => $attempt->total,
                        'percentage' => $attempt->percentage,
                        'passed' => (bool) $attempt->passed,
                        'attempt_count' => $attempt->attempt_count,
                        'ai_strong' => $attempt->ai_strong,
                        'ai_weak' => $attempt->ai_weak,
                        'ai_recommendation' => $attempt->ai_recommendation,
                    ];
                    $quizAttempts[$attempt->module_id] = $attemptData;
                    if (! empty($attempt->quiz_stage)) {
                        $quizAttempts["{$attempt->module_id}:{$attempt->quiz_stage}"] = $attemptData;
                    }
                });
        }

        $overallCompletion = $moduleCount > 0 ? round($totalProgress / $moduleCount, 2) : 0;

        // Attempt-limit info bawat formal-assessment na module, para sa student-facing
        // "ilang attempts pa" na display bago pa man sagutan ng estudyante.
        $attemptLimits = [];
        $formalModules = $modules->where('is_formal_assessment', true);

        if ($formalModules->isNotEmpty()) {
            $formalModuleIds = $formalModules->pluck('id');

            $grants = AssessmentAttemptGrant::where('user_id', $user->id)
                ->whereIn('module_id', $formalModuleIds)
                ->get()
                ->keyBy('module_id');

            $attemptCounts = QuizAttempt::where('user_id', $user->id)
                ->whereIn('module_id', $formalModuleIds)
                ->groupBy('module_id')
                ->selectRaw('module_id, MAX(attempt_count) as max_attempt')
                ->pluck('max_attempt', 'module_id');

            foreach ($formalModules as $formalModule) {
                $baseMax = $formalModule->max_attempts ?? 1;
                $extra = (int) ($grants->get($formalModule->id)?->extra_attempts ?? 0);
                $used = (int) ($attemptCounts->get($formalModule->id) ?? ($quizAttempts[$formalModule->id]['attempt_count'] ?? 0));

                $totalAllowed = $baseMax + $extra;
                $attemptLimits[$formalModule->id] = [
                    'base_max' => $baseMax,
                    'base_max_attempts' => $baseMax,
                    'extra_granted' => $extra,
                    'extra_attempts_granted' => $extra,
                    'total_allowed' => $totalAllowed,
                    'attempts_allowed' => $totalAllowed,
                    'used' => $used,
                    'attempts_used' => $used,
                    'remaining' => max(0, $totalAllowed - $used),
                    'can_attempt' => $used < $totalAllowed,
                ];
            }
        }

        return view('pages.student.modules', compact(
            'class',
            'modules',
            'progress',
            'completed',
            'locked',
            'quizAttempts',
            'overallCompletion',
            'attemptLimits',
            'availabilityInfo',
            'hasActiveAssessment',
            'activeAssessmentLabel'
        ));
    }

    // Teacher: post announcement
    public function postAnnouncement(Request $request, ClassModel $class)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403);
        }

        $class->announcements()->create([
            'user_id' => Auth::id(),
            'message' => $request->message,
        ]);

        return redirect()->back()->with('success', 'Announcement posted.');
    }

    // Teacher: view announcements (optional)
    public function viewAnnouncements(ClassModel $class)
    {
        if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403);
        }

        $announcements = $class->announcements()->with('user')->latest()->get();

        return view('pages.teacher.announcements', compact('class', 'announcements'));
    }

    public function viewModuleFile(Module $module)
    {
        $user = Auth::user();
        $class = $module->class;

        if (! $class->users()->where('user_id', $user->id)->exists() &&
            $class->created_by !== $user->id &&
            $user->role !== 'admin') {
            abort(403);
        }

        if ($user->role === 'student' && $user->hasActiveFormalAssessment($class->id) && ! $module->is_formal_assessment) {
            abort(403, 'Access to lecture materials is locked while a Formal Assessment is in progress.');
        }

        // Check module visibility
        $visibility = $module->visibility ?? 'all';
        if ($visibility === 'selected') {
            // User must be in the visibleTo list
            $allowed = $module->visibleTo()->where('user_id', $user->id)->exists();
            if (! $allowed) {
                abort(403, 'You do not have access to this module.');
            }
        } elseif ($visibility === 'except') {
            // User must NOT be in the visibleTo list
            $blocked = $module->visibleTo()->where('user_id', $user->id)->exists();
            if ($blocked) {
                abort(403, 'You do not have access to this module.');
            }
        }

        // Sequential lock removed: students can open modules without previous-module completion checks.

        if (! $module->file_path) {
            abort(404);
        }

        $path = storage_path('app/public/'.$module->file_path);

        if (! file_exists($path)) {
            abort(404);
        }

        // Determine content type based on file_type
        $contentType = match ($module->file_type) {
            'mov' => 'video/quicktime',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/pdf',
        };

        return response()->file($path, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Accept-Ranges' => 'bytes', // for video seeking
        ]);
    }

    public function pdfjsViewer(Module $module)
    {
        $user = Auth::user();
        $class = $module->class;

        // Access check
        if (! $class->users()->where('user_id', $user->id)->exists() &&
            $class->created_by !== $user->id &&
            $user->role !== 'admin') {
            abort(403);
        }

        if ($user->role === 'student' && $user->hasActiveFormalAssessment($class->id) && ! $module->is_formal_assessment) {
            abort(403, 'Access to lecture materials is locked while a Formal Assessment is in progress.');
        }

        // Check module visibility
        $visibility = $module->visibility ?? 'all';
        if ($visibility === 'selected') {
            // User must be in the visibleTo list
            $allowed = $module->visibleTo()->where('user_id', $user->id)->exists();
            if (! $allowed) {
                abort(403, 'You do not have access to this module.');
            }
        } elseif ($visibility === 'except') {
            // User must NOT be in the visibleTo list
            $blocked = $module->visibleTo()->where('user_id', $user->id)->exists();
            if ($blocked) {
                abort(403, 'You do not have access to this module.');
            }
        }

        if (! $module->file_path || ! in_array($module->file_type, ['pdf', 'ppt', 'pptx', 'docx'], true)) {
            abort(404);
        }

        if (in_array($module->file_type, ['pdf', 'ppt', 'pptx'], true)) {
            $pdfUrl = route('module.view.pdf', $module);

            return view('pages.student.pdfjs-viewer', compact('pdfUrl', 'module'));
        }

        if ($module->file_type === 'docx') {
            $docxUrl = route('module.view', $module);

            return view('pages.student.docx-viewer', compact('module', 'docxUrl'));
        }

        abort(404);
    }

    public function viewPdf(Module $module)
    {
        $user = Auth::user();
        $class = $module->class;

        if (! $class->users()->where('user_id', $user->id)->exists() &&
            $class->created_by !== $user->id &&
            $user->role !== 'admin') {
            abort(403);
        }

        if ($user->role === 'student' && $user->hasActiveFormalAssessment($class->id) && ! $module->is_formal_assessment) {
            abort(403, 'Access to lecture materials is locked while a Formal Assessment is in progress.');
        }

        $visibility = $module->visibility ?? 'all';
        if ($visibility === 'selected') {
            $allowed = $module->visibleTo()->where('user_id', $user->id)->exists();
            if (! $allowed) {
                abort(403, 'You do not have access to this module.');
            }
        } elseif ($visibility === 'except') {
            $blocked = $module->visibleTo()->where('user_id', $user->id)->exists();
            if ($blocked) {
                abort(403, 'You do not have access to this module.');
            }
        }

        if (! $module->file_path || ! in_array($module->file_type, ['pdf', 'ppt', 'pptx'], true)) {
            abort(404);
        }

        $pdfPath = $module->file_type === 'pdf'
            ? storage_path('app/public/'.$module->file_path)
            : $this->ensurePresentationPreviewPdf($module);

        if (! file_exists($pdfPath)) {
            abort(404);
        }

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    public function publicModuleFile(Module $module)
    {
        if (! request()->hasValidSignature()) {
            abort(403);
        }

        if (! $module->file_path || ! in_array($module->file_type, ['ppt', 'pptx', 'docx'], true)) {
            abort(404);
        }

        $path = storage_path('app/public/'.$module->file_path);

        if (! file_exists($path)) {
            abort(404);
        }

        $contentType = match ($module->file_type) {
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            default => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        };

        return response()->file($path, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ]);
    }

    private function ensurePresentationPreviewPdf(Module $module): string
    {
        $sourcePath = storage_path('app/public/'.$module->file_path);

        if (! file_exists($sourcePath)) {
            abort(404);
        }

        $previewDirectory = storage_path('app/public/module-previews');
        if (! File::exists($previewDirectory)) {
            File::makeDirectory($previewDirectory, 0755, true);
        }

        $sourceHash = md5($module->file_path.'|'.filemtime($sourcePath));
        $previewPath = $previewDirectory.DIRECTORY_SEPARATOR.'module-'.$module->id.'-'.$sourceHash.'.pdf';

        if (file_exists($previewPath)) {
            return $previewPath;
        }

        foreach (glob($previewDirectory.DIRECTORY_SEPARATOR.'module-'.$module->id.'-*.pdf') ?: [] as $oldPreviewPath) {
            @unlink($oldPreviewPath);
        }

        $escapedSourcePath = str_replace("'", "''", $sourcePath);
        $escapedPreviewPath = str_replace("'", "''", $previewPath);

        $script = <<<'POWERSHELL'
$sourcePath = '__SOURCE_PATH__'
$targetPath = '__TARGET_PATH__'
$powerPoint = $null
$presentation = $null

try {
    $previewDirectory = Split-Path -Parent $targetPath
    if (-not (Test-Path -LiteralPath $previewDirectory)) {
        New-Item -ItemType Directory -Path $previewDirectory -Force | Out-Null
    }

    $powerPoint = New-Object -ComObject PowerPoint.Application
    $powerPoint.Visible = -1
    $presentation = $powerPoint.Presentations.Open($sourcePath, $false, $false, $false)
    $presentation.SaveAs($targetPath, 32)
}
catch {
    Write-Error $_.Exception.Message
    exit 1
}
finally {
    if ($presentation -ne $null) {
        $presentation.Close()
    }

    if ($powerPoint -ne $null) {
        $powerPoint.Quit()
    }
}
POWERSHELL;

        $script = str_replace(['__SOURCE_PATH__', '__TARGET_PATH__'], [$escapedSourcePath, $escapedPreviewPath], $script);
        $encodedCommand = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));

        $command = 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand '.$encodedCommand;

        exec($command.' 2>&1', $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($previewPath)) {
            Log::error('PowerPoint preview conversion failed.', [
                'module_id' => $module->id,
                'source_path' => $sourcePath,
                'preview_path' => $previewPath,
                'exit_code' => $exitCode,
                'output' => $output,
            ]);

            abort(500, 'The presentation preview could not be generated.');
        }

        return $previewPath;
    }

    /**
     * Return JSON list of modules for a class (for modal)
     */
    public function listModulesJson(ClassModel $class)
    {
        if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403);
        }

        $modules = $class->modules()
            ->orderByRaw("
                CASE 
                    WHEN quiz_stage = 'pre_test' 
                         OR assessment_purpose IN ('pre_test', 'pre_assessment') 
                         OR LOWER(title) LIKE '%pre-test%' 
                         OR LOWER(title) LIKE '%pre-assessment%' 
                         OR LOWER(title) LIKE '%pre test%' 
                         OR LOWER(title) LIKE '%pre assessment%' 
                         OR (is_quiz = 1 AND (is_formal_assessment = 0 OR is_formal_assessment IS NULL) AND quiz_stage != 'post_test') THEN 0
                    WHEN is_lecture = 1 OR is_quiz = 0 OR file_path IS NOT NULL THEN 1
                    WHEN is_formal_assessment = 1 AND (quiz_stage IS NULL OR quiz_stage NOT IN ('pre_test', 'post_test')) THEN 2
                    WHEN quiz_stage = 'post_test' 
                         OR assessment_purpose IN ('post_test', 'post_assessment') 
                         OR LOWER(title) LIKE '%post-test%' 
                         OR LOWER(title) LIKE '%post-assessment%' 
                         OR LOWER(title) LIKE '%post test%' 
                         OR LOWER(title) LIKE '%post assessment%' 
                         OR LOWER(title) LIKE '%final assessment%' THEN 3
                    ELSE 2
                END
            ")
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(function ($module) {
                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'description' => $module->description ?? 'No description',
                    'type' => $module->is_quiz ? 'Quiz' : ($module->is_assignment ? 'Assignment' : ($module->is_lecture ? 'Lecture' : 'Document')),
                    'is_quiz' => (bool) $module->is_quiz,
                    'is_lecture' => (bool) $module->is_lecture,
                    'is_formal_assessment' => (bool) $module->is_formal_assessment,
                    'quiz_stage' => $module->quiz_stage,
                    'assessment_purpose' => $module->assessment_purpose,
                    'edit_url' => $module->is_quiz ? route('quiz.create', $module) : null,
                    'file_path' => $module->file_path ? asset('storage/'.$module->file_path) : null,
                    'file_type' => $module->file_type,
                    'created_at' => $module->created_at->diffForHumans(),
                    'due_date' => $module->due_date?->format('M d, Y g:i A'),
                    'available_at' => $module->available_at?->format('M d, Y g:i A'),
                    'is_active' => (bool) ($module->is_active ?? true),
                    'status_label' => $module->statusLabel(),
                    'is_open' => $module->isOpen(),
                    'is_upcoming' => $module->isUpcoming(),
                    'is_closed' => $module->isClosed(),
                ];
            });

        return response()->json(['modules' => $modules]);
    }

    public function deleteModule(Module $module)
    {
        $class = $module->class;

        if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403);
        }

        if ($module->file_path) {
            $fullPath = storage_path('app/public/'.$module->file_path);
            @unlink($fullPath);
        }

        $module->delete();

        return response()->json(['success' => 'Module deleted']);
    }

    public function duplicateModule(Request $request, Module $module)
    {
        $class = $module->class;
        $actor = Auth::user();

        if (! $actor) {
            abort(401);
        }

        if ($class->created_by !== $actor->id && ! in_array($actor->role, ['admin', 'superadmin'], true)) {
            abort(403, 'Unauthorized to duplicate this module.');
        }

        return DB::transaction(function () use ($module, $class, $actor, $request) {
            $baseTitle = trim((string) preg_replace('/\s*\(Copy(\s+\d+)?\)$/i', '', $module->title));
            $existingCopies = Module::where('class_id', $class->id)
                ->where(function ($q) use ($baseTitle) {
                    $q->where('title', $baseTitle)
                        ->orWhere('title', 'LIKE', "{$baseTitle} (Copy%");
                })
                ->count();

            $newTitle = $existingCopies <= 1
                ? "{$baseTitle} (Copy)"
                : "{$baseTitle} (Copy {$existingCopies})";
            $maxOrder = (int) Module::where('class_id', $class->id)->max('order');

            $newModule = Module::create([
                'class_id' => $class->id,
                'title' => $newTitle,
                'description' => $module->description,
                'file_path' => $module->file_path,
                'file_type' => $module->file_type,
                'order' => $maxOrder + 1,
                'is_quiz' => $module->is_quiz,
                'is_assignment' => $module->is_assignment,
                'is_lecture' => $module->is_lecture,
                'is_formal_assessment' => $module->is_formal_assessment,
                'time_limit' => $module->time_limit,
                'passing_grade' => $module->passing_grade,
                'visibility' => $module->visibility ?? 'all',
                'created_by' => $actor->id,
                'is_mock_board' => $module->is_mock_board,
                'due_date' => null,
                'max_attempts' => $module->max_attempts ?? 1,
                'quiz_stage' => $module->quiz_stage,
            ]);

            if (in_array($module->visibility, ['selected', 'except'], true)) {
                $userIds = $module->visibleTo()->pluck('users.id')->toArray();
                if (! empty($userIds)) {
                    $newModule->visibleTo()->sync($userIds);
                }
            }

            foreach ($module->quizQuestions()->orderBy('order')->get() as $q) {
                QuizQuestion::create([
                    'module_id' => $newModule->id,
                    'quiz_stage' => $q->quiz_stage,
                    'question_text' => $q->question_text,
                    'options' => $q->options,
                    'correct_option' => $q->correct_option,
                    'points' => $q->points,
                    'order' => $q->order,
                    'difficulty' => $q->difficulty,
                    'domain' => $q->domain,
                    'explanation' => $q->explanation,
                    'test_bank_question_id' => $q->test_bank_question_id,
                ]);
            }

            if ($module->is_lecture) {
                foreach ($module->subparts()->with('lessons')->orderBy('order')->get() as $sp) {
                    $newSubpart = ModuleSubpart::create([
                        'module_id' => $newModule->id,
                        'title' => $sp->title,
                        'description' => $sp->description,
                        'body' => $sp->body,
                        'file_path' => $sp->file_path,
                        'file_type' => $sp->file_type,
                        'order' => $sp->order,
                    ]);

                    foreach ($sp->lessons as $lesson) {
                        $newSubpart->lessons()->create([
                            'title' => $lesson->title,
                            'description' => $lesson->description,
                            'body' => $lesson->body,
                            'file_path' => $lesson->file_path,
                            'file_type' => $lesson->file_type,
                            'order' => $lesson->order,
                        ]);
                    }
                }
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Module duplicated successfully.',
                    'module' => $newModule,
                    'edit_url' => $newModule->is_quiz ? route('quiz.create', $newModule) : null,
                ]);
            }

            return redirect()->route('quiz.create', $newModule)
                ->with('success', 'Assessment/Quiz duplicated successfully with clean data!');
        });
    }

    /**
     * Teacher/Admin: Manually end/close or reopen an assessment/quiz.
     */
    public function toggleModuleStatus(Request $request, Module $module)
    {
        $class = $module->class;
        $actor = Auth::user();

        if ($class && $class->created_by !== $actor->id && ! in_array($actor->role, ['admin', 'superadmin'], true)) {
            abort(403, 'Unauthorized to modify this module.');
        }

        $action = $request->input('action'); // 'open' | 'close' | 'end' | 'reopen'

        if ($action === 'open' || $action === 'reopen') {
            $module->update([
                'is_active' => true,
                'due_date' => null,
                'available_at' => now(),
            ]);
            $message = 'Exam is now OPEN for students.';
        } else {
            $module->update([
                'is_active' => false,
                'due_date' => now(),
            ]);
            $message = 'Exam is now CLOSED.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_open' => $module->isOpen(),
            'is_closed' => $module->isClosed(),
            'status_label' => $module->statusLabel(),
        ]);
    }

    public function updateProgress(Request $request, Module $module)
    {
        $user = Auth::user();

        if (! $module->class->users()->where('user_id', $user->id)->exists()) {
            abort(403);
        }

        $validated = $request->validate([
            'progress' => 'required|numeric|min:0|max:100',
            'completed' => 'sometimes|boolean',
        ]);

        ModuleProgress::updateOrCreate(
            ['module_id' => $module->id, 'user_id' => $user->id],
            [
                'progress' => $validated['progress'],
                'completed' => $validated['completed'] ?? false,
                'completed_at' => $validated['completed'] ? now() : null,
            ]
        );

        return response()->json(['success' => true]);
    }

    public function createQuiz(Request $request, Module $module)
    {
        $class = $module->class;

        // Ligtas na pagsusuri: Kung may class, i-check ang created_by nito.
        // Kung wala (program-based/mock board), ang titingnan naman ay ang created_by ng mismong module.
        if ($class) {
            if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
                abort(403);
            }
        } else {
            if ($module->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
                abort(403);
            }
        }

        // Which Lecture stage are we editing? null = standalone quiz / mock
        // board phase (exactly the old behavior). 'pre_test' / 'post_test' =
        // editing that specific stage of a Lecture module's quiz.
        $stage = $request->query('stage');
        $stage = in_array($stage, ['pre_test', 'post_test'], true) ? $stage : null;

        // Eksplisitong destinasyon para sa "Back" button — hindi na umaasa sa
        // url()->previous(), na nagiging self-referencing pagkatapos ng save/redirect loop.
        $isMockBoardModule = \DB::table('mock_board_phases')->where('module_id', $module->id)->exists() || ($module->is_mock_board ?? false);
        $backUrl = $isMockBoardModule
            ? route('student.mock-boards.index')
            : route('manageclass');

        // Reachable in two cases: (a) a standalone/mock-board quiz module
        // (is_quiz = true), or (b) a Lecture module (is_quiz = false) when a
        // valid ?stage= is supplied — Lecture modules never carry is_quiz,
        // so the old check alone would 404 every pre-test/post-test edit.
        if (! $module->is_quiz && $stage === null) {
            abort(404, 'This module is not a quiz.');
        }

        $questionsQuery = $module->quizQuestions();
        if ($stage !== null) {
            $questionsQuery->where('quiz_stage', $stage);
        } else {
            $questionsQuery->whereNull('quiz_stage');
        }

        $existingQuestions = $questionsQuery
            ->get()
            ->map(function (QuizQuestion $question) {
                return [
                    'text' => $question->question_text,
                    'options' => $question->options,
                    'correct' => $question->correct_option,
                    'points' => $question->points,
                    'difficulty' => $question->difficulty,
                ];
            });

        $settingsResolver = app(AiSettingsResolver::class);

        // Protektahan din ito: Kung null ang $class, iwasang pasahen ng null para hindi mag-error ang resolver
        $classQuizDefaults = $class ? $settingsResolver->getClassQuizDefaults($class) : [];

        // 1. Check if this module belongs to any mock board exam phases
        $isMockBoard = \DB::table('mock_board_phases')->where('module_id', $module->id)->exists() || ($module->is_mock_board ?? false);

        // 2. Resolve standard AI setting for regular quizzes (kung may class, tsaka lang isama ang class)
        $isAiQuizGenerationEnabled = $class ? $settingsResolver->isFeatureEnabled('quiz_generation', $class) : false;

        // 3. OVERRIDE: If this is a mock board, strip away all AI capability flags completely
        if ($isMockBoard) {
            $isAiQuizGenerationEnabled = false;
        }

        $existingTopics = TestBankQuestion::query()
            ->whereNotNull('topic')
            ->where('topic', '!=', '')
            ->distinct()
            ->orderBy('topic')
            ->pluck('topic');

        return view('pages.teacher.quiz-create', compact(
            'module',
            'class',
            'existingQuestions',
            'classQuizDefaults',
            'isAiQuizGenerationEnabled',
            'isMockBoard',
            'backUrl',
            'existingTopics',
            'stage'
        ));
    }

    /**
     * Generate quiz questions using AI, with support for multiple uploaded documents
     */
    /**
     * Generate quiz questions using AI, with precise target counts per uploaded document
     */
    public function generateQuizAi(Request $request, Module $module, AiSettingsResolver $settingsResolver)
    {
        try {
            $user = Auth::user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Please log in again.',
                ], 401);
            }

            $class = $module->class;

            if ($class) {
                if ((int) $class->created_by !== (int) $user->id && $user->role !== 'admin') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: You do not have permission for this class.',
                    ], 403);
                }
            } else {
                if ($user->role !== 'admin' && (int) $module->created_by !== (int) $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: You do not have permission for this Mock Board.',
                    ], 403);
                }
            }

            if (! $module->is_quiz) {
                return response()->json([
                    'success' => false,
                    'message' => 'This module is not configured as a quiz.',
                ], 400);
            }

            if (! $settingsResolver->isFeatureEnabled('quiz_generation', $class)) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI quiz generation is currently disabled for this class.',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'context_files' => 'required|array|min:1',
                'context_files.*' => 'file|mimes:pdf,doc,docx,txt|max:20480',
                'file_difficulty_counts' => 'required|array|min:1',
                'file_difficulty_counts.*' => 'array',
                'file_difficulty_counts.*.Easy' => 'nullable|integer|min:0',
                'file_difficulty_counts.*.Average' => 'nullable|integer|min:0',
                'file_difficulty_counts.*.Difficult' => 'nullable|integer|min:0',
                'extra_instructions' => 'nullable|string|max:500',
                'choice_count' => 'nullable|integer|min:2|max:10',
                'quiz_stage' => 'nullable|in:pre_test,post_test',
            ]);

            $validator->after(function ($v) use ($request) {
                $allCounts = $request->input('file_difficulty_counts', []);
                $grandTotal = 0;

                if (is_array($allCounts)) {
                    foreach ($allCounts as $index => $tiers) {
                        if (! is_array($tiers)) {
                            continue;
                        }

                        $fileTotal = ((int) ($tiers['Easy'] ?? 0))
                            + ((int) ($tiers['Average'] ?? 0))
                            + ((int) ($tiers['Difficult'] ?? 0));

                        if ($fileTotal > 100) {
                            $v->errors()->add(
                                "file_difficulty_counts.{$index}",
                                "The total requested questions for this file ({$fileTotal}) exceeds the maximum allowed per-file limit of 100."
                            );
                        }

                        $grandTotal += $fileTotal;
                    }
                }

                if ($grandTotal > 100) {
                    $v->errors()->add(
                        'file_difficulty_counts',
                        "The total requested questions across all files ({$grandTotal}) exceeds the maximum allowed limit of 100."
                    );
                }

                if ($grandTotal === 0) {
                    $v->errors()->add(
                        'file_difficulty_counts',
                        'Please request at least one question for at least one file.'
                    );
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first() ?: 'Validation error.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            $difficultyCounts = $validated['file_difficulty_counts'] ?? [];
            $allowedDifficulties = ['Easy', 'Average', 'Difficult'];
            $requestedQuestionCount = collect($difficultyCounts)
                ->sum(fn (array $tiers): int => collect($allowedDifficulties)->sum(fn (string $tier): int => (int) ($tiers[$tier] ?? 0)));

            // Count active total requested questions to size the time limit correctly.
            $totalQuestionsToGenerate = 0;
            foreach ($difficultyCounts as $tiers) {
                foreach ($allowedDifficulties as $tier) {
                    $totalQuestionsToGenerate += (int) ($tiers[$tier] ?? 0);
                }
            }
            set_time_limit(max(120, $totalQuestionsToGenerate * 10));

            $allGeneratedQuestions = [];
            $allExtractedTexts = [];
            $totalTokenUsage = [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
            ];
            $ai = app(CloudflareAI::class);

            $choiceCount = (int) ($validated['choice_count'] ?? 4);
            $choiceLetters = array_slice(range('A', 'J'), 0, $choiceCount);

            $typePatterns = [
                // what, why, how  (base = 5)
                'Easy' => [3, 1, 1],
                'Average' => [1, 2, 2],
                'Difficult' => [0, 2, 3],
            ];

            foreach ($request->file('context_files') as $index => $file) {
                $fileTiers = $difficultyCounts[$index] ?? [];

                $fileTotalRequested = 0;
                foreach ($allowedDifficulties as $tier) {
                    $fileTotalRequested += (int) ($fileTiers[$tier] ?? 0);
                }

                if ($fileTotalRequested === 0 || ! $file->isValid()) {
                    continue;
                }

                $storedPath = $file->storeAs(
                    'temp_context',
                    uniqid('ctx_').'.'.$file->getClientOriginalExtension(),
                    'local'
                );
                $fullPath = Storage::disk('local')->path($storedPath);

                $ext = strtolower($file->getClientOriginalExtension());
                $text = '';

                if ($ext === 'txt') {
                    $rawText = file_get_contents($fullPath) ?: '';
                    $cleanedText = $this->cleanExtractedPdfText($rawText);
                    $text = $this->truncateAtSentenceBoundary($cleanedText, 25000);
                } elseif (in_array($ext, ['docx', 'doc'])) {
                    $rawText = $this->extractDocxText($fullPath);
                    $cleanedText = $this->cleanExtractedPdfText($rawText);
                    $text = $this->truncateAtSentenceBoundary($cleanedText, 25000);
                } else {
                    try {
                        $parser = new Parser;
                        $pdf = $parser->parseFile($fullPath);
                        $rawText = $pdf->getText();
                        $cleanedText = $this->cleanExtractedPdfText($rawText);
                        $text = $this->truncateAtSentenceBoundary($cleanedText, 25000);
                    } catch (\Throwable $e) {
                        Log::warning('Document parse failed for file '.$file->getClientOriginalName().' - '.$e->getMessage());
                        @unlink($fullPath);

                        continue;
                    }
                }
                @unlink($fullPath);

                if ($text === '') {
                    Log::warning('Empty text after parse: '.$file->getClientOriginalName());

                    continue;
                }

                $allExtractedTexts[$index] = [
                    'name' => $file->getClientOriginalName(),
                    'text' => $text,
                ];

                // Slice document into distinct coherent sections so different batches read different parts of the PDF
                $desiredSections = max(5, min(40, (int) ceil($fileTotalRequested / 2)));
                $sections = $this->sliceDocumentIntoSections($text, $desiredSections);
                $sectionCount = count($sections);

                $extraInstructions = trim($validated['extra_instructions'] ?? '');
                $extraInstructionsBlock = $extraInstructions !== ''
                    ? "Additional Teacher Instructions: {$extraInstructions}\n\n"
                    : '';

                $optionsExample = '{'.implode(',', array_map(fn ($l) => "\"{$l}\":\"...\"", $choiceLetters)).'}';
                $letterList = implode('|', $choiceLetters);

                // Build task queue of micro-jobs (1 to 2 questions per job) for this file
                $tasks = [];
                foreach ($allowedDifficulties as $targetDifficulty) {
                    $requestedCount = (int) ($fileTiers[$targetDifficulty] ?? 0);
                    if ($requestedCount <= 0) {
                        continue;
                    }

                    $pattern = $typePatterns[$targetDifficulty] ?? $typePatterns['Normal'];
                    $base = array_sum($pattern);

                    $targetWhat = (int) round($requestedCount * ($pattern[0] / $base));
                    $targetWhy = (int) round($requestedCount * ($pattern[1] / $base));
                    $targetHow = (int) round($requestedCount * ($pattern[2] / $base));

                    $sum = $targetWhat + $targetWhy + $targetHow;
                    if ($sum < $requestedCount) {
                        if ($targetDifficulty === 'Average') {
                            $targetWhat += ($requestedCount - $sum);
                        } else {
                            $targetHow += ($requestedCount - $sum);
                        }
                    } elseif ($sum > $requestedCount) {
                        $overflow = $sum - $requestedCount;
                        if ($targetWhat >= $overflow) {
                            $targetWhat -= $overflow;
                        } else {
                            $overflow -= $targetWhat;
                            $targetWhat = 0;
                            if ($targetWhy >= $overflow) {
                                $targetWhy -= $overflow;
                            } else {
                                $overflow -= $targetWhy;
                                $targetWhy = 0;
                                $targetHow = max(0, $targetHow - $overflow);
                            }
                        }
                    }

                    foreach (['what' => $targetWhat, 'why' => $targetWhy, 'how' => $targetHow] as $qType => $count) {
                        while ($count > 0) {
                            $batchSize = min(5, $count);
                            $allTasks[] = [
                                'file_index' => $index,
                                'file_name' => $file->getClientOriginalName(),
                                'file_text' => $text,
                                'sections' => $sections,
                                'difficulty' => $targetDifficulty,
                                'type' => $qType,
                                'count' => $batchSize,
                            ];
                            $count -= $batchSize;
                        }
                    }
                }
            }

            // Build concurrent task payloads
            $poolPayloads = [];
            $extraInstructions = trim($validated['extra_instructions'] ?? '');
            $extraInstructionsBlock = $extraInstructions !== ''
                ? "Additional Teacher Instructions: {$extraInstructions}\n\n"
                : '';

            $optionsExample = '{'.implode(',', array_map(fn ($l) => "\"{$l}\":\"...\"", $choiceLetters)).'}';
            $letterList = implode('|', $choiceLetters);

            foreach ($allTasks as $tIdx => $task) {
                $targetDifficulty = $task['difficulty'];
                $questionType = $task['type'];
                $typeCount = $task['count'];
                $bufferedCount = (int) ceil($typeCount * 1.4) + 1;

                $sections = $task['sections'];
                $sectionCount = count($sections);
                $pass = (int) floor($tIdx / max(1, $sectionCount));
                $assignedSection = $sections[$tIdx % $sectionCount];

                $angleDirective = match ($pass % 4) {
                    1 => "FOCUS ANGLE: Focus specifically on implementation details, practical steps, or rule constraints found in this text.\n",
                    2 => "FOCUS ANGLE: Focus specifically on challenges, limitations, exceptions, or rationale behind rules found in this text.\n",
                    3 => "FOCUS ANGLE: Focus specifically on outcomes, benefits, evaluation methods, or comparative points found in this text.\n",
                    default => "FOCUS ANGLE: Focus specifically on definitions, key components, concepts, or primary facts found in this text.\n",
                };

                $typeInstructions = match ($questionType) {
                    'why' => "ALL {$bufferedCount} questions MUST be WHY questions.\n"
                        ."- Ask for reasoning, justification, purpose, or rationale behind a rule, principle, or outcome.\n"
                        ."- Answer options must concisely state the core rationale/purpose without repeating the question stem.\n"
                        ."- Set question_type to \"why\" on every object.\n"
                        ."- Include a short evidence field with a verbatim or near-verbatim 5-15 word phrase from the text.\n",
                    'how' => "ALL {$bufferedCount} questions MUST be HOW questions.\n"
                        ."- Ask for process, method, computation, application steps, or procedure.\n"
                        ."- Answer options must concisely state the specific action/process without repeating the question stem.\n"
                        ."- Set question_type to \"how\" on every object.\n"
                        ."- Include a short evidence field with a verbatim or near-verbatim 5-15 word phrase from the text.\n",
                    default => "ALL {$bufferedCount} questions MUST be WHAT questions.\n"
                        ."- Identify specific concepts, components, definitions, rules, or scenarios.\n"
                        ."- Set question_type to \"what\" on every object.\n"
                        ."- Include a short evidence field with a verbatim or near-verbatim 5-15 word phrase from the text.\n",
                };

                $lectureDirectives = "LECTURE-BASED & ANTI-EXAMPLE DIRECTIVES:\n"
                    ."- STRICTLY LECTURE-BASED: Test ONLY the general theoretical rules, criteria, standards, definitions, and procedures taught in the lecture.\n"
                    ."- COMPLETELY IGNORE ILLUSTRATIONS/EXAMPLES: If the text contains any section, paragraph, or sentence labeled 'Illustration', 'Example', 'Case', or has specific peso amounts or entity names, COMPLETELY IGNORE IT as if it does not exist in the document.\n"
                    ."- FORBIDDEN IN STEM AND CHOICES: Do NOT create questions asking 'Which of the following is an example...'. Do NOT copy or include ANY numbers, peso amounts, or specific company/person names from worked examples in EITHER question stems OR answer choices.\n"
                    ."- RULE-BASED QUESTIONS ONLY: Ask directly about the underlying rule, principle, definition, or criteria.\n"
                    ."- NO PRE-EXISTING QUESTIONS: If the text contains practice problems, quizzes, drills, exercises, or answer keys, IGNORE them completely and formulate questions testing the underlying lecture concepts instead.\n";

                $prompt = "Generate EXACTLY {$bufferedCount} unique multiple-choice questions based ONLY on the text below.\n"
                    ."Each question must have EXACTLY {$choiceCount} answer choices ({$letterList}).\n"
                    ."Difficulty: {$targetDifficulty}.\n"
                    ."Source file: {$task['file_name']}\n\n"
                    ."════════════════════════════════════════\n"
                    .$lectureDirectives
                    .$angleDirective
                    .$typeInstructions
                    ."════════════════════════════════════════\n\n"
                    ."Requirements:\n"
                    ."- Formulate questions specifically testing concepts, rules, facts, or standards found in the content below.\n"
                    ."- Return ONLY a valid JSON array of {$bufferedCount} objects.\n"
                    ."- Format: {\"question\":\"...\",\"options\":{$optionsExample},\"correct\":\"{$letterList}\",\"difficulty\":\"{$targetDifficulty}\",\"question_type\":\"{$questionType}\",\"evidence\":\"...\"}\n"
                    ."- Spread correct answers across {$letterList}.\n"
                    ."- CRITICAL: Every question must test a DISTINCT topic or fact. Do NOT repeat or reuse the same answer choices or correct answers across different questions.\n\n"
                    .$extraInstructionsBlock
                    ."Content:\n{$assignedSection}\n\n"
                    .'Rules: Return valid JSON array only. No markdown, no extra text.';

                $poolPayloads[$tIdx] = [
                    'model' => $settingsResolver->getModel(),
                    'payload' => [
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => "You generate board-exam MCQs. Output ONLY a JSON array of {$bufferedCount} objects. Every question must be lecture-based, testing concepts without copying worked examples. Distinct and non-duplicative. No markdown.",
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt,
                            ],
                        ],
                        'max_tokens' => min(380 * $bufferedCount, 4000),
                        'temperature' => 0.35,
                        'response_format' => [
                            'type' => 'json_schema',
                            'json_schema' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'question' => ['type' => 'string'],
                                        'options' => [
                                            'type' => 'object',
                                            'properties' => array_fill_keys($choiceLetters, ['type' => 'string']),
                                            'required' => $choiceLetters,
                                        ],
                                        'correct' => [
                                            'type' => 'string',
                                            'enum' => $choiceLetters,
                                        ],
                                        'difficulty' => [
                                            'type' => 'string',
                                            'enum' => $allowedDifficulties,
                                        ],
                                        'question_type' => [
                                            'type' => 'string',
                                            'enum' => ['what', 'why', 'how'],
                                        ],
                                        'evidence' => ['type' => 'string'],
                                    ],
                                    'required' => ['question', 'options', 'correct', 'difficulty', 'question_type', 'evidence'],
                                ],
                            ],
                        ],
                    ],
                ];
            }

            // EXECUTE ALL MAIN TASKS IN PARALLEL VIA NON-BLOCKING HTTP POOL
            $poolResults = $ai->runPool($poolPayloads);

            // Process results from parallel pool
            foreach ($allTasks as $tIdx => $task) {
                $targetDifficulty = $task['difficulty'];
                $questionType = $task['type'];
                $typeCount = $task['count'];
                $fileText = $task['file_text'];
                $fileName = $task['file_name'];

                $result = $poolResults[$tIdx] ?? [];
                if (isset($result['usage'])) {
                    $totalTokenUsage['prompt_tokens'] += (int) ($result['usage']['prompt_tokens'] ?? 0);
                    $totalTokenUsage['completion_tokens'] += (int) ($result['usage']['completion_tokens'] ?? 0);
                    $totalTokenUsage['total_tokens'] += (int) ($result['usage']['total_tokens'] ?? 0);
                }
                $aiResponse = $result['response'] ?? '';

                if (is_array($aiResponse)) {
                    $batch = $aiResponse;
                } else {
                    $raw = trim((string) $aiResponse);
                    $raw = preg_replace('/^[\s\r\n]*```json\s*/i', '', $raw);
                    $raw = preg_replace('/\s*```[\s\r\n]*$/i', '', $raw);
                    $raw = preg_replace('/^[\s\r\n]*```[\s\r\n]*/i', '', $raw);
                    $raw = preg_replace('/,\s*([}\]])/u', '$1', $raw);
                    $raw = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw);
                    $raw = trim($raw);
                    preg_match('/\[.*\]/s', $raw, $matches);
                    $jsonBlock = $matches[0] ?? $raw;
                    if (! str_starts_with($jsonBlock, '[')) {
                        $jsonBlock = '['.$jsonBlock;
                    }
                    if (! str_ends_with($jsonBlock, ']')) {
                        $jsonBlock .= ']';
                    }
                    $batch = json_decode($jsonBlock, true);
                    if (! is_array($batch)) {
                        $batch = [];
                    }
                }

                $cleanBatch = [];
                $rejections = [
                    'invalid_structure' => 0,
                    'empty_question_or_option' => 0,
                    'duplicate_options' => 0,
                    'ungrounded' => 0,
                    'stem_echo' => 0,
                ];
                foreach ($batch as $candidateIndex => $candidate) {
                    $rejectionReason = $this->aiQuestionRejectionReason($candidate, $choiceLetters, $questionType, $fileText);
                    if ($rejectionReason !== null) {
                        $rejections[$rejectionReason] = ($rejections[$rejectionReason] ?? 0) + 1;

                        continue;
                    }

                    $cleanBatch[] = $candidate;
                }
                if (array_sum($rejections) > 0) {
                    Log::info('AI quiz task rejections', [
                        'file' => $fileName,
                        'difficulty' => $targetDifficulty,
                        'type' => $questionType,
                        'rejections' => $rejections,
                    ]);
                }
                $batch = $cleanBatch;

                // Force question_type + difficulty, then shuffle options so correct is not always A
                foreach ($batch as &$q) {
                    $q['question_type'] = $questionType;
                    $q['difficulty'] = $targetDifficulty;

                    $options = $q['options'];
                    $correctLetter = strtoupper((string) $q['correct']);
                    $correctText = $options[$correctLetter] ?? reset($options);

                    $texts = array_values($options);
                    for ($i = count($texts) - 1; $i > 0; $i--) {
                        $j = random_int(0, $i);
                        [$texts[$i], $texts[$j]] = [$texts[$j], $texts[$i]];
                    }

                    $newOptions = [];
                    $newCorrect = $choiceLetters[0];
                    foreach ($choiceLetters as $idx => $letter) {
                        $newOptions[$letter] = $texts[$idx] ?? '';
                        if (($texts[$idx] ?? null) === $correctText) {
                            $newCorrect = $letter;
                        }
                    }

                    $q['options'] = $newOptions;
                    $q['correct'] = $newCorrect;
                }
                unset($q);

                foreach (array_slice($batch, 0, $typeCount) as $q) {
                    $allGeneratedQuestions[] = $q;
                }
            }

            $deduplication = $this->deduplicateQuestionBatch($allGeneratedQuestions);
            $allGeneratedQuestions = $deduplication['questions'];

            if (! empty($deduplication['duplicates'])) {
                Log::warning('AI quiz cross-batch duplicates removed', [
                    'removed' => count($deduplication['duplicates']),
                    'duplicates' => $deduplication['duplicates'],
                ]);
            }

            // SMART MICRO-BATCH TOP-UP: Top up in fast micro-batches targeting rotating sections
            $missingInitial = max(0, $requestedQuestionCount - count($allGeneratedQuestions));
            $maxMicroBatches = max(30, $missingInitial * 3);
            $microBatchCount = 0;

            while (count($allGeneratedQuestions) < $requestedQuestionCount && $microBatchCount < $maxMicroBatches && ! empty($allExtractedTexts)) {
                $microBatchCount++;
                $missingCount = $requestedQuestionCount - count($allGeneratedQuestions);
                $batchTarget = min($missingCount, $missingCount > 20 ? 6 : 3);
                $bufferedTopUp = (int) ceil($batchTarget * 1.4) + 1;

                $fileKeys = array_keys($allExtractedTexts);
                $selectedKey = $fileKeys[$microBatchCount % count($fileKeys)];
                $topUpFile = $allExtractedTexts[$selectedKey];
                $fullDocText = $topUpFile['text'] ?? '';
                $topUpFileName = $topUpFile['name'] ?? 'context_file';

                if ($fullDocText === '') {
                    break;
                }

                $topUpSections = $this->sliceDocumentIntoSections($fullDocText, 30);
                $topUpChunkText = $topUpSections[$microBatchCount % count($topUpSections)];

                $existingStems = array_map(fn ($q) => (string) ($q['question'] ?? ''), $allGeneratedQuestions);
                $avoidList = implode("\n", array_map(fn ($s) => '- '.trim($s), array_slice($existingStems, -35)));
                $avoidBlock = "CRITICAL: Avoid Duplicates. Do NOT repeat or closely rephrase any of these existing questions:\n{$avoidList}\n\n";

                $topUpPrompt = "Generate EXACTLY {$bufferedTopUp} unique multiple-choice questions based ONLY on the text below.\n"
                    ."Each question must have EXACTLY {$choiceCount} answer choices ({$letterList}).\n"
                    ."Source file: {$topUpFileName}\n\n"
                    .$avoidBlock
                    ."Requirements:\n"
                    ."- STRICTLY LECTURE-BASED: Test distinct theoretical concepts, standards, rules, or definitions from the text.\n"
                    ."- COMPLETELY IGNORE ILLUSTRATIONS/EXAMPLES: If the text contains sections with worked illustrations, examples, or specific figures, completely ignore them.\n"
                    ."- FORBIDDEN IN STEM AND CHOICES: Do NOT create questions asking 'Which of the following is an example...'. Do NOT use worked example numbers or company names in stems or answer choices.\n"
                    ."- NO PRE-EXISTING QUESTIONS: If the text includes existing sample drills or questions, ignore them and formulate fresh concept questions.\n"
                    ."- Format: Return ONLY a valid JSON array of {$bufferedTopUp} objects.\n"
                    ."- Format: [{\"question\":\"...\",\"options\":{$optionsExample},\"correct\":\"{$letterList}\",\"difficulty\":\"Easy|Average|Difficult\",\"question_type\":\"what|why|how\",\"evidence\":\"...\"}]\n"
                    ."- Spread correct answers across {$letterList}.\n"
                    ."- No markdown, no extra text.\n\n"
                    ."Content:\n{$topUpChunkText}";

                try {
                    $topUpPayload = [
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => "You generate board-exam MCQs. Output ONLY a JSON array of {$bufferedTopUp} distinct objects. Lecture-based concepts only, no worked examples. No markdown.",
                            ],
                            [
                                'role' => 'user',
                                'content' => $topUpPrompt,
                            ],
                        ],
                        'max_tokens' => min(380 * $bufferedTopUp, 4000),
                        'temperature' => 0.4,
                        'response_format' => [
                            'type' => 'json_schema',
                            'json_schema' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'question' => ['type' => 'string'],
                                        'options' => [
                                            'type' => 'object',
                                            'properties' => array_fill_keys($choiceLetters, ['type' => 'string']),
                                            'required' => $choiceLetters,
                                        ],
                                        'correct' => [
                                            'type' => 'string',
                                            'enum' => $choiceLetters,
                                        ],
                                        'difficulty' => [
                                            'type' => 'string',
                                            'enum' => $allowedDifficulties,
                                        ],
                                        'question_type' => [
                                            'type' => 'string',
                                            'enum' => ['what', 'why', 'how'],
                                        ],
                                        'evidence' => ['type' => 'string'],
                                    ],
                                    'required' => ['question', 'options', 'correct', 'difficulty', 'question_type', 'evidence'],
                                ],
                            ],
                        ],
                    ];

                    $topUpResult = $ai->run($settingsResolver->getModel(), $topUpPayload);
                    if (isset($topUpResult['usage'])) {
                        $totalTokenUsage['prompt_tokens'] += (int) ($topUpResult['usage']['prompt_tokens'] ?? 0);
                        $totalTokenUsage['completion_tokens'] += (int) ($topUpResult['usage']['completion_tokens'] ?? 0);
                        $totalTokenUsage['total_tokens'] += (int) ($topUpResult['usage']['total_tokens'] ?? 0);
                    }
                    $rawTopUp = $topUpResult['response'] ?? '';
                    $topUpBatch = is_array($rawTopUp) ? $rawTopUp : null;

                    if (! is_array($topUpBatch)) {
                        $cleaned = trim((string) $rawTopUp);
                        $cleaned = preg_replace('/^[\s\r\n]*```json\s*/i', '', $cleaned);
                        $cleaned = preg_replace('/\s*```[\s\r\n]*$/i', '', $cleaned);
                        $cleaned = preg_replace('/^[\s\r\n]*```[\s\r\n]*/i', '', $cleaned);
                        preg_match('/\[.*\]/s', $cleaned, $m);
                        $topUpBatch = json_decode($m[0] ?? $cleaned, true);
                    }

                    if (is_array($topUpBatch)) {
                        $validTopUp = [];
                        $topUpRejections = [
                            'invalid_structure' => 0,
                            'empty_question_or_option' => 0,
                            'duplicate_options' => 0,
                            'ungrounded' => 0,
                            'stem_echo' => 0,
                        ];
                        foreach ($topUpBatch as $candidate) {
                            $qType = (string) ($candidate['question_type'] ?? 'what');
                            $reason = $this->aiQuestionRejectionReason($candidate, $choiceLetters, $qType, $fullDocText);
                            if ($reason !== null) {
                                $topUpRejections[$reason] = ($topUpRejections[$reason] ?? 0) + 1;

                                continue;
                            }

                            $options = $candidate['options'];
                            $correctLetter = strtoupper((string) $candidate['correct']);
                            $correctText = $options[$correctLetter] ?? reset($options);

                            $texts = array_values($options);
                            for ($i = count($texts) - 1; $i > 0; $i--) {
                                $j = random_int(0, $i);
                                [$texts[$i], $texts[$j]] = [$texts[$j], $texts[$i]];
                            }

                            $newOptions = [];
                            $newCorrect = $choiceLetters[0];
                            foreach ($choiceLetters as $idx => $letter) {
                                $newOptions[$letter] = $texts[$idx] ?? '';
                                if (($texts[$idx] ?? null) === $correctText) {
                                    $newCorrect = $letter;
                                }
                            }

                            $candidate['options'] = $newOptions;
                            $candidate['correct'] = $newCorrect;
                            $validTopUp[] = $candidate;
                        }

                        if (array_sum($topUpRejections) > 0) {
                            Log::info('AI quiz top-up rejections', [
                                'file' => $topUpFileName,
                                'iteration' => $microBatchCount,
                                'rejections' => $topUpRejections,
                            ]);
                        }

                        $combined = array_merge($allGeneratedQuestions, $validTopUp);
                        $dedupResult = $this->deduplicateQuestionBatch($combined);
                        $allGeneratedQuestions = $dedupResult['questions'];
                    }
                } catch (\Exception $topUpEx) {
                    Log::warning('AI quiz micro-batch top-up failed: '.$topUpEx->getMessage());
                }
            }

            // Cap to exact requested count if we reached or exceeded it
            if (count($allGeneratedQuestions) > $requestedQuestionCount) {
                $allGeneratedQuestions = array_slice($allGeneratedQuestions, 0, $requestedQuestionCount);
            }

            $answerDistribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0, 'G' => 0, 'H' => 0, 'I' => 0, 'J' => 0];
            foreach ($allGeneratedQuestions as $question) {
                $correctLetter = strtoupper((string) ($question['correct'] ?? ''));
                if (isset($answerDistribution[$correctLetter])) {
                    $answerDistribution[$correctLetter]++;
                }
            }
            Log::info('AI quiz answer letter distribution', [
                'distribution' => $answerDistribution,
                'generated' => count($allGeneratedQuestions),
            ]);

            if (empty($allGeneratedQuestions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid questions could be generated from any of the uploaded documents.',
                    'requested' => $requestedQuestionCount,
                    'generated' => 0,
                    'shortfall' => $requestedQuestionCount,
                    'questions' => [],
                ], 422);
            }

            $generatedQuestionCount = count($allGeneratedQuestions);
            $quizStage = $validated['quiz_stage'] ?? null;

            DB::transaction(function () use ($module, $allGeneratedQuestions, $allowedDifficulties, $quizStage) {
                QuizQuestion::query()
                    ->where('module_id', $module->id)
                    ->where('quiz_stage', $quizStage)
                    ->delete();

                foreach ($allGeneratedQuestions as $index => $q) {
                    $questionDifficulty = ucfirst(strtolower((string) ($q['difficulty'] ?? '')));
                    if (! in_array($questionDifficulty, $allowedDifficulties, true)) {
                        $questionDifficulty = 'Average';
                    }

                    QuizQuestion::create([
                        'module_id' => $module->id,
                        'quiz_stage' => $quizStage,
                        'question_text' => trim((string) $q['question']),
                        'options' => $q['options'],
                        'correct_option' => strtoupper((string) $q['correct']),
                        'points' => 1,
                        'order' => $index + 1,
                        'difficulty' => $questionDifficulty,
                    ]);
                }
            });

            Log::info('AI quiz generation complete', [
                'requested' => $requestedQuestionCount,
                'generated' => $generatedQuestionCount,
                'token_usage' => $totalTokenUsage,
            ]);

            $tokensFormatted = number_format($totalTokenUsage['total_tokens']);
            $successMessage = ($generatedQuestionCount === $requestedQuestionCount)
                ? $generatedQuestionCount.' of '.$requestedQuestionCount.' requested questions generated and saved ('.$tokensFormatted.' tokens used).'
                : $generatedQuestionCount.' questions generated and saved into the quiz module ('.$tokensFormatted.' tokens used).';

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'requested' => $requestedQuestionCount,
                'generated' => $generatedQuestionCount,
                'shortfall' => max(0, $requestedQuestionCount - $generatedQuestionCount),
                'tokens' => $totalTokenUsage,
                'questions' => $allGeneratedQuestions,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Quiz Generation failed: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'AI Generation failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper to truncate text cleanly at sentence boundaries
     */
    private function truncateAtSentenceBoundary(string $text, int $limit = 8000): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $substring = mb_substr($text, 0, $limit);

        // Search for last sentence end (. ! ?)
        if (preg_match('/.*[.!?](?=\s|$)/s', $substring, $matches)) {
            return trim($matches[0]);
        }

        // Fallback: Clip at last space boundary
        $lastSpace = mb_strrpos($substring, ' ');

        return $lastSpace !== false ? mb_substr($substring, 0, $lastSpace) : $substring;
    }

    /**
     * Helper to extract plain text from DOCX files
     */
    private function extractDocxText(string $filePath): string
    {
        if (! class_exists('\ZipArchive')) {
            return '';
        }

        $zip = new \ZipArchive;
        if ($zip->open($filePath) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            if ($xml) {
                $xml = preg_replace('/<w:p[ >]/', "\n", $xml);

                return strip_tags($xml);
            }
        }

        return '';
    }

    /**
     * Save manually created quiz questions
     */
    public function storeQuizManual(Request $request, Module $module)
    {
        $class = $module->class;

        if ($class) {
            if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
                abort(403);
            }
        } else {
            if (Auth::user()->role !== 'admin' && (int) $module->created_by !== (int) Auth::id()) {
                abort(403, 'You do not have permission to modify this Mock Board.');
            }
        }

        if (! $module->is_quiz) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'questions' => 'required|array|min:1',
            'questions.*.text' => 'required|string',
            'questions.*.options' => 'required|array|min:2|max:10',
            'questions.*.options.*' => 'required|string',
            'questions.*.correct' => 'required|string',
            'questions.*.points' => 'required|integer|min:1',
            'questions.*.difficulty' => 'nullable|in:Average,Normal,Hard',
            'questions.*.domain' => 'nullable|string|max:150',
            'questions.*.explanation' => 'nullable|string',
            'shuffle_questions' => 'nullable|boolean',
            // NULL = ordinary standalone quiz (unchanged behavior). Set to
            // 'pre_test' or 'post_test' only when saving one stage of a
            // lecture module's quiz.
            'quiz_stage' => 'nullable|in:pre_test,post_test',
        ]);

        // Tiyakin na ang 'correct' letter ay talagang isa sa mga key na binigay sa 'options'
        $validator->after(function ($v) use ($request) {
            $questions = $request->input('questions', []);

            foreach ($questions as $index => $q) {
                $options = $q['options'] ?? [];
                $correct = $q['correct'] ?? null;

                if ($correct === null || ! array_key_exists($correct, $options)) {
                    $v->errors()->add(
                        "questions.{$index}.correct",
                        'Ang tinukoy na tamang sagot ay wala sa listahan ng options.'
                    );
                }
            }
        });

        $validated = $validator->validate();

        $questions = $validated['questions'];

        if (! empty($validated['shuffle_questions'])) {
            shuffle($questions);
        }

        $quizStage = $validated['quiz_stage'] ?? null;

        DB::transaction(function () use ($questions, $module, $quizStage) {
            // Scoped by quiz_stage: saving a post-test must not touch the
            // pre-test's questions (or a standalone quiz's, since those stay
            // quiz_stage = null and this query would leave them alone too).
            QuizQuestion::query()
                ->where('module_id', $module->id)
                ->where('quiz_stage', $quizStage)
                ->delete();

            foreach (array_values($questions) as $index => $q) {
                QuizQuestion::create([
                    'module_id' => $module->id,
                    'quiz_stage' => $quizStage,
                    'question_text' => $q['text'],
                    'options' => $q['options'], // dynamic na array, hindi na fixed A-D
                    'correct_option' => $q['correct'],
                    'points' => $q['points'],
                    'order' => $index + 1,
                    'difficulty' => $q['difficulty'] ?? 'Normal',
                    'domain' => $q['domain'] ?? null,
                    'explanation' => $q['explanation'] ?? null,
                ]);
            }
        });

        return redirect()->route('quiz.create', $module)
            ->with('success', count($validated['questions']).' quiz questions saved successfully!');
    }

    public function createQuizDraft(Request $request, ClassModel $class)
    {
        if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403);
        }
        \Log::info('createQuizDraft - Received data:', $request->all());
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'quiz_stage' => 'nullable|in:pre_test,post_test',
            'is_formal_assessment' => 'nullable|boolean',
            // allow null, but ensure numeric when present
            'time_limit' => 'nullable',
            'due_date' => 'nullable|date',
            'available_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        // Normalize time_limit: accept numeric strings, null, or empty -> store null or int minutes
        $rawTime = $request->input('time_limit', null);

        // If user submitted an empty string, treat as null (no limit)
        if ($rawTime === '' || $rawTime === null) {
            $minutes = null;
        } else {
            // Remove non-digits, then cast to int. This accepts "5", "05", " 5 ", etc.
            // If you expect formats like "00:05", handle that separately below.
            if (is_numeric($rawTime)) {
                $minutes = (int) $rawTime;
            } elseif (preg_match('/^(\d+):(\d{1,2})$/', trim($rawTime), $m)) {
                // Accept "MM:SS" or "H:MM" style and convert to minutes (round up if seconds > 0)
                $mins = (int) $m[1];
                $secs = (int) $m[2];
                $minutes = $mins + ($secs > 0 ? 1 : 0);
            } else {
                // fallback: try to extract digits
                preg_match('/\d+/', $rawTime, $m2);
                $minutes = isset($m2[0]) ? (int) $m2[0] : null;
            }
        }

        $module = Module::create([
            'class_id' => $class->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'time_limit' => $minutes ?? 0,
            'due_date' => $validated['due_date'] ?? null,
            'available_at' => $validated['available_at'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : false,
            'file_path' => null,
            'file_type' => null,
            'is_quiz' => true,
            'is_assignment' => false,
            'is_formal_assessment' => $request->boolean('is_formal_assessment'),
            'quiz_stage' => $validated['quiz_stage']
                ?? ($request->boolean('is_formal_assessment') ? null : 'pre_test'),
        ]);

        return redirect()->route('quiz.create', $module);
    }

    public function showModules(ClassModel $class)
    {
        if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403);
        }

        $modules = $class->modules()->latest()->get();

        return view('pages.teacher.modules-list', compact('class', 'modules'));
    }

    /**
     * Delete a module (teacher/admin only)
     */
    public function destroyModule(Module $module)
    {
        $class = $module->class;

        // Security: only creator or admin
        if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403);
        }

        // Optional: delete physical file
        if ($module->file_path) {
            $fullPath = storage_path('app/public/'.$module->file_path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        // Delete module (cascades to quiz_questions if foreign key set)
        $module->delete();

        return redirect()->back()
            ->with('success', 'Module deleted successfully.');
    }

    public function getQuizQuestions(Module $module)
    {
        // load questions and module
        $questions = QuizQuestion::where('module_id', $module->id)
            ->orderBy('order')
            ->get();

        return response()->json([
            'success' => true,
            'questions' => $questions,
            // ensure time_limit is an integer (minutes) or null
            'time_limit' => is_null($module->time_limit) ? null : (int) $module->time_limit,
        ]);
    }

    public function studentPerformance(ClassModel $class)
    {
        if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403, 'You do not have permission to view this class performance.');
        }

        $attempts = QuizAttempt::whereHas('module', function ($q) use ($class) {
            $q->where('class_id', $class->id);
        })->with(['user', 'module'])->get();

        $totalStudents = $class->students()->count();
        $averageScore = $attempts->avg('percentage') ?? 0;
        $passRate = $totalStudents > 0 ? round(($attempts->where('passed', true)->count() / max($attempts->count(), 1)) * 100, 1) : 0;

        $weakTopics = [
            ['topic' => 'Reference Locking', 'count' => 14],
            ['topic' => 'AI Engine Function', 'count' => 11],
            ['topic' => 'Database Storage', 'count' => 8],
        ];

        $ranking = $attempts->sortByDesc('percentage')
            ->take(10)
            ->map(fn ($a) => [
                'rank' => $attempts->sortByDesc('percentage')->search($a) + 1,
                'student' => $a->user->name ?? 'Student '.$a->user_id,
                'score' => round($a->percentage, 1).'%',
            ]);

        $aiSummary = 'The class performed moderately (average '.round($averageScore, 1).'%). Most students struggled with Reference Locking and AI Engine functions. Recommendation: Review Module 3 next week.';

        return view('teacher.student-performance', compact(
            'class', 'averageScore', 'passRate', 'weakTopics', 'ranking', 'aiSummary', 'totalStudents'
        ));
    }

    /**
     * Display quiz with timer based on teacher's input
     */
    public function quizTimer(Module $module)
    {
        // Teacher sets time_limit (in minutes) when creating the quiz
        $timeLimitMinutes = $module->time_limit ?? 0;

        return view('quiz.timer', [
            'module' => $module,
            'questions' => $module->questions, // assuming relation exists
            'timeLimitMinutes' => $timeLimitMinutes,
        ]);
    }

    /**
     * Return Lecture-style modules for a class (has sub-parts and/or an
     * existing pre-test/post-test) so the "Assessment" tab in manageclass
     * can list them with Edit Pre-Test / Edit Post-Test links. Plain
     * standalone quizzes and document modules are excluded.
     */
    public function listLectureModulesJson(ClassModel $class)
    {
        if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403);
        }

        $modules = $class->modules()
            ->where('is_lecture', true)
            ->with('subparts:id,module_id')
            ->get()
            ->values()
            ->map(function (Module $module) {
                return [
                    'id' => $module->id,
                    'title' => $module->title,
                    'has_pre_test' => $module->hasPreTest(),
                    'has_post_test' => $module->hasPostTest(),
                    'pre_test_url' => route('quiz.create', $module).'?stage=pre_test',
                    'post_test_url' => route('quiz.create', $module).'?stage=post_test',
                ];
            });

        return response()->json(['modules' => $modules]);
    }

    /**
     * Search students enrolled in a class (for visibility picker).
     */
    public function searchClassStudents(Request $request, ClassModel $class): JsonResponse
    {
        if ($class->created_by !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403);
        }

        $query = trim($request->input('q', ''));

        $students = $class->users()
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('users.name', 'like', '%'.$query.'%')
                        ->orWhere('users.email', 'like', '%'.$query.'%')
                        ->orWhere('users.idnumber', 'like', '%'.$query.'%');
                });
            })
            ->select('users.id', 'users.name', 'users.email', 'users.idnumber', 'users.program')
            ->limit(20)
            ->get();

        return response()->json($students);
    }
}

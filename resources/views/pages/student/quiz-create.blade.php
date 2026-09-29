@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2>Create Quiz for: {{ $module->title }}</h2>

    <ul class="nav nav-tabs mb-4" id="quizTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="ai-tab" data-bs-toggle="tab" data-bs-target="#ai" type="button" role="tab">AI Generator</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab">Manual Creation</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- AI Tab -->
        <div class="tab-pane fade show active" id="ai" role="tabpanel">
            <div class="alert alert-warning d-flex align-items-center mb-3" role="alert" style="font-size: 14px;">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <div>
                    <strong>AI Generation Disclaimer:</strong> AI-generated content is intended for study drafting assistance and may not be 100% accurate. Please review and cross-reference all questions with your official lecture notes and textbooks.
                </div>
            </div>
            <form id="aiQuizForm" method="POST" action="{{ route('quiz.generate', $module) }}">
                @csrf
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Number of Questions</label>
                        <input type="number" name="num_questions" class="form-control" min="1" max="20" value="5" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Difficulty Level</label>
                        <select name="difficulty" class="form-select" required>
                            <option value="Easy">Easy</option>
                            <option value="Normal" selected>Normal</option>
                            <option value="Hard">Difficult</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Generate Questions</button>
            </form>

            <div id="generatedQuestions" class="mt-4"></div>
        </div>

        <!-- Manual Tab -->
        <div class="tab-pane fade" id="manual" role="tabpanel">
            <form id="manualQuizForm" method="POST" action="{{ route('quiz.store', $module) }}">
                @csrf
                <div id="questionsContainer">
                    <!-- JS will add question blocks here -->
                </div>

                <button type="button" class="btn btn-success mt-3" id="addQuestionBtn">Add Question</button>
                <button type="submit" class="btn btn-primary mt-3">Save Quiz</button>
            </form>
        </div>
    </div>
</div>

@section('scripts')
    <script>
        tinymce.init({
            selector: '.tinymce-question',
            height: 180,
            menubar: false,
            plugins: 'lists link image code',
            toolbar: 'undo redo | bold italic underline | bullist numlist | link image | code',
            setup: function (editor) {
                editor.on('init', function () {
                    editor.getBody().style.fontSize = '14px';
                });
            }
        });

        let questionCount = 0;
        const OPTION_LETTERS = 'ABCDEFGHIJ'.split(''); // support up to 10 options kung kailangan

        function relabelOptions(questionId) {
            const container = $(`#q${questionId} .options-container`);
            container.find('.option-row').each(function(index) {
                const letter = OPTION_LETTERS[index];
                $(this).find('.option-letter-label').text(letter);
                $(this).find('.option-input').attr('name', `questions[${questionId}][options][${letter}]`);
                $(this).find('.option-radio').val(letter).attr('id', `correct${letter}_${questionId}`);
                $(this).find('.option-radio-label').attr('for', `correct${letter}_${questionId}`).text(letter);
            });
        }

        function addOptionRow(questionId) {
            const container = $(`#q${questionId} .options-container`);
            const currentCount = container.find('.option-row').length;

            if (currentCount >= OPTION_LETTERS.length) {
                alert('Maximum number of options reached.');
                return;
            }

            const row = `
                <div class="col-md-6 option-row">
                    <div class="d-flex align-items-center gap-2">
                        <label class="option-letter-label fw-bold" style="min-width: 20px;"></label>
                        <input type="text" class="form-control option-input" required placeholder="Option text">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-option" title="Remove option">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            container.append(row);
            relabelOptions(questionId);
            rebuildCorrectAnswerRadios(questionId);
        }

        function rebuildCorrectAnswerRadios(questionId) {
            const radioContainer = $(`#q${questionId} .correct-answer-container`);
            const optionCount = $(`#q${questionId} .option-row`).length;
            radioContainer.empty();

            for (let i = 0; i < optionCount; i++) {
                const letter = OPTION_LETTERS[i];
                radioContainer.append(`
                    <div class="form-check">
                        <input class="form-check-input option-radio" type="radio" name="questions[${questionId}][correct]" value="${letter}" id="correct${letter}_${questionId}" required>
                        <label class="form-check-label option-radio-label" for="correct${letter}_${questionId}">${letter}</label>
                    </div>
                `);
            }
        }

        function addQuestionBlock() {
            questionCount++;
            const qid = questionCount;

            let block = `
                <div class="question-block border p-4 mb-4 rounded shadow-sm" id="q${qid}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5>Question ${qid}</h5>
                        <button type="button" class="btn btn-danger btn-sm remove-question" data-target="#q${qid}">
                            <i class="fas fa-trash"></i> Remove Question
                        </button>
                    </div>

                    <textarea name="questions[${qid}][text]" class="tinymce-question form-control mb-3" required></textarea>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Domain / Topic <span class="text-muted small">(optional)</span></label>
                        <input type="text" name="questions[${qid}][domain]" class="form-control" placeholder="e.g., Financial Accounting, Taxation">
                    </div>

                    <label class="form-label fw-medium">Options</label>
                    <div class="row g-3 options-container">
                        <div class="col-md-6 option-row">
                            <div class="d-flex align-items-center gap-2">
                                <label class="option-letter-label fw-bold" style="min-width: 20px;">A</label>
                                <input type="text" name="questions[${qid}][options][A]" class="form-control option-input" required placeholder="Option A">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-option" title="Remove option">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 option-row">
                            <div class="d-flex align-items-center gap-2">
                                <label class="option-letter-label fw-bold" style="min-width: 20px;">B</label>
                                <input type="text" name="questions[${qid}][options][B]" class="form-control option-input" required placeholder="Option B">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-option" title="Remove option">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 option-row">
                            <div class="d-flex align-items-center gap-2">
                                <label class="option-letter-label fw-bold" style="min-width: 20px;">C</label>
                                <input type="text" name="questions[${qid}][options][C]" class="form-control option-input" required placeholder="Option C">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-option" title="Remove option">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 option-row">
                            <div class="d-flex align-items-center gap-2">
                                <label class="option-letter-label fw-bold" style="min-width: 20px;">D</label>
                                <input type="text" name="questions[${qid}][options][D]" class="form-control option-input" required placeholder="Option D">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-option" title="Remove option">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2 add-option" data-target="${qid}">
                        <i class="fas fa-plus"></i> Add Option
                    </button>

                    <div class="mt-3">
                        <label class="form-label fw-medium">Correct Answer</label>
                        <div class="d-flex gap-4 correct-answer-container">
                            <div class="form-check">
                                <input class="form-check-input option-radio" type="radio" name="questions[${qid}][correct]" value="A" id="correctA_${qid}" required>
                                <label class="form-check-label option-radio-label" for="correctA_${qid}">A</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input option-radio" type="radio" name="questions[${qid}][correct]" value="B" id="correctB_${qid}" required>
                                <label class="form-check-label option-radio-label" for="correctB_${qid}">B</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input option-radio" type="radio" name="questions[${qid}][correct]" value="C" id="correctC_${qid}" required>
                                <label class="form-check-label option-radio-label" for="correctC_${qid}">C</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input option-radio" type="radio" name="questions[${qid}][correct]" value="D" id="correctD_${qid}" required>
                                <label class="form-check-label option-radio-label" for="correctD_${qid}">D</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-medium">Points</label>
                        <input type="number" name="questions[${qid}][points]" class="form-control w-25" value="1" min="1" required>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-medium">Explanation / Rationale <span class="text-muted small">(optional, shown after answering)</span></label>
                        <textarea name="questions[${qid}][explanation]" class="form-control" rows="2" placeholder="Explain why this is the correct answer..."></textarea>
                    </div>
                </div>
            `;

            $('#questionsContainer').append(block);

            tinymce.init({
                selector: `#q${qid} .tinymce-question`,
                height: 180,
                menubar: false,
                plugins: 'lists link image code',
                toolbar: 'undo redo | bold italic underline | bullist numlist | link image | code',
            });
        }

        addQuestionBlock();
        $('#addQuestionBtn').click(addQuestionBlock);

        $(document).on('click', '.remove-question', function() {
            $($(this).data('target')).remove();
        });

        $(document).on('click', '.add-option', function() {
            addOptionRow($(this).data('target'));
        });

        $(document).on('click', '.remove-option', function() {
            const questionBlock = $(this).closest('.question-block');
            const questionId = questionBlock.attr('id').replace('q', '');
            const optionRows = questionBlock.find('.option-row');

            if (optionRows.length <= 2) {
                alert('A minimum of 2 options is required.');
                return;
            }

            $(this).closest('.option-row').remove();
            relabelOptions(questionId);
            rebuildCorrectAnswerRadios(questionId);
        });
    </script>
@endsection
</parameter>
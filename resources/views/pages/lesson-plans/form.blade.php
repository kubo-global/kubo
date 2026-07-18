<x-page :title="$plan->exists ? 'Edit lesson plan' : 'New lesson plan'" :wrap="true">
    @if ($errors->any())
            <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded p-3">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
            action="{{ $plan->exists ? route('lesson-plans.update', $plan) : route('lesson-plans.store') }}"
            class="divide-y divide-gray-200">
            @csrf
            @if ($plan->exists)
                @method('PUT')
            @endif

            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                <label for="lesson_date" class="my-auto text-sm font-medium text-gray-500">Date</label>
                <input type="date" id="lesson_date" name="lesson_date" required
                    value="{{ old('lesson_date', $plan->lesson_date?->format('Y-m-d')) }}"
                    class="w-full py-2 pl-2 text-sm border-2 border-gray-200 rounded-md form-input sm:col-span-2">
            </div>

            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                <label for="offering_id" class="my-auto text-sm font-medium text-gray-500">Grade / Class</label>
                <select id="offering_id" name="offering_id" required
                    class="w-full py-2 pl-2 text-sm border-2 border-gray-200 rounded-md form-input sm:col-span-2">
                    <option value="">— pick a class —</option>
                    @foreach ($offerings as $offering)
                        <option value="{{ $offering->id }}"
                            @selected(old('offering_id', $plan->offering_id) == $offering->id)>
                            {{ $offering->grade->name ?? '?' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                <label for="subject_id" class="my-auto text-sm font-medium text-gray-500">Subject</label>
                <select id="subject_id" name="subject_id" required
                    class="w-full py-2 pl-2 text-sm border-2 border-gray-200 rounded-md form-input sm:col-span-2">
                    <option value="">— pick a subject —</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}"
                            @selected(old('subject_id', $plan->subject_id) == $subject->id)>
                            {{ $subject->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                <label for="topic" class="my-auto text-sm font-medium text-gray-500">Topic</label>
                <input type="text" id="topic" name="topic" required maxlength="255"
                    value="{{ old('topic', $plan->topic) }}"
                    class="w-full py-2 pl-2 text-sm border-2 border-gray-200 rounded-md form-input sm:col-span-2">
            </div>

            {{-- Curriculum topic: optional link to the subject's curriculum.
                 Shown only when the chosen subject has topics seeded. Used by
                 the "gate exercises by lesson plan" setting to unlock practice
                 content for the class once a topic has been covered. --}}
            <div x-data="{
                    subject: '{{ old('subject_id', $plan->subject_id) }}',
                    topicsBySubject: @js($topicsBySubject),
                    selectedTopic: '{{ old('curriculum_topic_id', $plan->curriculum_topic_id) }}',
                    get topics() { return this.topicsBySubject[this.subject] || []; }
                 }"
                 x-init="document.getElementById('subject_id').addEventListener('change', e => { subject = e.target.value; selectedTopic = ''; })"
                 x-show="topics.length > 0" x-cloak
                 class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                <label for="curriculum_topic_id" class="my-auto text-sm font-medium text-gray-500">
                    Curriculum topic
                    <span class="block text-xs font-normal text-gray-500">optional — unlocks practice content</span>
                </label>
                <select id="curriculum_topic_id" name="curriculum_topic_id" x-model="selectedTopic"
                    class="w-full py-2 pl-2 text-sm border-2 border-gray-200 rounded-md form-input sm:col-span-2">
                    <option value="">— none —</option>
                    <template x-for="t in topics" :key="t.id">
                        <option :value="t.id" x-text="t.name"></option>
                    </template>
                </select>
            </div>

            @php $rowsFor = ['activities' => 10]; @endphp
            @foreach (['content' => 'Content', 'objectives' => 'Objectives', 'resources' => 'Resources', 'activities' => 'Activities', 'assessment' => 'Assessment', 'conclusion' => 'Conclusion'] as $field => $label)
                <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                    <label for="{{ $field }}" class="my-auto text-sm font-medium text-gray-500">{{ $label }}</label>
                    <textarea id="{{ $field }}" name="{{ $field }}" rows="{{ $rowsFor[$field] ?? 4 }}"
                        class="w-full py-2 pl-2 text-sm border-2 border-gray-200 rounded-md form-input sm:col-span-2">{{ old($field, $plan->{$field}) }}</textarea>
                </div>
            @endforeach

            <div class="flex items-center col-span-3 py-4 mt-4">
                @unless ($plan->exists || app()->environment('production'))
                    {{-- Demo convenience (hidden in production): prefills the form with a sample
                         lesson. Nothing is saved until the teacher reviews and submits. Mirrors
                         the scorebook "Fill example marks (demo)" button. --}}
                    <button type="button" onclick="fillLessonPlanDemo()"
                        class="px-3 py-1.5 text-sm font-medium rounded-lg text-amber-800 bg-amber-50 ring-1 ring-amber-200 hover:bg-amber-100">Fill in demo data</button>
                @endunless
                <div class="ml-auto">
                    <a href="{{ route('lesson-plans.index') }}" class="mr-6 text-gray-600 underline">Cancel</a>
                    <button type="submit"
                        class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900">
                        {{ $plan->exists ? 'Save changes' : 'Create lesson plan' }}
                    </button>
                </div>
            </div>
    </form>

    @unless ($plan->exists || app()->environment('production'))
        <script>
            function fillLessonPlanDemo() {
                var demo = {
                    topic: 'Introduction to Fractions',
                    content: 'A fraction represents a part of a whole. The denominator tells how many equal parts the whole is divided into, and the numerator tells how many of those parts we take. Today we focus on halves, thirds and quarters using everyday examples such as sharing bread and oranges.',
                    objectives: 'By the end of the lesson, pupils should be able to:\n- name the numerator and denominator of a fraction;\n- represent simple fractions with diagrams;\n- compare two fractions that have the same denominator.',
                    resources: 'Chalkboard and chalk, paper circles cut into equal parts, real objects (an orange, a loaf of bread), pupil exercise books.',
                    activities: '1. Recap sharing equally from the previous lesson.\n2. Fold paper circles into halves and quarters and label each part.\n3. In pairs, pupils shade a given fraction on printed shapes.\n4. Class discussion comparing 1/2 and 1/4 using the folded circles.',
                    assessment: 'Pupils complete five short exercises: name the shaded fraction of a shape, and write its numerator and denominator. Observe pair work and question individuals during the folding activity.',
                    conclusion: 'Recap that a fraction has a numerator and a denominator, and that for the same numerator a larger denominator means smaller parts. Preview adding fractions with the same denominator in the next lesson.'
                };

                var offering = document.getElementById('offering_id');
                if (offering && offering.selectedIndex <= 0 && offering.options.length > 1) {
                    offering.selectedIndex = 1;
                }

                var subject = document.getElementById('subject_id');
                if (subject) {
                    var idx = Array.prototype.findIndex.call(subject.options, function (o) {
                        return o.text.trim().toLowerCase() === 'mathematics';
                    });
                    if (idx < 1) { idx = subject.options.length > 1 ? 1 : 0; }
                    subject.selectedIndex = idx;
                    // Let the curriculum-topic list (Alpine) refresh for the chosen subject.
                    subject.dispatchEvent(new Event('change'));
                }

                var date = document.getElementById('lesson_date');
                if (date && !date.value) { date.value = new Date().toISOString().slice(0, 10); }

                document.getElementById('topic').value = demo.topic;
                ['content', 'objectives', 'resources', 'activities', 'assessment', 'conclusion'].forEach(function (field) {
                    var el = document.getElementById(field);
                    if (el) { el.value = demo[field]; }
                });
            }
        </script>
    @endunless
</x-page>

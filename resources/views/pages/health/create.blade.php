@php
    $isEdit = isset($healthReport) && $healthReport;
@endphp
<x-page title="{{ $isEdit ? 'Edit health report' : 'Create a health report' }}">
    <div>
        @if (session()->has('message'))
        <div class="alert alert-success">
            {{ session('message') }}
        </div>
        @endif
    </div>
    @if ($errors->any())
    <div class="max-w-4xl px-4 mx-auto mt-6 md:px-8 xl:px-0">
        <div class="p-3 text-sm text-red-700 border border-red-200 rounded-md bg-red-50">
            <ul class="pl-5 list-disc">
                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    </div>
    @endif
    <div class="flex overflow-hidden bg-white ">
        <!-- Content area -->
        <div class="flex flex-col flex-1">
            <main class="flex-1 overflow-y-auto focus:outline-none" tabindex="0">
                <div class="relative max-w-4xl mx-auto md:px-8 xl:px-0">
                    <div class="mt-10 space-y-6 ">
                        <form method="POST"
                              action="{{ $isEdit ? route('health.update', $healthReport) : route('health.store') }}"
                              class="px-4 divide-y divide-gray-200 sm:px-6 md:px-0">
                            @csrf
                            @if ($isEdit) @method('PUT') @endif
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                                <label for="student"
                                    class="my-auto text-sm font-medium leading-5 text-gray-500">Student</label>
                                <div class="rounded-md">
                                    <select required id="student" name="student"
                                        class="block w-full px-3 py-2 mt-1 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-gray-900 focus:border-gray-900 sm:text-sm">

                                        <option value="{{$student->id}}">{{$student->getFullNameAttribute()}}</option>

                                    </select>
                                </div>
                            </div>
                            <div
                                class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-b sm:border-gray-200">
                                <label for="general-condition"
                                    class="my-auto text-sm font-medium leading-5 text-gray-500">General condition</label>
                                <div class="rounded-md">
                                    <textarea id="general-condition" name="general-condition"
                                        class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">{{ old('general-condition', $healthReport?->general_condition) }}</textarea>
                                </div>
                            </div>
                            <div
                                class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-b sm:border-gray-200">
                                <label for="wounds-bruises"
                                    class="my-auto text-sm font-medium leading-5 text-gray-500">Wounds and bruises</label>
                                <div class="rounded-md">
                                    <textarea id="wounds-bruises" name="wounds-bruises"
                                        class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">{{ old('wounds-bruises', $healthReport?->wound_and_bruise_observations) }}</textarea>
                                </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                                <label for="height" class="my-auto text-sm font-medium leading-5 text-gray-500">Height (cm)</label>
                                <div class="rounded-md">
                                    <input id="height" name="height" type="number" min="40" max="220" step="1" inputmode="numeric"
                                        value="{{ old('height', $healthReport?->height_in_cm) }}"
                                        class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                                </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                                <label for="weight" class="my-auto text-sm font-medium leading-5 text-gray-500">Weight (kg)</label>
                                <div class="rounded-md">
                                    <input id="weight" name="weight" type="number" min="3" max="150" step="0.1" inputmode="decimal"
                                        value="{{ old('weight', $healthReport?->weight_kg) }}"
                                        class="flex w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                                </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                                <label for="teeth-condition"
                                    class="my-auto text-sm font-medium leading-5 text-gray-500">Teeth condition</label>
                                <div class="rounded-md">
                                    @php $v = old('teeth-condition', $healthReport?->teeth_condition); @endphp
                                    <select id="teeth-condition" name="teeth-condition"
                                        class="w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                                        <option value="">Select a condition</option>
                                        <option value="Poor" @selected($v === 'Poor')>Poor</option>
                                        <option value="Good" @selected($v === 'Good')>Good</option>
                                        <option value="Excellent" @selected($v === 'Excellent')>Excellent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                                <label for="eye-condition"
                                    class="my-auto text-sm font-medium leading-5 text-gray-500">Eye condition</label>
                                <div class="rounded-md">
                                    @php $v = old('eye-condition', $healthReport?->eyes_condition); @endphp
                                    <select id="eye-condition" name="eye-condition"
                                        class="w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                                        <option value="">Select a condition</option>
                                        <option value="Poor" @selected($v === 'Poor')>Poor</option>
                                        <option value="Good" @selected($v === 'Good')>Good</option>
                                        <option value="Excellent" @selected($v === 'Excellent')>Excellent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                                <label for="ear-condition"
                                    class="my-auto text-sm font-medium leading-5 text-gray-500">Ear condition</label>
                                <div class="rounded-md">
                                    @php $v = old('ear-condition', $healthReport?->ears_condition); @endphp
                                    <select id="ear-condition" name="ear-condition"
                                        class="w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                                        <option value="">Select a condition</option>
                                        <option value="Poor" @selected($v === 'Poor')>Poor</option>
                                        <option value="Good" @selected($v === 'Good')>Good</option>
                                        <option value="Excellent" @selected($v === 'Excellent')>Excellent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                                <label for="hair-condition"
                                    class="my-auto text-sm font-medium leading-5 text-gray-500">Hair condition</label>
                                <div class="rounded-md">
                                    @php $v = old('hair-condition', $healthReport?->hair_condition); @endphp
                                    <select id="hair-condition" name="hair-condition"
                                        class="w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                                        <option value="">Select a condition</option>
                                        <option value="Poor" @selected($v === 'Poor')>Poor</option>
                                        <option value="Good" @selected($v === 'Good')>Good</option>
                                        <option value="Excellent" @selected($v === 'Excellent')>Excellent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                                <label for="nail-condition"
                                    class="my-auto text-sm font-medium leading-5 text-gray-500">Nail condition</label>
                                <div class="rounded-md">
                                    @php $v = old('nail-condition', $healthReport?->nails_condition); @endphp
                                    <select id="nail-condition" name="nail-condition"
                                        class="w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                                        <option value="">Select a condition</option>
                                        <option value="Poor" @selected($v === 'Poor')>Poor</option>
                                        <option value="Good" @selected($v === 'Good')>Good</option>
                                        <option value="Excellent" @selected($v === 'Excellent')>Excellent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                                <label for="worm-treatment-received"
                                    class="my-auto text-sm font-medium leading-5 text-gray-500">Worm treatment received?</label>
                                <div class="rounded-md">
                                    @php $v = old('worm-treatment-received', $healthReport?->worm_treatment_received); @endphp
                                    <select id="worm-treatment-received" name="worm-treatment-received"
                                        class="w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                                        <option value="" @selected($v === null || $v === '')>Unknown</option>
                                        <option value="1" @selected((string) $v === '1')>Yes</option>
                                        <option value="0" @selected((string) $v === '0')>No</option>
                                    </select>
                                </div>
                            </div>
                            @if (!$isEdit)
                            @php
                                $milestoneFields = [
                                    'menstruated' => ['label' => 'Already menstruated?', 'column' => 'first_menstruated_on', 'recorded_label' => 'First menstruation'],
                                    'hep-a-vax' => ['label' => 'Hep A vaccine received?', 'column' => 'hep_a_received_on', 'recorded_label' => 'Hep A vaccine'],
                                    'polio-vax' => ['label' => 'Polio vaccine received?', 'column' => 'polio_received_on', 'recorded_label' => 'Polio vaccine'],
                                    'tetanus-vax' => ['label' => 'Tetanus vaccine received?', 'column' => 'tetanus_received_on', 'recorded_label' => 'Tetanus vaccine'],
                                    'yellow-fever-vax' => ['label' => 'Yellow fever vaccine received?', 'column' => 'yellow_fever_received_on', 'recorded_label' => 'Yellow fever vaccine'],
                                ];
                            @endphp
                            @foreach ($milestoneFields as $name => $cfg)
                                <div class="py-4 space-y-1 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4">
                                    <label for="{{ $name }}" class="my-auto text-sm font-medium leading-5 text-gray-500">{{ $cfg['label'] }}</label>
                                    @if ($milestone->{$cfg['column']})
                                        <div class="text-sm text-gray-500 sm:col-span-2 my-auto">
                                            ✓ {{ $cfg['recorded_label'] }} recorded on {{ $milestone->{$cfg['column']}->format('d M Y') }}
                                        </div>
                                    @else
                                        <div class="rounded-md">
                                            <select id="{{ $name }}" name="{{ $name }}"
                                                class="w-full py-2 pl-2 space-x-4 text-sm leading-5 text-gray-900 border-2 border-gray-200 border-solid rounded-md form-input sm:text-sm sm:leading-5 sm:mt-0 sm:col-span-2">
                                                <option value="" selected>Unknown</option>
                                                <option value="1">Yes</option>
                                                <option value="0">No</option>
                                            </select>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                            @endif
                            <div class="col-span-4 text-right">
                                <button type="submit"
                                    class="inline-flex justify-center px-4 py-2 mt-4 mb-4 mr-4 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
                                    {{ $isEdit ? 'Save changes' : 'Submit health report' }}
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </main>
        </div>
    </div>
</x-page>
<form class="px-4 divide-y divide-gray-200 sm:px-6 md:px-0">
    <div>
        @if (session()->has('message'))
        <div class="alert alert-success">
            {{ session('message') }}
        </div>
        @endif
    </div>
    <div class="container max-w-4xl px-32 py-4 mx-auto space-y-1 md:px-8 xl:px-0 sm:py-5">
        <div>
            <div>
                <label for="schoolyear" class="block text-sm font-medium leading-6 text-gray-900">Schoolyear</label>
                <select required id="schoolyear" name="schoolyear" wire:model.live="selectedSchoolyear"
                    class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    @foreach ($this->schoolyears as $schoolyear)
                    <option value="{{ $schoolyear->id }}">{{ $schoolyear->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="terms" class="block text-sm font-medium leading-6 text-gray-900">Term</label>
                <select required id="terms" name="terms" wire:model.live="selectedTerm"
                    class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    <option value="0"></option>
                    @foreach ($this->terms as $term)
                    <option value="{{ $term->id }}">{{ $term->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label for="offering" class="block text-sm font-medium leading-6 text-gray-900">Grade</label>
                <select required id="offering" name="offering" wire:model.live="selectedGrade"
                    class="mt-2 block w-full rounded-md border-0 py-1.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    <option value="0"></option>
                    @foreach ($this->offerings as $offering)
                    <option value="{{ $offering->grade->id }}">{{ $offering->grade->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-span-4 mt-4 flex items-center justify-end gap-3 bg-gray-50">
            <button type="button" wire:click="prepareReports"
                class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600">
                Prepare reports
            </button>
            <button type="button" wire:click="generateReport"
                class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-gray-800 border border-transparent rounded-md shadow-sm hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900">
                Generate reports
            </button>
        </div>
        <p class="mt-2 text-right text-xs text-gray-500">
            Prepare reports to type each pupil's conduct and remark first; Generate reports downloads the cards.
        </p>
    </div>
</form>
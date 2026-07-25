<!-- Off-canvas menu for mobile -->
<div x-data="{ isOpen: false }" class="lg:hidden">
    <div x-show="isOpen" x-on:toggle.window="isOpen = !isOpen"
        x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 flex z-40">
        <div class="fixed inset-0">
            <div class="absolute inset-0 bg-gray-600 opacity-75"></div>
        </div>
        <div @click.away="isOpen = false" class="relative flex-1 flex flex-col max-w-xs w-full pt-5 pb-4 bg-white">
            <div class="absolute flex top-0 right-0 -mr-14 p-1 ">
                <button @click="isOpen = false"
                    class=" bg-gray-500 flex items-center justify-center h-8 w-8 rounded-full focus:outline-none focus:bg-gray-600"
                    aria-label="Close sidebar">
                    <svg class="h-6 w-6 text-white" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="mt-5 flex-1 h-0 overflow-y-auto">
                <nav class="px-2">
                    <div class="space-y-1">
                        @include('components.navigation.menu-items')
                    </div>
                </nav>
            </div>
        </div>
    </div>
</div>
<!-- Static sidebar for desktop — collapsible -->
<div class="hidden lg:flex lg:flex-shrink-0">
    <div data-sidebar class="flex flex-col border-r border-gray-200 pt-3 pb-4 bg-gray-100 transition-all duration-200"
         :class="sidebarOpen ? 'w-56' : 'w-12'">
        <div class="flex items-center flex-shrink-0" :class="sidebarOpen ? 'px-4' : 'px-2'">
            <template x-if="sidebarOpen">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center">
                        <svg class="h-7 w-7 fill-current text-indigo-600 flex-shrink-0" viewBox="0 0 240 240" xmlns="http://www.w3.org/2000/svg">
                            <path d="M228.39 76.13l11.24-6.48L125.94 4 1 76.13 38.9 98v87.53l87 50.24 87-50.24V85l-87 50.26-75.77-43.73 64.57-37.28-11.24-6.48L38.9 85l-15.43-8.87L125.94 17l102.45 59.13zM50.14 104.49l75.8 43.84 75.79-43.84V179l-75.79 43.83L50.14 179v-74.51z" />
                        </svg>
                        <h2 class="ml-2 text-gray-900 text-sm leading-5 font-medium uppercase">Kubo</h2>
                    </div>
                    <button type="button" @click="sidebarOpen = false" aria-label="Collapse sidebar" class="text-gray-500 hover:text-gray-600 focus:outline-none p-1">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                </div>
            </template>
            <template x-if="!sidebarOpen">
                <button type="button" @click="sidebarOpen = true" aria-label="Expand sidebar" class="text-gray-500 hover:text-gray-600 focus:outline-none p-1 mx-auto">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </template>
        </div>
        <div class="h-0 flex-1 flex flex-col overflow-y-auto overflow-x-hidden">
            <nav class="mt-7" :class="sidebarOpen ? 'px-3' : 'px-1'">
                <div class="space-y-1">
                    @include('components.navigation.menu-items')
                </div>
            </nav>
        </div>
    </div>
</div>
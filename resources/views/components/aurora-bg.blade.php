{{-- Deep-indigo "aurora" backdrop: a dark base with soft colour glows. Shared by
     the student and staff login so both wear the same brand night sky.

     Usage: drop as the first child of a `relative overflow-hidden min-h-screen`
     container and give the real content `relative z-10` so it sits above. --}}
<style>
    /* Keep the overscroll gutter dark instead of the app's default white, so
       rubber-band scrolling past the aurora never flashes a white line. */
    html, body { background-color: #0f1030; }
</style>
<div aria-hidden="true" class="absolute inset-0 overflow-hidden pointer-events-none bg-[#0f1030]">
    <div class="absolute -top-40 -left-32 w-[38rem] h-[38rem] rounded-full bg-indigo-500 opacity-40 blur-[120px]"></div>
    <div class="absolute -bottom-48 -right-24 w-[40rem] h-[40rem] rounded-full bg-fuchsia-600 opacity-30 blur-[130px]"></div>
    <div class="absolute top-1/3 right-1/4 w-[26rem] h-[26rem] rounded-full bg-teal-400 opacity-20 blur-[120px]"></div>
</div>

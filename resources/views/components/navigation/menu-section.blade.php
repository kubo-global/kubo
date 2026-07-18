{{-- Sidebar group divider. Always shows a separating line; the uppercase label
     appears only when the sidebar is expanded (collapsed = icons only). --}}
<div class="pt-3 mt-2 border-t border-gray-200">
  @if(!empty($label))
    <p x-show="sidebarOpen" x-transition.opacity
       class="px-2 mb-1 text-[10px] font-semibold tracking-wider text-gray-600 uppercase">{{ $label }}</p>
  @endif
</div>

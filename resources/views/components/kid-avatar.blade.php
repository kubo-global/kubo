@props(['seed' => 0, 'label' => '?', 'bg' => null])
@php($bgColor = $bg ?: \App\Support\AvatarColor::forSeed((int) $seed)[0])
@php($ink = \App\Support\AvatarColor::inkFor($bgColor))
{{-- A colour-and-initial identity token — the same visual language for a pupil on
     the roster and a grade badge, so students navigate by colour, not just text. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center flex-shrink-0 font-extrabold rounded-full leading-none']) }}
      style="background-color: {{ $bgColor }}; color: {{ $ink }};">
    {{ $label }}
</span>

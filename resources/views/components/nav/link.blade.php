@props([
    'href',
    'label',
    'icon',
    'active' => false,
])

<a
    href="{{ $href }}"
    title="{{ $label }}"
    aria-label="{{ $label }}"
    @if ($active) aria-current="page" @endif
    @class([
        'ops-nav-link group flex items-center justify-start gap-3 rounded-md border-l-2 border-transparent px-3 py-2.5 text-sm font-medium transition-colors duration-150',
        'ops-nav-link--active' => $active,
        'ops-nav-link--idle' => ! $active,
    ])
    data-sidebar-link
>
    <x-nav.icon :name="$icon" class="h-5 w-5" />
    <span class="min-w-0 truncate" data-sidebar-label>{{ $label }}</span>
</a>

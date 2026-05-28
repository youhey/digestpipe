<header
    @class([
        'fi-header',
        'fi-header-has-breadcrumbs' => $breadcrumbs,
    ])
>
    <div>
        @if ($breadcrumbs)
            <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
        @endif

        <h1 class="fi-header-heading">
            {{ $heading }}
        </h1>

        @if ($actions)
            <div class="mt-3">
                <x-filament::actions :actions="$actions" />
            </div>
        @endif
    </div>
</header>

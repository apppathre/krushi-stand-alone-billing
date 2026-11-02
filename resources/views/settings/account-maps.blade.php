<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">{{ __('Account Map') }}</h1>
    </x-slot>

    <form method="POST" action="{{ route('settings.account-maps.update') }}" class="card card-body max-w-4xl space-y-4">
        @csrf @method('PUT')
        <p class="text-sm text-ink-soft">{{ __('Default ledgers used when invoices, purchases and payments are posted to the books.') }}</p>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($fields as $column => $label)
                @php($options = $column === 'walk_in_ledger_id' ? $parties : $accounts)
                <x-select-field :name="$column" :label="__($label)" :value="$map->$column" :placeholder="__('— not set —')"
                    :options="$options->mapWithKeys(fn ($l) => [$l->id => $l->display_name.' ('.__(ucwords(str_replace('_', ' ', $l->type))).')'])->all()" />
            @endforeach
        </div>

        @can('menu', ['settings_account_maps', 'edit'])
            <div><button class="btn-primary">{{ __('Save') }}</button></div>
        @endcan
    </form>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h1 class="page-title">
            {{ $item->exists ? __('Edit :item', ['item' => __($c['singular'])]) : __('Add :item', ['item' => __($c['singular'])]) }}
        </h1>
    </x-slot>

    <form method="POST" action="{{ $item->exists ? route($c['route'].'.update', $item) : route($c['route'].'.store') }}" class="card card-body max-w-2xl space-y-4">
        @csrf
        @if ($item->exists) @method('PUT') @endif

        @foreach ($c['fields'] as $f)
            @php($type = $f['type'] ?? 'text')
            @if ($type === 'checkbox')
                <x-check :name="$f['name']" :label="__($f['label'])" :checked="$item->{$f['name']}" />
            @else
                <x-field :name="$f['name']" :label="__($f['label'])" :type="$type" :value="$item->{$f['name']}" :required="$f['required'] ?? false" />
            @endif
        @endforeach

        <div class="flex gap-2 pt-2">
            <button class="btn-primary">{{ __('Save') }}</button>
            <a href="{{ route($c['route'].'.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-app-layout>

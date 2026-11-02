@props(['dark' => false])

<div {{ $attributes->merge(['class' => 'lang-switch inline-flex overflow-hidden rounded-lg border text-xs font-bold '.($dark ? 'border-white/30' : 'border-sage-dark')]) }}>
    @foreach (['en' => 'EN', 'mr' => 'मर'] as $code => $label)
        <a href="{{ route('locale.switch', $code) }}"
           class="lang-switch__opt px-2.5 py-1 transition {{ app()->getLocale() === $code
                ? ($dark ? 'bg-white text-leaf-dark' : 'bg-leaf text-white')
                : ($dark ? 'text-white/80 hover:bg-white/10' : 'bg-white text-leaf-dark hover:bg-sage') }}"
           title="{{ $code === 'en' ? 'English' : 'मराठी' }}">{{ $label }}</a>
    @endforeach
</div>

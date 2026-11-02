{{-- TinyMCE terms editor. Saved on the voucher; printed on invoice (not hardcoded). --}}
@php
    $field = $name ?? 'terms';
    $fieldId = $id ?? ('f-'.$field);
    $rows = $rows ?? 6;
    $editorValue = $value ?? '';
@endphp
<div class="{{ $class ?? 'card card-body space-y-2' }}" data-terms-editor>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <label class="label mb-0" for="{{ $fieldId }}">{{ $label ?? __('Terms & conditions') }}</label>
        <p class="text-xs text-ink-soft">{{ $hint ?? __('Printed on the invoice. Change for this bill only — shop default is under Company settings.') }}</p>
    </div>
    <textarea id="{{ $fieldId }}" name="{{ $field }}" rows="{{ $rows }}" class="input font-mono text-sm"
              data-tinymce-terms>{{ old($field, $editorValue) }}</textarea>
    @error($field) <p class="error-text">{{ $message }}</p> @enderror
</div>

@once
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/tinymce@7.5.1/tinymce.min.js" referrerpolicy="origin"></script>
        <script>
            (function () {
                function initTermsEditors(root) {
                    if (!window.tinymce) return;
                    (root || document).querySelectorAll('textarea[data-tinymce-terms]').forEach(function (el) {
                        if (el.dataset.tmBound === '1') return;
                        el.dataset.tmBound = '1';
                        tinymce.init({
                            target: el,
                            menubar: false,
                            branding: false,
                            promotion: false,
                            height: 180,
                            plugins: 'lists link autoresize',
                            toolbar: 'undo redo | bold italic underline | bullist numlist | removeformat',
                            content_style: 'body{font-family:system-ui,sans-serif;font-size:13px;line-height:1.45}',
                            setup: function (editor) {
                                var form = el.closest('form');
                                if (form) {
                                    form.addEventListener('submit', function () {
                                        editor.save();
                                    });
                                }
                            },
                        });
                    });
                }
                document.addEventListener('DOMContentLoaded', function () {
                    initTermsEditors(document);
                });
                window.KrushiTermsEditor = { init: initTermsEditors };
            })();
        </script>
    @endpush
@endonce

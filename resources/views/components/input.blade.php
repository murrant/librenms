@props([
    'id',
    'label' => null,
    'type' => 'text',
    'icon' => null
])

@if($label)<label for="{{ $id }}" class="tw-block tw-mb-1 tw-mt-2 tw-font-medium tw-text-gray-900 dark:tw-text-white">{{ $label }}</label>@endif
@if($icon)
    <div class="tw-relative tw-text-gray-400 focus-within:tw-text-gray-600 tw-inline-block">
    <i class="fa {{ $icon }} tw-text-2xl tw-pointer-events-none tw-w-10 tw-h-10 tw-absolute tw-top-1/2 tw-transform tw--translate-y-1/2 tw-left-3"></i>
@endif
<input type="{{ $type }}"
       {{ $attributes->merge(['id' => $id, 'class' => 'tw-mb-2 tw-bg-gray-50 tw-border tw-border-gray-300 tw-text-gray-900 tw-rounded-lg tw-block tw-w-full tw-p-2.5 dark:tw-bg-gray-700 dark:tw-border-gray-600 dark:tw-placeholder-gray-400 dark:tw-text-white dark:tw-focus:border-blue-500' . ($icon ? ' tw-pl-11' : '')]) }}
>
@if($icon)
</div>
@endif

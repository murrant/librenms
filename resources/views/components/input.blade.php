<div {{ $attributes->except(['value', 'placeholder']) }}>
    @if($label)
        <label for="{{ $name }}" class="tw-mb-1 tw-text-xs sm:tw-text-sm tw-tracking-wide tw-text-gray-600">{{ $label }}</label>
    @endif
    <input type="{{ $type }}"
            class="tw-text-sm sm:tw-text-base tw-relative tw-w-full tw-border tw-rounded tw-placeholder-gray-400 focus:tw-border-indigo-400 focus:tw-outline-none tw-p-2"
            {{ $attributes->only(['value', 'placeholder']) }}
    >
    <div class="tw-flex tw-items-center tw-font-medium tw-tracking-wide tw-text-red-500 tw-text-xs tw-mt-1 tw-ml-1">
        error message
    </div>
</div>

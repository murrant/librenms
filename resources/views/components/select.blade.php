@props([
'name' => '',
'label' => '',
'options' => [],
])


<div {{ $attributes->except(['name', 'placeholder']) }}>
    @if($label)
        <label for="{{ $name }}" class="tw-mb-1 tw-text-xs sm:tw-text-sm tw-tracking-wide tw-text-gray-600">{{ $label }}</label>
    @endif
        <select class="
      tw-block
      tw-px-3
      tw-py-1.5
      tw-text-base
      tw-font-normal
      tw-text-gray-700
      tw-bg-white tw-bg-clip-padding tw-bg-no-repeat
      tw-border tw-border-solid tw-border-gray-300
      tw-rounded
      tw-transition
      tw-ease-in-out
      tw-m-0
      focus:tw-text-gray-700 focus:tw-bg-white focus:tw-border-blue-600 focus:tw-outline-none
        " name="device" {{ $attributes->only(['name', 'placeholder']) }}>
            @foreach($options as $value => $text)
                <option value="{{ $value }}">{{ $text }}</option>
            @endforeach
        </select>
    <div class="tw-flex tw-items-center tw-font-medium tw-tracking-wide tw-text-red-500 tw-text-xs tw-mt-1 tw-ml-1">
        error message
    </div>
</div>

@once
    <script>
        function lnmsSelect(config) {

        }
    </script>
@endonce

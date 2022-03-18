<div x-data="{selectedButton: null, first: null, last: null}"
     x-modelable="selectedButton"
     {{ $attributes }}
     >
    <div class="tw-flex tw-items-center tw-justify-center">
        <div class="tw-inline-flex" role="group">
            @foreach($buttons as $key => $name)
                <button type="button"
                        x-on:click="selectedButton = {{ is_int($key) ? $key : "'$key'" }}"
                        class="
        tw-px-6
        tw-py-2
        tw-border-t tw-border-b tw-border-r tw-border-gray-200 dark:tw-border-dark-gray-200
        tw-text-gray-500 dark:tw-text-gray-400
        tw-font-medium
        tw-leading-tight
        tw-bg-gray-200 dark:tw-bg-dark-gray-200
        hover:tw-bg-opacity-5
        focus:tw-outline-none focus:tw-ring-0
        tw-transition tw-duration-150 tw-ease-in-out
        @if ($loop->first) tw-border-l tw-rounded-l @endif
        @if ($loop->last) tw-rounded-r @endif
      "
                        x-bind:class="{
                        'tw-text-gray-700 dark:tw-text-dark-white-100 tw-shadow-inner tw-bg-opacity-5': {{ is_int($key) ? $key : "'$key'" }} === selectedButton
}"
                >{{ $name }}</button>
                @endforeach
            </template>
    </div>
</div>

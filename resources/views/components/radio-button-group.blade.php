<div x-data="{selectedButton: null, first: null, last: null}"
     x-modelable="selectedButton"
     x-init="
     $nextTick(() => {
         if (typeof buttons === 'object' && buttons.length > 0) {
            const keys = Object.keys(buttons);
            first = keys[0];
            last = keys[keys.length];
         } else {
            console.log('You must define buttons object')
         }
     })
     "
     {{ $attributes }}
     >
    <div class="tw-flex tw-items-center tw-justify-center">
        <div class="tw-inline-flex" role="group">
            <template x-for="(name, key) in buttons">
                <button type="button"
                        x-text="name"
                        x-on:click="selectedButton = key"
                        class="
        tw-rounded-l
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
      "
                        x-bind:class="{
                        'tw-border-l tw-rounded-l': key === first,
                        'tw-rounded-r': key === last,
                        'tw-text-gray-700 dark:tw-text-dark-white-100 tw-shadow-inner tw-bg-opacity-5': key === selectedButton
}"
                ></button>
            </template>
    </div>
</div>

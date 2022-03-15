<div x-data="{selectedButton: null, buttonNames: ['Left', 'Middle', 'Right']}" x-modelable="selectedButton">
    <div class="tw-flex tw-items-center tw-justify-center">
        <div class="tw-inline-flex" role="group">
            <template x-for="(name, index) in buttonNames">
                <button type="button"
                        x-text="name"
                        x-on:click="selectedButton = index"
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
                        'tw-border-l tw-rounded-l': index === 0,
                        'tw-rounded-r': index + 1 === buttonNames.length,
                        'tw-text-gray-700 dark:tw-text-dark-white-100 tw-shadow-inner tw-bg-opacity-5': index === selectedButton
}"
                ></button>
            </template>
    </div>
</div>

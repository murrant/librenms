<div x-data="{enabled : false}"
     x-bind:class="{ 'tw-bg-green-500': enabled}"
     class="tw-w-20 tw-h-9 tw-inline-flex tw-items-center tw-bg-gray-300 tw-rounded-full tw-mx-1 tw-px-1 tw-transition-colors tw-duration-300"
     x-on:click="enabled = ! enabled">
    <div class="tw-bg-white tw-w-7 tw-h-7 tw-rounded-full tw-shadow-md tw-transform tw-transition tw-ease-in-out tw-duration-300" x-bind:class="{ 'tw-translate-x-11': enabled}"></div>
</div>

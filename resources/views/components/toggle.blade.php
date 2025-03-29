@props(['label' => ''])

<!-- Toggle -->
<div x-data="{ isLive : false }" class="tw:flex">
    @if($label)
      <span class="tw:flex tw:flex-col">
        <span
            class="tw:mr-2 tw:font-medium tw:text-gray-900"
            id="availability-label"
        >{{ $label }}
        </span>
      </span>
      @endif
      <button
          type="button"
          @click="isLive = !isLive"
          :class="isLive ? 'tw:bg-emerald-500' : 'tw:bg-gray-200'"
          class="tw:relative tw:inline-flex tw:flex-shrink-0 tw:h-10.5 tw:w-20 tw:border-2 tw:border-transparent tw:rounded-full tw:cursor-pointer tw:transition-colors tw:ease-in-out tw:duration-200 tw:focus:outline-none tw:focus:ring-2 tw:focus:ring-offset-2 tw:focus:ring-indigo-500"
          role="switch"
          aria-checked="false"
          aria-labelledby="availability-label"
          aria-describedby="availability-description"
      >
      <span
          aria-hidden="true"
          :class="isLive ? 'tw:translate-x-9' : 'tw:translate-x-0'"
          class="tw:pointer-events-none tw:inline-block tw:h-9 tw:w-9 tw:rounded-full tw:bg-white tw:shadow tw:transform tw:ring-0 tw:transition tw:ease-in-out tw:duration-200"
      ></span>
    </button>
</div>

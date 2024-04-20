<div x-data="{expand: false, overflowed: false, expand() { this.expand = true; this.overflowed=false; $refs.container.style.height = 'auto' }}">
    <div  x-ref="container" class="tw-overflow-y-hidden" style="height:{{ $attributes->get('height') }}" x-init="overflowed = $el.offsetHeight < $el.scrollHeight">{{ $slot }}</div>
    <div x-cloak class="tw-cursor-pointer tw-leading-6" x-on:click="expand()" x-show="overflowed" title="{{ __('More') }}">...</div>
</div>

<?php
    switch ($envelope->getType()) {
        case 'success':
            $title = 'Success';
            $textColor = 'tw-text-green-600';
            $backgroundColor = 'tw-bg-green-600';
            $progressBackgroundColor = 'tw-bg-green-100';
            $borderColor = 'tw-border-green-600';
            $icon = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="check tw-w-5 tw-h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
            break;
        case 'error':
            $title = 'Error';
            $textColor = 'tw-text-red-600';
            $backgroundColor = 'tw-bg-red-600';
            $progressBackgroundColor = 'tw-bg-red-100';
            $borderColor = 'tw-border-red-600';
            $icon = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="x tw-w-5 tw-h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
            break;
        case 'warning':
            $title = 'Warning';
            $textColor = 'tw-text-yellow-600';
            $backgroundColor = 'tw-bg-yellow-600';
            $progressBackgroundColor = 'tw-bg-yellow-100';
            $borderColor = 'tw-border-yellow-600';
            $icon = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="exclamation tw-w-5 tw-h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
            break;
        case 'info':
        default:
            $title = 'Info';
            $textColor = 'tw-text-blue-600';
            $backgroundColor = 'tw-bg-blue-600';
            $progressBackgroundColor = 'tw-bg-blue-100';
            $borderColor = 'tw-border-blue-600';
            $icon = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" class="exclamation-circle tw-w-5 tw-h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            break;
    }
?>
<div class="tw-bg-white tw-shadow-lg tw-border-l-4 tw-mt-2 tw-cursor-pointer {{ $borderColor }}">
    <div class="tw-flex tw-items-center tw-px-2 tw-py-3 tw-rounded-lg tw-shadow-lg tw-overflow-hidden">
        <div class="tw-inline-flex tw-items-center {{ $backgroundColor }} tw-p-2 tw-text-white tw-text-sm tw-rounded-full tw-flex-shrink-0">
            {!! $icon !!}
        </div>
        <div class="tw-ml-4 tw-w-0 tw-flex-1">
            <p class="tw-text-xl tw-leading-5 tw-font-medium tw-capitalize {{ $textColor }}">
                {{ $envelope->getTitle() ?: $title }}
            </p>
            <p class="tw-mt-1 tw-text-base tw-leading-5 tw-text-gray-500">
                {!! clean(stripslashes($envelope->getMessage())) !!}
            </p>
        </div>
    </div>
    <div class="tw-h-0.5 tw-flex {{ $progressBackgroundColor }}">
        <span class="flasher-progress {{ $backgroundColor }}"></span>
    </div>
</div>

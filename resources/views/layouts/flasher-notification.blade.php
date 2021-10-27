<?php
    switch ($envelope->getType()) {
        case 'success':
            $title = 'Success';
            $textColor = 'tw-text-green-600';
            $backgroundColor = 'tw-bg-green-600';
            $progressBackgroundColor = 'tw-bg-green-100';
            $borderColor = 'tw-border-green-600';
            $class='flasher-success';
            break;
        case 'error':
            $title = 'Error';
            $textColor = 'tw-text-red-600';
            $backgroundColor = 'tw-bg-red-600';
            $progressBackgroundColor = 'tw-bg-red-100';
            $borderColor = 'tw-border-red-600';
            $class = 'flasher-error';
            break;
        case 'warning':
            $title = 'Warning';
            $textColor = 'tw-text-yellow-600';
            $backgroundColor = 'tw-bg-yellow-600';
            $progressBackgroundColor = 'tw-bg-yellow-100';
            $borderColor = 'tw-border-yellow-600';
            $class = 'flasher-warning';
            break;
        case 'info':
        default:
            $title = 'Info';
            $textColor = 'tw-text-blue-600';
            $backgroundColor = 'tw-bg-blue-600';
            $progressBackgroundColor = 'tw-bg-blue-100';
            $borderColor = 'tw-border-blue-600';
            $class='flasher-info';
            break;
    }
?>
<div class="{{ $class }} tw-bg-white tw-opacity-80 hover:tw-opacity-100 tw-rounded-md tw-shadow-lg hover:tw-shadow-xl tw-border-l-8 tw-mt-2 tw-cursor-pointer {{ $borderColor }}">
    <div class="tw-pl-20 tw-py-4 tw-pr-2 tw-overflow-hidden">
        <div class="tw-text-xl tw-leading-7 tw-font-semibold tw-capitalize {{ $textColor }}">
            {{ $envelope->getTitle() ?: $title }}
        </div>
        <div class="tw-mt-1 tw-text-base tw-leading-5 tw-text-gray-500">
            {!! clean(stripslashes($envelope->getMessage()), 'notifications') !!}
        </div>
    </div>
    <div class="tw-h-1 tw-flex tw-mr-1 {{ $progressBackgroundColor }}">
        <span class="flasher-progress {{ $backgroundColor }}"></span>
    </div>
</div>

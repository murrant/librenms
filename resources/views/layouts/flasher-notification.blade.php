<?php
    switch ($envelope->getType()) {
        case 'success':
            $title = 'Success';
            $textColor = 'tw-text-green-600';
            $backgroundColor = 'tw-bg-green-600';
            $progressBackgroundColor = 'tw-bg-green-100';
            $borderColor = 'tw-border-green-600';
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path d="M16 0A16 16 0 0 0 0 16a16 16 0 0 0 16 16 16 16 0 0 0 16-16A16 16 0 0 0 16 0zm7 10a1 1 0 0 1 .707.293 1 1 0 0 1 0 1.414l-10 10a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 0-1.414 1 1 0 0 1 1.414 0L13 19.586l9.293-9.293A1 1 0 0 1 23 10z" fill="currentColor" /></svg>';
            break;
        case 'error':
            $title = 'Error';
            $textColor = 'tw-text-red-600';
            $backgroundColor = 'tw-bg-red-600';
            $progressBackgroundColor = 'tw-bg-red-100';
            $borderColor = 'tw-border-red-600';
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path d="M16 0A16 16 0 0 0 0 16a16 16 0 0 0 16 16 16 16 0 0 0 16-16A16 16 0 0 0 16 0zm-6 9a1 1 0 0 1 .707.293L16 14.586l5.293-5.293a1 1 0 0 1 1.414 0 1 1 0 0 1 0 1.414L17.414 16l5.293 5.293a1 1 0 0 1 0 1.414 1 1 0 0 1-1.414 0L16 17.414l-5.293 5.293a1 1 0 0 1-1.414 0 1 1 0 0 1 0-1.414L14.586 16l-5.293-5.293a1 1 0 0 1 0-1.414A1 1 0 0 1 10 9z" fill="currentColor" /></svg>';
            break;
        case 'warning':
            $title = 'Warning';
            $textColor = 'tw-text-yellow-600';
            $backgroundColor = 'tw-bg-yellow-600';
            $progressBackgroundColor = 'tw-bg-yellow-100';
            $borderColor = 'tw-border-yellow-600';
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path d="M16 0A16 16 0 0 0 0 16a16 16 0 0 0 16 16 16 16 0 0 0 16-16A16 16 0 0 0 16 0zm0 6.156c1.016 0 2.032.49 2.598 1.469l6.927 12c1.131 1.958-.336 4.5-2.597 4.5H9.072c-2.261 0-3.728-2.542-2.597-4.5l6.927-12c.566-.979 1.582-1.469 2.598-1.469zm0 1.938c-.33 0-.66.177-.865.531l-6.93 12c-.409.708.049 1.5.867 1.5h13.856c.818 0 1.276-.792.867-1.5l-6.93-12c-.204-.354-.534-.531-.865-.531zm0 4.031a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1 1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1zm0 6h.01a1 1 0 0 1 1 1 1 1 0 0 1-1 1H16a1 1 0 0 1-1-1 1 1 0 0 1 1-1z" fill="currentColor"/></svg>';
            break;
        case 'info':
        default:
            $title = 'Info';
            $textColor = 'tw-text-blue-600';
            $backgroundColor = 'tw-bg-blue-600';
            $progressBackgroundColor = 'tw-bg-blue-100';
            $borderColor = 'tw-border-blue-600';
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><path d="M16 0A16 16 0 0 0 0 16a16 16 0 0 0 16 16 16 16 0 0 0 16-16A16 16 0 0 0 16 0zm0 6c5.511 0 10 4.489 10 10s-4.489 10-10 10S6 21.511 6 16 10.489 6 16 6zm0 2c-4.43 0-8 3.57-8 8s3.57 8 8 8 8-3.57 8-8-3.57-8-8-8zm0 3a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1 1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1zm0 8h.01a1 1 0 0 1 1 1 1 1 0 0 1-1 1H16a1 1 0 0 1-1-1 1 1 0 0 1 1-1z" fill="currentColor" /></svg>';
            break;
    }
?>
<div class="tw-bg-white opacity-80 tw-rounded-md tw-shadow-lg tw-border-l-8 tw-mt-2 tw-cursor-pointer {{ $borderColor }}">
    <div class="tw-flex tw-items-center tw-px-2 tw-py-3 tw-rounded-lg tw-shadow-lg tw-overflow-hidden">
        <div class="tw-inline-flex tw-items-center tw-p-2 tw-text-white tw-rounded-full tw-flex-shrink-0 {{ $textColor }}" style="width: 40px; height: 32px">
            {!! $icon !!}
        </div>
        <div class="tw-ml-4 tw-w-0 tw-flex-1">
            <p class="tw-text-xl tw-leading-7 tw-font-semibold tw-capitalize {{ $textColor }}">
                {{ $envelope->getTitle() ?: $title }}
            </p>
            <p class="tw-mt-1 tw-text-base tw-leading-5 tw-text-gray-500">
                {!! clean(stripslashes($envelope->getMessage())) !!}
            </p>
        </div>
    </div>
    <div class="tw-h-1 tw-flex {{ $progressBackgroundColor }}">
        <span class="flasher-progress {{ $backgroundColor }}"></span>
    </div>
</div>

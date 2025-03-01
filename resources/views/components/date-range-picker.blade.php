@props([
    'start' => date('m/d/Y', \LibreNMS\Util\Time::parseAt('-2d')),
    'end' => date('m/d/Y', time()),
])

<div class="date-range-picker tw-inline-block tw-cursor-pointer tw-border-2 tw-border-black tw-rounded-lg tw-p-2 tw-whitespace-nowrap"
     {{ $attributes }}
     data-start-date="{{ $start }}" data-end-date="{{ $end }}"
>
    <i class="fa fa-calendar"></i>
    <span>{{ $start }} - {{ $end }}</span> <i class="fa fa-caret-down"></i>
</div>

@once
    @push('scripts')
        <script>
            $('.date-range-picker').daterangepicker({
                "showDropdowns": true,
                "timePicker": true,
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },
                "linkedCalendars": false,
                "showCustomRangeLabel": false,
                "alwaysShowCalendars": true
            }, function (start, end, label) {
                this.element.find('span').text(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
                console.log('New date range selected: ' + start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD') + ' (predefined range: ' + label + ')');
            });
        </script>
    @endpush

    @push('javascript')
        <script type="text/javascript" src="{{ asset('js/daterangepicker.js') }}"></script>
    @endpush

    @push('styles')
        <link rel="stylesheet" type="text/css" href="{{ asset('css/daterangepicker.css') }}"/>
    @endpush
@endonce

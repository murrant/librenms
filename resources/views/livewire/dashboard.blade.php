<div class="grid-stack">
    @foreach($widgets as $widget)
        @include("device.tabs.overview.$widget-stub")
    @endforeach
</div>

@push('styles')
    @livewireStyles
    <link rel="stylesheet" href="{{ asset('css/gridstack.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/gridstack-extra.min.css') }}">
    <style>
        /*.grid-stack-item {*/
        /*    border: 1px solid #000000;*/
        /*    border-radius: 2px;*/
        /*}*/
    </style>
@endpush

@push('javascript')
    <script src="{{ asset('js/gridstack-h5.js') }}"></script>
@endpush

@push('scripts')
    @livewireScripts
    <script>
        let grid = GridStack.init({
            disableOneColumnMode: true, // will manually do 1 column
            // float: true,
            draggable: {
                handle: '.dash-widget-header',
            }
        });

        function resizeGrid() {
            let width = document.body.clientWidth;
            let layout = 'moveScale';
            let columns = 12;
            let maxColumns = 2;

            if (width < 700) {
                columns = 1
            } else if (width < 850) {
                columns = 3
            } else if (width < 950) {
                columns = 6
            } else if (width < 1100) {
                columns = 8
            }
            columns = Math.min(columns, maxColumns)

            grid.column(columns, layout);
        }

        resizeGrid();
        grid.on('change', function(event, items) {
            Livewire.emit('widgetsChanged', event, items);
        });

        window.addEventListener('resize', function() {resizeGrid()});

    </script>
@endpush



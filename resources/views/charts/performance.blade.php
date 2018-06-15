@extends('layouts.librenmsv1')

@section('style')
    <link href="//cdnjs.cloudflare.com/ajax/libs/dygraph/2.1.0/dygraph.min.css" rel="stylesheet" type="text/css" />
@endsection

@section('content')
      <div>{!! $chart->container() !!}</div>
    <script src=//cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.1/Chart.min.js charset=utf-8></script>
    <script src=//cdnjs.cloudflare.com/ajax/libs/dygraph/2.1.0/dygraph.min.js charset=utf-8></script>
    {{--<script src=//cdnjs.cloudflare.com/ajax/libs/highcharts/6.0.6/highcharts.js charset=utf-8></script>--}}
    {{--<script src=//cdn.jsdelivr.net/npm/fusioncharts@3.12.2/fusioncharts.js charset=utf-8></script>--}}
    {{--<script src=//cdnjs.cloudflare.com/ajax/libs/echarts/4.0.2/echarts-en.min.js charset=utf-8></script>--}}
     {!! $chart->script() !!}

@endsection

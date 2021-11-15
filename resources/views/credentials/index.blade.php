@extends('layouts.librenmsv1')

@section('title', trans('credentials.title'))

@section('content')
    <ul>
        @foreach($credentials as $credential)
            <li>{{ $credential->credential_type }}: {{ $credential->description }} {{ $credential->default ? '*' : '' }}</li>
        @endforeach
    </ul>
@endsection

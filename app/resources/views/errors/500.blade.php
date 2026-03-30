@extends('errors.layout')

@section('title', 'Serverio klaida')

@section('content')
    <p class="code">500</p>
    <h1>Įvyko klaida</h1>
    <p>Bandykite vėliau. Jei problema kartojasi, praneškite administratoriui.</p>
    <a href="{{ url('/') }}">Į pradžią</a>
@endsection

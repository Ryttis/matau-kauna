@extends('errors.layout')

@section('title', 'Puslapis nerastas')

@section('content')
    <p class="code">404</p>
    <h1>Puslapis nerastas</h1>
    <p>Tokio adreso nėra arba turinys buvo perkeltas.</p>
    <a href="{{ url('/') }}">Į pradžią</a>
@endsection

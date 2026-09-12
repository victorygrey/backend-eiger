@extends('layouts.admin')

@section('title', 'Tambah Perangkat Table / Display')
@section('page-title', 'Tambah Interactive Table')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.tablets.index') }}">Interactive Table & Display</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.tablets.store') }}">
    @csrf
    @include('admin.tablets._form')
</form>
@endsection

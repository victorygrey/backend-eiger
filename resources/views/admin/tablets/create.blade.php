@extends('layouts.admin')

@section('title', 'Tambah Tablet')
@section('page-title', 'Tambah Tablet')
@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('admin.tablets.index') }}">Interactive Tablets</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.tablets.store') }}">
    @csrf
    @include('admin.tablets._form')
</form>
@endsection

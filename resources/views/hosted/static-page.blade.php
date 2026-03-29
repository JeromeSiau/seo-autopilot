@extends('hosted.layout')

@section('content')
    <section class="page-shell">
        <div class="container">
            <article class="content">
                <h1 class="page-title">{{ $pageTitle }}</h1>
                {!! $pageContentHtml !!}
            </article>
        </div>
    </section>
@endsection

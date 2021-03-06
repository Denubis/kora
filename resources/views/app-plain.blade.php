<!doctype html>

<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <title>kora - {{ $page_title }}</title>

    <link rel="stylesheet" href="{{url('assets/css/app.css')}}">
    <link rel="stylesheet" type="text/css" href="{{ url('assets/css/vendor/leaflet/leaflet.css') }}"/>
</head>
<body class="single-resource-body {{ str_hyphenated($page_class) }}-body">

<div class="single-resource {{ str_hyphenated($page_class) }}">
    @yield('body')
</div>

@yield('javascripts')
</body>
</html>

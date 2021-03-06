@extends('app', ['page_title' => (Auth::user()->id == $user->id ? 'My Profile' : $user->username), 'page_class' => 'user-profile'])

@section('aside-content')
  @include('partials.sideMenu.dashboard', ['openDashboardDrawer' => false, 'openProjectDrawer' => false])
@stop

@section('header')
    @include('partials.user.profile.head')
@stop

@section('body')
        <section class="permissions center page-section page-section-js {{($section == 'permissions' ? 'active' : '')}}" id="permissions">
        <div class="section-filters mt-xxxl">
            <a href="#projects" class="filter-link select-content-section-js underline-middle underline-middle-hover">Projects</a>
            <a href="#forms" class="filter-link select-content-section-js underline-middle underline-middle-hover">Forms</a>
        </div>
        <div class="content-section content-section-js" id="projects">
          <div class="content-sections-scroll">
            @if (!$user->admin)
                <div class="my-xl">
                    <p>{{ (Auth::user()->id == $user->id ? 'You have' : $user->preferences['first_name'] . ' has') }} access to the following projects...</p>
                </div>
                @foreach ($projects as $index=>$project)
                    @include('partials.user.profile.project')
                @endforeach
            @else
                <div class="my-xl">
                    <p>{{ (Auth::user()->id == $user->id ? 'You have' : $user->preferences['first_name'] . ' has') }} access to all projects</p>
                </div>
            @endif
          </div>
        </div>
        <div class="content-section content-section-js" id="forms">
          <div class="content-sections-scroll">
            @if (!$user->admin)
                <div class="my-xl">
                    <p>{{ (Auth::user()->id == $user->id ? 'You have' : $user->preferences['first_name'] . ' has') }} access to the following forms...</p>
                </div>
                @foreach ($forms as $index=>$form)
                    @include('partials.user.profile.form')
                @endforeach
            @else
                <div class="my-xl">
                    <p>{{ (Auth::user()->id == $user->id ? 'You have' : $user->preferences['first_name'] . ' has') }} access to all forms</p>
                </div>
            @endif
          </div>
        </div>
    </section>
@stop


@section('javascripts')
    @include('partials.user.javascripts')

    <script type="text/javascript">
        Kora.User.Profile();
    </script>
@stop
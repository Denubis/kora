<div class="form card {{ $index == 0 ? 'active' : '' }}" id="{{$form['fid']}}">
    <div class="header {{ $index == 0 ? 'active' : '' }}">
        <div class="left pl-m">
            <a class="title underline-middle-hover mr-xl" href="{{ action("FormController@show",["pid" => $form['pid'], 'fid' => $form['fid']]) }}">
                <span class="name">{{$form['name']}}</span>
            </a>
            <a class="group" href="{{ action("FormGroupController@index",["pid" => $form['pid'], 'fid' => $form['fid'], 'active' => $form['group']['id']]) }}">
                <span>{{$form['group']['name']}}</span>
            </a>
        </div>

        <div class="card-toggle-wrap">
            <a href="#" class="card-toggle card-toggle-js">
                <i class="icon icon-chevron {{ $index == 0 ? 'active' : '' }}"></i>
            </a>
        </div>
    </div>

    <div class="content content-js {{ $index == 0 ? 'active' : '' }}">
        <div class="pb-m">
            @if ($form['permissions'] == 'Admin')
                <p>{{ (Auth::user()->id == $user->id ? 'You are' : $user->first_name . ' is') }} an Admin for this form.</p>
            @else
                <p>{{ (Auth::user()->id == $user->id ? 'You can' : $user->first_name . ' can') }} {{$form['permissions']}} within this form</p>
            @endif
        </div>
    </div>
</div>
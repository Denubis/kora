@if ($admin)
    <hr/>

    <h4> Admin Panel</h4>

    <form action="{{ action('AdminController@users') }}" style="display: inline">
        <button type="submit" class="btn btn-default"> Manage Users </button>
    </form>

    <form action="#" style="display: inline">
        <button type="submit" class="btn btn-default"> Manage Tokens </button>
    </form>

@endif
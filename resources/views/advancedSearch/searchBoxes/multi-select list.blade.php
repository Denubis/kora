<div class="panel panel-default">
    <div class="panel-heading">
        <div class="checkbox">
            <label style="font-size:1.25em;"><input type="checkbox" name="{{$field->flid}}_dropdown"> {{$field->name}}</label>
        </div>
    </div>
    <div id="input_collapse_{{$field->flid}}" style="display: none;">
        <div class="panel-body">
            <label for="{{$field->flid}}_input">Select options:</label></br>
            {!! Form::select( $field->flid . "_input[]", \App\MultiSelectListField::getList($field, false), "", ["class" => "form-control", "Multiple", 'id' => $field->flid."_input", "style" => "width: 100%"]) !!}
        </div>
    </div>
</div>
<script>$("#{{$field->flid}}_input").select2();</script>
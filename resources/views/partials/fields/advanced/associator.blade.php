{!! Form::hidden('advanced',true) !!}
<div class="form-group mt-xxxl">
    {!! Form::label('','Association Search Configuration') !!}
</div>

@php $associations = \App\Http\Controllers\AssociationController::getAvailableAssociations($fid); @endphp
<div class="advanced-options-associator {{count($associations) == 0 ? 'search-config-empty-state' : ''}}">
    @foreach($associations as $a)
        @php
        $f = \App\Http\Controllers\FormController::getForm($a->data_form);
        $formFieldsData = $f->layout['fields'];
        $formFields = array();
        foreach($formFieldsData as $flid => $data) {
            $formFields[$flid] = $data['name'];
        }

        $selectArray = ['class' => 'single-select', "data-placeholder" => "Select field preview value", 'disabled'];
        @endphp
        <div class="form-group mb-m">
            <div class="check-box-half">
                <input type="checkbox" value="1" id="active" class="check-box-input association-check-js" name="checkbox_{{$f->id}}"/>
                <span class="check"></span>
                <span class="placeholder">Search through "{{$f->name}}"?</span>
            </div>
        </div>

        <div class="form-group mt-m mb-xl hidden">
            {!! Form::label('preview_'.$f->id.'[]', 'Preview Value') !!}
            {!! Form::select('preview_'.$f->id.'[]', $formFields, null, $selectArray) !!}
        </div>
    @endforeach
	@if(count($associations) == 0) No Forms Associated @endif
</div>

<div class="form-group mt-sm">
    <p class="sub-text">
        If no forms are available, have a Form Admin request permission to forms by using the Association Permissions page
    </p>
</div>

<script>
    Kora.Fields.Options('Associator');
</script>
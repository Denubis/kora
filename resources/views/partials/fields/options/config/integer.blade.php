@php
    if(isset($seq)) //Combo List
        $seq = '_' . $seq;
    else
        $seq = '';
@endphp
<div class="form-group mt-xl">
    <div class="number-input-container">
        {!! Form::label('min' . $seq,'Minimum Value') !!}
        <span class="error-message"></span>
        <input
            type="number"
            name="min{{$seq}}"
            class="text-input number-min-js"
            id="min"
            value="{{ $field['options']['Min'] }}"
            placeholder="Enter minimum value here"
            step="1"
        >
    </div>
</div>

<div class="form-group mt-xl">
    <div class="number-input-container">
        {!! Form::label('max'. $seq,'Max Value') !!}
        <span class="error-message"></span>
        <input
            type="number"
            name="max{{$seq}}"
            class="text-input number-max-js"
            id="max"
            value="{{ $field['options']['Max'] }}"
            placeholder="Enter max value here"
            step="1"
        >
    </div>
</div>

<div class="form-group mt-xl">
    {!! Form::label('unit'. $seq,'Unit of Measurement') !!}
    {!! Form::text('unit'. $seq, $field['options']['Unit'], ['class' => 'text-input', 'placeholder' => 'Enter unit of measurement here']) !!}
</div>

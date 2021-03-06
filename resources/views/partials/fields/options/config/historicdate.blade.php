@php
    if(isset($seq)) {
        $jseq = $seq . '-';
        $seq = '_' . $seq;
    } else
        $seq = $jseq = '';
@endphp
<div class="form-group mt-xl">
    {!! Form::label('format' . $seq,'Date Format') !!}
    {!! Form::select('format' . $seq, ['MMDDYYYY' => 'MM DD, YYYY','DDMMYYYY' => 'DD MM YYYY','YYYYMMDD' => 'YYYY MM DD'], $field['options']['Format'], ['class' => 'single-select']) !!}
</div>

<div class="form-group mt-xl half pr-m">
    <div class="number-input-container number-input-container-js">
        {!! Form::label('start' . $seq,'Start Year') !!}
        <span class="error-message"></span>
        @php
            $start = $field['options']['Start'];
            if ($start == 0)
                $start = date("Y");
        @endphp
        {!! Form::input('number', 'start' . $seq, $start, ['class' => 'text-input start-year-'.$jseq.'js', 'placeholder' => 'Enter start year here', 'data-current-year-id' => 'start']) !!}
        {!! Form::input('hidden', 'start' . $seq, 0, ['class' => 'hidden-current-year-'.$jseq.'js', 'disabled']) !!}
    </div>

    <div class="check-box-half mt-m">
        {!! Form::input('checkbox', 'start-current-year' . $seq, null, ['class' => 'check-box-input current-year-'.$jseq.'js', 'data-current-year-id' => 'start', ($field['options']['Start'] == 0 ? 'checked' : '')]) !!}
        <span class="check"></span>
        <span class="placeholder">Use Current Year as Start Year</span>
    </div>
</div>

<div class="form-group mt-xl half pl-m">
    <div class="number-input-container number-input-container-js">
        {!! Form::label('end' . $seq,'End Year') !!}
        <span class="error-message"></span>
        @php
            $end = $field['options']['End'];
            if ($end == 0)
                $end = date("Y");
        @endphp
        {!! Form::input('number', 'end' . $seq, $end, ['class' => 'text-input end-year-'.$jseq.'js', 'placeholder' => 'Enter end year here', 'data-current-year-id' => 'end', ]) !!}
        {!! Form::input('hidden', 'end' . $seq, 0, ['class' => 'hidden-current-year-'.$jseq.'js', 'disabled']) !!}
    </div>

    <div class="check-box-half mt-m">
        {!! Form::input('checkbox', 'end-current-year' . $seq, null, ['class' => 'check-box-input current-year-'.$jseq.'js', 'data-current-year-id' => 'end', ($field['options']['End'] == 0 ? 'checked' : '')]) !!}
        <span class="check"></span>
        <span class="placeholder">Use Current Year as End Year</span>
    </div>
</div>

@include("partials.fields.modals.changeDefaultYearModal")

<div class="form-group mt-xl">
    {!! Form::label('prefix' . $seq,'Show Prefixes?') !!}
    {!! Form::select('prefix' . $seq, [0 => 'No', 1 => 'Yes'], $field['options']['ShowPrefix'], ['class' => 'single-select']) !!}
</div>

<div class="form-group mt-xl">
    {!! Form::label('era' . $seq,'Show Calendar/Date Notation?') !!}
    {!! Form::select('era' . $seq, [0 => 'No', 1 => 'Yes'], $field['options']['ShowEra'], ['class' => 'single-select']) !!}
</div>

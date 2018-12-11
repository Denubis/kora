@extends('fields.show')

@section('fieldOptions')
    <?php
        $thumbSmCurr = explode('x',\App\Http\Controllers\FieldController::getFieldOption($field, "ThumbSmall"));
        $thumbLrgCurr = explode('x',\App\Http\Controllers\FieldController::getFieldOption($field, "ThumbLarge"));
    ?>

    <div class="form-group">
        {!! Form::label('filesize','Max File Size (kb)') !!}
        <div class="number-input-container number-input-container-js">
            <input type="number" name="filesize" class="text-input" step="1"
               value="{{ \App\Http\Controllers\FieldController::getFieldOption($field, "FieldSize") }}" min="0"
			   placeholder="Enter max file size (kb) here">
        </div>
    </div>

    <div class="form-group mt-xl half pr-m">
        {!! Form::label('small_x','Small Thumbnail (X)') !!}
        <div class="number-input-container number-input-container-js">
            <input type="number" name="small_x" class="text-input" step="any" value="{{$thumbSmCurr[0]}}" min="50" max="700"
		    placeholder="Enter small thumbnail (X) here">
        </div>
    </div>

    <div class="form-group mt-xl half pl-m">
        {!! Form::label('small_y','Small Thumbnail (Y)') !!}
        <div class="number-input-container number-input-container-js">
            <input type="number" name="small_y" class="text-input" step="any" value="{{$thumbSmCurr[1]}}" min="50" max="700"
		    placeholder="Enter small thumbnail (Y) here">
        </div>
    </div>

    <div class="form-group">
        {{--This is a fake spacer for handling multiple half inputs in a row--}}
    </div>

    <div class="form-group mt-xl half pr-m">
        {!! Form::label('large_x','Large Thumbnail (X)') !!}
        <div class="number-input-container number-input-container-js">
            <input type="number" name="large_x" class="text-input" step="1" value="{{$thumbLrgCurr[0]}}" min="50" max="700"
		    placeholder="Enter large thumbnail (X) here">
        </div>
    </div>

    <div class="form-group mt-xl half pl-m">
        {!! Form::label('large_y','Large Thumbnail (Y)') !!}
        <div class="number-input-container number-input-container-js">
            <input type="number" name="large_y" class="text-input" step="1" value="{{$thumbLrgCurr[1]}}" min="50" max="700"
		    placeholder="Enter large thumbnail (Y) here">
        </div>
    </div>

    <div class="form-group mt-xl">
        {!! Form::label('maxfiles','Max File Amount') !!}
        <div class="number-input-container number-input-container-js">
            <input type="number" name="maxfiles" class="text-input" step="1"
               value="{{ \App\Http\Controllers\FieldController::getFieldOption($field, "MaxFiles") }}" min="0"
			   placeholder="Enter max file amount here">
        </div>
    </div>

    <div class="form-group mt-xl">
        {!! Form::label('filetype','Allowed File Types') !!}
        {!! Form::select('filetype'.'[]',['image/jpeg' => 'Jpeg','image/gif' => 'Gif','image/png' => 'Png','image/bmp' => 'Bmp'],
            explode('[!]',\App\Http\Controllers\FieldController::getFieldOption($field, "FileTypes")),
            ['class' => 'multi-select', 'Multiple', 'data-placeholder' => 'Search and Select the file types allowed here']) !!}
    </div>
@stop

@section('fieldOptionsJS')
    Kora.Inputs.Number();
    Kora.Fields.Options('Gallery');
@stop
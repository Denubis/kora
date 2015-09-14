<?php
    $value = array();

    //clear the tmp files
    $folder = 'f'.$field->flid.'u'.\Auth::user()->id;
    $dirTmp = env('BASE_PATH').'storage/app/tmpFiles/'.$folder;
    if(file_exists($dirTmp)) {
        foreach (new \DirectoryIterator($dirTmp) as $file) {
            if ($file->isFile()) {
                unlink($dirTmp.'/'.$file->getFilename());
            }
        }
    }
    if(!is_null($documents)){
        //move things over from storage to tmp
        $dir = env('BASE_PATH').'storage/app/files/p'.$record->pid.'/f'.$record->fid.'/r'.$record->rid.'/fl'.$field->flid;
        if(file_exists($dir)) {
            foreach (new \DirectoryIterator($dir) as $file) {
                if ($file->isFile()) {
                    copy($dir.'/'.$file->getFilename(),$dirTmp.'/'.$file->getFilename());
                    array_push($value,$file->getFilename());
                }
            }
        }
    }
?>
<div class="form-group" id="fileDiv{{$field->flid}}">
    {!! Form::label($field->flid, $field->name.': ') !!}
    @if($field->required==1)
        <b style="color:red;font-size:20px">*</b>
    @endif
    <span class="btn btn-success fileinput-button">
        <span>Add files...</span>
        <input id="file{{$field->flid}}" type="file" name="file{{$field->flid}}[]"
               data-url="{{ env('BASE_URL') }}public/saveTmpFile/{{$field->flid}}" multiple>
        {!! Form::hidden($field->flid,'f'.$field->flid.'u'.\Auth::user()->id) !!}
    </span>
    <br/><br/>
    <div id="progress">
        <div class="bar{{$field->flid}} progress-bar" style="width: 0%; height:18px; background:green;"></div>
    </div>
    <br/>
    <div id="filenames{{$field->flid}}">
        @foreach($value as $file)
            <div>
                {{$file}}
                <button class="btn btn-danger delete" type="button" data-type="DELETE"
                        data-url="{{env('BASE_URL')}}public/deleteTmpFile/{{$folder}}/{{urlencode($file)}}">
                    <i class="glyphicon glyphicon-trash"></i>
                    DELETE
                </button>
            </div>
        @endforeach
    </div>
</div>

<script>
    $('#file{{$field->flid}}').fileupload({
        dataType: 'json',
        singleFileUploads: false,
        done: function (e, data) {
            $.each(data.result['file{{$field->flid}}'], function (index, file) {
                var del = '<div>'+file.name+' ';
                del += '<button class="btn btn-danger delete" type="button" data-type="'+file.deleteType+'" data-url="'+file.deleteUrl+'" >';
                del += '<i class="glyphicon glyphicon-trash" /> DELETE</button>';
                del += '</div>';

                $('#filenames{{$field->flid}}').append(del);
            });
        },
        progressall: function (e, data) {
            var progress = parseInt(data.loaded / data.total * 100, 10);
            $('#progress .bar{{$field->flid}}').css(
                    'width',
                    progress + '%'
            );
        }
    });
    $('#filenames{{$field->flid}}').on('click','.delete',function(){
        var div = $(this).parent();
        $.ajax({
            url: 'http://'+$(this).attr('data-url'),
            type: 'DELETE',
            dataType: 'json',
            data: {
                "_token": '{{csrf_token()}}'
            },
            success: function (data) {
                div.remove();
            }
        });
    });

    function numFiles(flid){
        var cnt = 0;
        $("#filenames"+flid).find(".button").each(function () {
            cnt++;
        });

        return cnt;
    }
</script>
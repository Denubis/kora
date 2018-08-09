var Kora = Kora || {};
Kora.Records = Kora.Records || {};

Kora.Records.Validate = function() {

    function initializeRecordValidation() {
        $('.record-validate-js').click(function(e) {
            var $this = $(this);

            e.preventDefault();

            values = {};
            //We need to make sure all CKEDITOR data is actually in the form to validate it
            for(var instanceName in CKEDITOR.instances){
                CKEDITOR.instances[instanceName].updateElement();
            }
            $.each($('.record-form').serializeArray(), function(i, field) {
                if(field.name in values)
                    if(Array.isArray(values[field.name]))
                        values[field.name].push(field.value);
                    else
                        values[field.name] = [values[field.name], field.value];
                else
                    values[field.name] = field.value;
            });
            values['_method'] = 'POST';

            // $.ajax({
                // url: validationUrl,
                // method: 'POST',
                // data: values,
                // success: function(err) {
                    // $('.error-message').text('');
                    // $('.text-input, .text-area, .cke, .chosen-container').removeClass('error');

                    // if(err.errors.length==0) {
                        // $('.record-form').submit();
                    // } else {
                        // $.each(err.errors, function(fieldName, error) {
                            // var $field = $('#'+fieldName);
                            // $field.addClass('error');
                            // $field.siblings('.error-message').text(error);
                        // });
                    // }
                // }
            // });

            $.ajax({
              url: validationUrl,
              method: 'POST',
              data: values,
              success: function (data) {
                $('.record-form').submit();
              },
              error: function (err) {
                console.log(err);
              }
            });
        });
    }

    initializeRecordValidation();
}
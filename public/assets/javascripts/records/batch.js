var Kora = Kora || {};
Kora.Records = Kora.Records || {};

Kora.Records.Batch = function() {

    $('.single-select').chosen({
        allow_single_deselect: true,
        width: '100%',
    });

    $('.multi-select').chosen({
        width: '100%',
    });

    function initializeSelectAddition() {
        $('.chosen-search-input').on('keyup', function(e) {
            var container = $(this).parents('.chosen-container').first();

            if (e.which === 13 && (container.find('li.no-results').length > 0 || container.find('li.active-result').length == 0)) {
                var option = $("<option>").val(this.value.trim()).text(this.value.trim());

                var select = container.siblings('.modify-select').first();

                select.append(option);
                select.find(option).prop('selected', true);
                select.trigger("chosen:updated");
            }
        });
    }

    function initializeSelectBatchField() {
        $('.field-to-batch-js').on('change', function(e) { // instead of 'on change' it should be 'on change if field is populated'
            var flid = $(this).val(); // otherwise in the case of loading the page with this already populated, deselecting the current option activates the button

            //MAKE BUTTON WORK
            if (flid != '')
                $('.batch-submit-js, .batch-selected-submit-js').removeClass('disabled');
            else
                $('.batch-submit-js, .batch-selected-submit-js').addClass('disabled');

            $('.batch-field-section-js').each(function() {
               var divID = $(this).attr('id');

                if(divID=='batch_'+flid)
                    $(this).removeClass('hidden');
                else
                    $(this).addClass('hidden');
            });
        });
    }

    function initializeSpecialInputs() {
        $('.ckeditor-js').each(function() {
            textid = $(this).attr('id');

            CKEDITOR.replace(textid);
        });

        jQuery('.event-start-time-js').datetimepicker({
            format:'m/d/Y g:i A', inline:true, lang:'en', step: 15,
            minDate:'1900/01/31',
            maxDate:'2020/12/31'
        });

        jQuery('.event-end-time-js').datetimepicker({
            format:'m/d/Y g:i A', inline:true, lang:'en', step: 15,
            minDate:'1900/01/31',
            maxDate:'2020/12/31'
        });
    }

    function intializeAssociatorOptions() {
        $('.assoc-search-records-js').on('keypress', function(e) {
            var keyCode = e.keyCode || e.which;
            if(keyCode === 13) {
                e.preventDefault();

                var keyword = $(this).val();
                var assocSearchURI = $(this).attr('search-url');
                var resultsBox = $(this).parent().next().children('.assoc-select-records-js').first();
                //Clear old values
                resultsBox.html('');
                resultsBox.trigger("chosen:updated");

                $.ajax({
                    url: assocSearchURI,
                    type: 'POST',
                    data: {
                        "_token": csrfToken,
                        "keyword": keyword
                    },
                    success: function (result) {
                        for(var kid in result) {
                            var preview = result[kid];
                            var opt = "<option value='"+kid+"'>"+kid+": "+preview+"</option>";

                            resultsBox.append(opt);
                            resultsBox.trigger("chosen:updated");
                        }

                        resultInput = resultsBox.next().find('.chosen-search-input').first();
                        resultInput.val('');
                        resultInput.click();
                    }
                });
            }
        });

        $('.assoc-select-records-js').change(function() {
            defaultBox = $(this).parent().next().children('.assoc-default-records-js');

            $(this).children('option').each(function() {
                if($(this).is(':selected')) {
                    option = $("<option/>", { value: $(this).attr("value"), text: $(this).text() });

                    defaultBox.append(option);
                    defaultBox.find(option).prop('selected', true);
                    defaultBox.trigger("chosen:updated");

                    $(this).prop("selected", false);
                }
            });

            $(this).trigger("chosen:updated");
        });
    }

    function initializeComboListOptions(){
        var flid, type1, type2, $comboValueDiv, $modal;

        $('.combo-list-display').on('click', '.delete-combo-value-js', function() {
            parentDiv = $(this).parent();
            parentDiv.remove();
        });

        $('.open-combo-value-modal-js').click(function(e) {
            flid = $(this).attr('flid');
            type1 = $(this).attr('typeOne');
            type2 = $(this).attr('typeTwo');

            $comboValueDiv = $('.combo-value-div-js-'+flid);
            var $modal = $comboValueDiv.find('.combo-list-modal-js');

            Kora.Modal.close();
            Kora.Modal.open($modal);
        });

        $('.add-combo-value-js').click(function() {
            if(type1=='Date') {
                monthOne = $('#month_one_'+flid);
                dayOne = $('#day_one_'+flid);
                yearOne = $('#year_one_'+flid);
                val1 = monthOne.val()+'/'+dayOne.val()+'/'+yearOne.val();
            } else {
                inputOne = $('#default_one_'+flid);
                val1 = inputOne.val();
            }

            if(type2=='Date') {
                monthTwo = $('#month_two_'+flid);
                dayTwo = $('#day_two_'+flid);
                yearTwo = $('#year_two_'+flid);
                val2 = monthTwo.val()+'/'+dayTwo.val()+'/'+yearTwo.val();
            } else {
                inputTwo = $('#default_two_'+flid);
                val2 = inputTwo.val();
            }

            if(val1=='' | val2=='' | val1==null | val2==null | val1=='//'| val2=='//') {
                $('.combo-error-'+flid+'-js').text('Both fields must be filled out');
            } else {
                $('.combo-error-'+flid+'-js').text('');

                if($comboValueDiv.find('.combo-list-empty').length) {
                    $comboValueDiv.find('.combo-list-empty').first().remove();
                }

                div = '<div class="combo-value-item combo-value-item-js">';

                if(type1=='Text' | type1=='List' | type1=='Number' | type1=='Date') {
                    div += '<input type="hidden" name="'+flid+'_combo_one[]" value="'+val1+'">';
                    div += '<span class="combo-column">'+val1+'</span>';
                } else if(type1=='Multi-Select List' | type1=='Generated List' | type1=='Associator') {
                    div += '<input type="hidden" name="'+flid+'_combo_one[]" value="'+val1.join('[!]')+'">';
                    div += '<span class="combo-column">'+val1.join(' | ')+'</span>';
                }

                if(type2=='Text' | type2=='List' | type2=='Number' | type2=='Date') {
                    div += '<input type="hidden" name="'+flid+'_combo_two[]" value="'+val2+'">';
                    div += '<span class="combo-column">'+val2+'</span>';
                } else if(type2=='Multi-Select List' | type2=='Generated List' | type2=='Associator') {
                    div += '<input type="hidden" name="'+flid+'_combo_two[]" value="'+val2.join('[!]')+'">';
                    div += '<span class="combo-column">'+val2.join(' | ')+'</span>';
                }

                div += '<span class="combo-delete delete-combo-value-js tooltip" tooltip="Delete Combo Value"><i class="icon icon-trash"></i></span>';

                div += '</div>';

                $comboValueDiv.find('.combo-value-item-container-js').append(div);

                if(type1=='Multi-Select List' | type1=='Generated List' | type1=='List' | type1=='Associator') {
                    inputOne.val('');
                    inputOne.trigger("chosen:updated");
                } else if(type1=='Date') {
                    monthOne.val(''); dayOne.val(''); yearOne.val('');
                    monthOne.trigger("chosen:updated"); dayOne.trigger("chosen:updated"); yearOne.trigger("chosen:updated");
                } else {
                    inputOne.val('');
                }

                if(type2=='Multi-Select List' | type2=='Generated List' | type2=='List' | type2=='Associator') {
                    inputTwo.val('');
                    inputTwo.trigger("chosen:updated");
                } else if(type2=='Date') {
                    monthTwo.val(''); dayTwo.val(''); yearTwo.val('');
                    monthTwo.trigger("chosen:updated"); dayTwo.trigger("chosen:updated"); yearTwo.trigger("chosen:updated");
                } else {
                    inputTwo.val('');
                }

                Kora.Modal.close();
            }
        });
    }

    function initializeDateOptions() {
        var $dateFormGroups = $('.date-input-form-group-js');
        var $dateListInputs = $dateFormGroups.find('.chosen-container');
        var scrollBarWidth = 17;

        $eraCheckboxes = $('.era-check-js');

        $eraCheckboxes.click(function() {
            var $selected = $(this);

            $eraCheckboxes.prop('checked', false);
            $selected.prop('checked', true);

            currEra = $selected.val();
            flid = $selected.attr('flid');
            $month = $('#month_'+flid);
            $day = $('#day_'+flid);

            if(currEra=='BP' | currEra=='KYA BP') {
                $month.attr('disabled','disabled');
                $day.attr('disabled','disabled');
                $month.trigger("chosen:updated");
                $day.trigger("chosen:updated");
            } else {
                $month.removeAttr('disabled');
                $day.removeAttr('disabled');
                $month.trigger("chosen:updated");
                $day.trigger("chosen:updated");
            }
        });

        setTextInputWidth();

        $(window).resize(setTextInputWidth);

        function setTextInputWidth() {
            if ($(window).outerWidth() < 1175 - scrollBarWidth) {
                // Window is small, full width Inputs
                $dateListInputs.css('width', '100%');
                $dateListInputs.css('margin-bottom', '10px');
            } else {
                // Window is large, 1/3 width Inputs
                $dateListInputs.css('width', '33%');
                $dateListInputs.css('margin-bottom', '');
            }
        }
    }

    function initializeScheduleOptions() {
        Kora.Modal.initialize();

        var flid = '';
        var start_year = 1900;
        var end_year = 2020;

        var $scheduleCardContainers = $('.schedule-card-container-js');
        var $scheduleCards = $scheduleCardContainers.find('.schedule-card-js');
        var $newEventButtons = $('.add-new-default-event-js');

        // Action arrows on the cards
        initializeMoveAction($scheduleCards);

        // Drag cards to sort
        $scheduleCardContainers.sortable();

        // Delete card
        initializeDelete();

        $newEventButtons.click(function(e) {
            e.preventDefault();

            flid = $(this).attr('flid');
            start_year = $(this).attr('start');
            end_year = $(this).attr('end');

            Kora.Modal.open($('.schedule-add-event-modal-js'));
        });

        $('.add-new-event-js').on('click', function(e) {
            e.preventDefault();

            $('.error-message').text('');
            $('.text-input, .text-area, .cke, .chosen-container').removeClass('error');

            var nameInput = $('.event-name-js');
            var sTimeInput = $('.event-start-time-js');
            var eTimeInput = $('.event-end-time-js');

            var name = nameInput.val().trim();
            var sTime = sTimeInput.val().trim();
            var eTime = eTimeInput.val().trim();

            if(name==''|sTime==''|eTime=='') {
                if(name=='') {
                    schError = $('.event-name-js');
                    schError.addClass('error');
                    schError.siblings('.error-message').text('Event name is required');
                }

                if(sTime=='') {
                    schError = $('.event-start-time-js');
                    schError.addClass('error');
                    schError.siblings('.error-message').text('Start time is required');
                }

                if(eTime=='') {
                    schError = $('.event-end-time-js');
                    schError.addClass('error');
                    schError.siblings('.error-message').text('End time is required');
                }
            } else {
                if($('.event-allday-js').is(":checked")) {
                    sTime = sTime.split(" ")[0];
                    eTime = eTime.split(" ")[0];
                }

                if(sTime>eTime) {
                    schError = $('.event-start-time-js');
                    schError.addClass('error');
                    schError.siblings('.error-message').text('Start time can not occur before the end time');
                } else {
                    val = name + ': ' + sTime + ' - ' + eTime;

                    if(val != '') {
                        // Value is valid
                        // Create and display new schedule card
                        var newCardHtml = '<div class="card schedule-card schedule-card-js">' +
                            '<input type="hidden" class="list-option-js" name="'+flid+'[]" value="' + val + '">' +
                            '<div class="header">' +
                            '<div class="left">' +
                            '<div class="move-actions">' +
                            '<a class="action move-action-js up-js" href="">' +
                            '<i class="icon icon-arrow-up"></i>' +
                            '</a>' +
                            '<a class="action move-action-js down-js" href="">' +
                            '<i class="icon icon-arrow-down"></i>' +
                            '</a>' +
                            '</div>' +
                            '<span class="title">' + name + '</span>' +
                            '</div>' +
                            '<div class="card-toggle-wrap">' +
                            '<a class="schedule-delete schedule-delete-js tooltip" tooltip="Delete Event" href=""><i class="icon icon-trash"></i></a>' +
                            '</div>' +
                            '</div>' +
                            '<div class="content"><p class="event-time">'+ sTime + ' - ' + eTime + '</p></div>' +
                            '</div>';

                        var $scheduleCardContainer = $('.schedule-'+flid+'-js').find('.schedule-card-container-js');
                        $scheduleCardContainer.append(newCardHtml);

                        initializeMoveAction($scheduleCardContainer.find('.schedule-card-js'));
                        initializeDelete();
                        Kora.Fields.TypedFieldInputs.Initialize();

                        nameInput.val('');
                        Kora.Modal.close($('.schedule-add-event-modal-js'));
                    }
                }
            }
        });

        function initializeDelete() {
            $scheduleCardContainers.find('.schedule-card-js').each(function() {
                var $card = $(this);
                var $deleteButton = $card.find('.schedule-delete-js');

                $deleteButton.unbind();
                $deleteButton.click(function(e) {
                    e.preventDefault();

                    $card.remove();
                })
            });
        }
    }

    function intializeGeolocatorOptions() {
        Kora.Modal.initialize();
        var flid = '';

        var $geoCardContainers = $('.geolocator-card-container-js');
        var $geoCards = $geoCardContainers.find('.geolocator-card-js');
        var $newLocationButtons = $('.add-new-default-location-js');

        // Action arrows on the cards
        initializeMoveAction($geoCards);

        // Drag cards to sort
        $geoCardContainers.sortable();

        // Delete card
        initializeDelete();

        $newLocationButtons.click(function(e) {
            e.preventDefault();

            flid = $(this).attr('flid');

            Kora.Modal.open($('.geolocator-add-location-modal-js'));
        });

        $('.location-type-js').on('change', function(e) {
            newType = $(this).val();
            if(newType=='LatLon') {
                $('.lat-lon-switch-js').removeClass('hidden');
                $('.utm-switch-js').addClass('hidden');
                $('.address-switch-js').addClass('hidden');
            } else if(newType=='UTM') {
                $('.lat-lon-switch-js').addClass('hidden');
                $('.utm-switch-js').removeClass('hidden');
                $('.address-switch-js').addClass('hidden');
            } else if(newType=='Address') {
                $('.lat-lon-switch-js').addClass('hidden');
                $('.utm-switch-js').addClass('hidden');
                $('.address-switch-js').removeClass('hidden');
            }
        });

        $('.add-new-location-js').click(function(e) {
            e.preventDefault();

            $('.error-message').text('');
            $('.text-input, .text-area, .cke, .chosen-container').removeClass('error');

            //check to see if description provided
            var desc = $('.location-desc-js').val();
            if(desc=='') {
                $geoError = $('.location-desc-js');
                $geoError.addClass('error');
                $geoError.siblings('.error-message').text('Location description required');
            } else {
                var type = $('.location-type-js').val();

                //determine if info is good for that type
                var valid = true;
                if(type == 'LatLon') {
                    var lat = $('.location-lat-js').val();
                    var lon = $('.location-lon-js').val();

                    if(lat == '') {
                        $geoError = $('.location-lat-js');
                        $geoError.addClass('error');
                        $geoError.siblings('.error-message').text('Latitude value required');
                        valid = false;
                    }

                    if(lon == '') {
                        $geoError = $('.location-lon-js');
                        $geoError.addClass('error');
                        $geoError.siblings('.error-message').text('Longitude value required');
                        valid = false;
                    }
                } else if(type == 'UTM') {
                    var zone = $('.location-zone-js').val();
                    var east = $('.location-east-js').val();
                    var north = $('.location-north-js').val();

                    if(zone == '') {
                        $geoError = $('.location-zone-js');
                        $geoError.addClass('error');
                        $geoError.siblings('.error-message').text('UTM Zone is required');
                        valid = false;
                    }

                    if(east == '') {
                        $geoError = $('.location-east-js');
                        $geoError.addClass('error');
                        $geoError.siblings('.error-message').text('UTM Easting required');
                        valid = false;
                    }

                    if(north == '') {
                        $geoError = $('.location-north-js');
                        $geoError.addClass('error');
                        $geoError.siblings('.error-message').text('UTM Northing required');
                        valid = false;
                    }
                } else if(type == 'Address') {
                    var addr = $('.location-addr-js').val();

                    if(addr == '') {
                        $geoError = $('.location-addr-js');
                        $geoError.addClass('error');
                        $geoError.siblings('.error-message').text('Location address required');
                        valid = false;
                    }
                }

                //if still valid
                if(valid) {
                    //find info for other loc types
                    if(type == 'LatLon')
                        coordinateConvert({"_token": csrfToken,type:'latlon',lat:lat,lon:lon});
                    else if(type == 'UTM')
                        coordinateConvert({"_token": csrfToken,type:'utm',zone:zone,east:east,north:north});
                    else if(type == 'Address')
                        coordinateConvert({"_token": csrfToken,type:'geo',addr:addr});

                    $('.location-lat-js').val(''); $('.location-lon-js').val('');
                    $('.location-zone-js').val(''); $('.location-east-js').val(''); $('.location-north-js').val('');
                    $('.location-addr-js').val('');
                }
            }
        });

        function coordinateConvert(data) {
            $.ajax({
                url: geoConvertUrl,
                type: 'POST',
                data: data,
                success:function(result) {
                    // Get Values
                    var desc = $('.location-desc-js').val();
                    var fullresult = '[Desc]'+desc+'[Desc]'+result;
                    var latlon = result.split('[LatLon]')[1].split(',').join(', ');
                    var utm = result.split('[UTM]')[1];
                    var addr = result.split('[Address]')[1];

                    // Create and display new geolocation card
                    var newCardHtml = '<div class="card geolocator-card geolocator-card-js">' +
                        '<input type="hidden" class="list-option-js" name="'+flid+'[]" value="' + fullresult + '">' +
                        '<div class="header">' +
                        '<div class="left">' +
                        '<div class="move-actions">' +
                        '<a class="action move-action-js up-js" href="">' +
                        '<i class="icon icon-arrow-up"></i>' +
                        '</a>' +
                        '<a class="action move-action-js down-js" href="">' +
                        '<i class="icon icon-arrow-down"></i>' +
                        '</a>' +
                        '</div>' +
                        '<span class="title">' + desc + '</span>' +
                        '</div>' +
                        '<div class="card-toggle-wrap">' +
                        '<a class="geolocator-delete geolocator-delete-js tooltip" tooltip="Delete Location" href=""><i class="icon icon-trash"></i></a>' +
                        '</div>' +
                        '</div>' +
                        '<div class="content"><p class="location"><span class="bold">LatLon:</span> '+ latlon +'</p></div>' +
                        '</div>';

                    var $geoCardContainer = $('.geolocator-'+flid+'-js').find('.geolocator-card-container-js');
                    $geoCardContainer.append(newCardHtml);

                    initializeMoveAction($geoCardContainer.find('.geolocator-card-js'));
                    initializeDelete();
                    Kora.Fields.TypedFieldInputs.Initialize();

                    $('.location-desc-js').val('');
                    Kora.Modal.close($('.geolocator-add-location-modal-js'));
                }
            });
        }

        function initializeDelete() {
            $geoCardContainers.find('.geolocator-card-js').each(function() {
                var $card = $(this);
                var $deleteButton = $card.find('.geolocator-delete-js');

                $deleteButton.unbind();
                $deleteButton.click(function(e) {
                    e.preventDefault();

                    $card.remove();
                })
            });
        }
    }

    function intializeFileUploaderOptions() {
        var $fileUploads = $('.kora-file-upload-js');
        var $fileCardsContainer = $fileUploads.parent().find('.file-cards-container-js');
        //We will capture the current field when we start to upload. That way when we do upload, it's guarenteed to be that Field ID
        var lastClickedFlid = 0;

        // Prevents upload to whole web page
        $(document).bind('drop dragover', function (e) {
            e.preventDefault();
        });

        $fileUploads.each(function() {
            var $fileUpload = $(this);

            $('#'+$fileUpload.attr('id')).fileupload({
                dataType: 'json',
                dropZone: $('#'+$fileUpload.attr('id')).parent(),
                singleFileUploads: false,
                done: function (e, data) {
                    var $uploadInput = $(this);
                    lastClickedFlid = $uploadInput.attr('flid');
                    console.log(lastClickedFlid);
                    inputName = 'file'+lastClickedFlid;
                    capName = 'file_captions'+lastClickedFlid;
                    fileDiv = ".filenames-"+lastClickedFlid+"-js";

                    var $field = $uploadInput.siblings('#'+lastClickedFlid);
                    var $formGroup = $field.parent('.form-group');

                    // Tooltip text
                    var tooltip = "Remove Document";
                    if ($formGroup.hasClass('gallery-input-form-group')) {
                        tooltip = "Remove Image";
                    } else if ($formGroup.hasClass('video-input-form-group')) {
                        tooltip = "Remove Video";
                    } else if ($formGroup.hasClass('audio-input-form-group')) {
                        tooltip = "Remove Audio";
                    } else if ($formGroup.hasClass('3d-model-input-form-group')) {
                        tooltip = "Remove 3D Model";
                    }

                    $field.removeClass('error');
                    $field.siblings('.error-message').text('');
                    $.each(data.result[inputName], function (index, file) {
                        if(file.error == "" || !file.hasOwnProperty('error')) {
                            // Add caption only if input is a gallery
                            var captionHtml = '';
                            if ($formGroup.hasClass('gallery-input-form-group')) {
                                captionHtml = '<textarea type="text" name="' + capName + '[]" class="caption autosize-js" placeholder="Enter caption here"></textarea>';
                            }
                            // File card html
                            var fileCardHtml = '<div class="card file-card file-card-js">' +
                                '<input type="hidden" name="' + inputName + '[]" value ="' + file.name + '">' +
                                '<div class="header">' +
                                '<div class="left">' +
                                '<div class="move-actions">' +
                                '<a class="action move-action-js up-js" href="">' +
                                '<i class="icon icon-arrow-up"></i>' +
                                '</a>' +
                                '<a class="action move-action-js down-js" href="">' +
                                '<i class="icon icon-arrow-down"></i>' +
                                '</a>' +
                                '</div>' +
                                '<span class="title">' + file.name + '</span>' +
                                '</div>' +
                                '<div class="card-toggle-wrap">' +
                                '<a href="#" class="file-delete upload-filedelete-js ml-sm tooltip" tooltip="'+tooltip+'" data-url="' + file.deleteUrl + '">' +
                                '<i class="icon icon-trash danger"></i>' +
                                '</a>' +
                                '</div>' +
                                captionHtml +
                                '</div>' +
                                '</div>';

                            // Add file card to list of cards
                            $formGroup.find(fileDiv).append(fileCardHtml);

                            // Change directions text
                            $formGroup.find('.directions-empty-js').removeClass('active');
                            $formGroup.find('.directions-not-empty-js').addClass('active');

                            // Reinitialize inputs
                            Kora.Fields.TypedFieldInputs.Initialize();
                            Kora.Inputs.Textarea();
                        } else {
                            $field.addClass('error');
                            $field.siblings('.error-message').text(file.error);
                            return false;
                        }
                    });

                    //Reset progress bar
                    var progressBar = '.progress-bar-'+lastClickedFlid+'-js';
                    $formGroup.find(progressBar).css(
                        {"width": 0, "height": 0, "margin-top": 0}
                    );
                },
                fail: function (e,data){
                    var $uploadInput = $(this);
                    var $errorMessage = $uploadInput.siblings('.error-message');

                    var error = data.jqXHR['responseText'];
                    lastClickedFlid = $uploadInput.attr('flid');

                    var $field = $uploadInput.siblings('#'+lastClickedFlid);

                    $field.removeClass('error');
                    $field.siblings('.error-message').text('');
                    if(error=='InvalidType'){
                        $field.addClass('error');
                        $errorMessage.text('Invalid file type provided');
                    } else if(error=='TooManyFiles'){
                        $field.addClass('error');
                        $errorMessage.text('Max file limit was reached');
                    } else if(error=='MaxSizeReached'){
                        $field.addClass('error');
                        $errorMessage.text('One or more uploaded files is bigger than limit');
                    } else {
                        $field.addClass('error');
                        $errorMessage.text('Error uploading file');
                    }
                },
                progressall: function (e, data) {
                    var $uploadInput = $(this);
                    var $formGroup = $uploadInput.parent();
                    var progressBar = '.progress-bar-'+lastClickedFlid+'-js';
                    var progress = parseInt(data.loaded / data.total * 100, 10);

                    $formGroup.find(progressBar).css(
                        {"width": progress + '%', "height": '18px', "margin-top": '10px'}
                    );
                }
            });
        });

        $fileCardsContainer.on('click', '.upload-filedelete-js', function(e) {
            e.preventDefault();

            var $fileCard = $(this).parent().parent().parent('.file-card-js');
            var $container = $fileCard.parent();

            $.ajax({
                url: $(this).attr('data-url'),
                type: 'POST',
                dataType: 'json',
                data: {
                    "_token": csrfToken,
                    "_method": 'delete'
                },
                success: function (data) {
                    $fileCard.remove();

                    // Change directions text
                    if ($fileCardsContainer.children().length > 0) {
                        $container.siblings('.directions-empty-js').removeClass('active');
                        $container.siblings('.directions-not-empty-js').addClass('active');
                    } else {
                        $container.siblings('.directions-empty-js').addClass('active');
                        $container.siblings('.directions-not-empty-js').removeClass('active');
                    }
                }
            });
        });

        // Move file card up
        $fileCardsContainer.on('click', '.up-js', function(e) {
            e.preventDefault();

            var $fileCard = $(this).parent().parent().parent().parent('.file-card-js');

            if ($fileCard.prev('.file-card-js').length == 1) {
                var $prevCard = $fileCard.prev('.file-card-js');

                $fileCard.insertBefore($prevCard);
            }
        });

        // Move file card down
        $fileCardsContainer.on('click', '.down-js', function(e) {
            e.preventDefault();

            var $fileCard = $(this).parent().parent().parent().parent('.file-card-js');

            if ($fileCard.next('.file-card-js').length == 1) {
                var $nextCard = $fileCard.next('.file-card-js');

                $fileCard.insertAfter($nextCard);
            }
        });

        // Drag file cards to reorder
        $fileCardsContainer.sortable();

        Kora.Fields.TypedFieldInputs.Initialize();
        Kora.Inputs.Textarea();
    }

    function initializeRecordValidation() {
        $('.batch-submit-js').click(function(e) {
            var $this = $(this);

            e.preventDefault();

            values = {};
            $.each($('.batch-form').serializeArray(), function(i, field) {
                if(field.name in values)
                    if(Array.isArray(values[field.name]))
                        values[field.name].push(field.value);
                    else
                        values[field.name] = [values[field.name], field.value];
                else
                    values[field.name] = field.value;
            });
            values['_method'] = 'POST';

            $.ajax({
                url: validationUrl,
                method: 'POST',
                data: values,
                success: function(err) {
                    $('.error-message').text('');
                    $('.text-input, .text-area, .cke, .chosen-container').removeClass('error');

                    if(err.errors.length==0) {
                        $('.batch-form').submit();
                    } else {
                        $.each(err.errors, function(fieldName, error) {
                            var $field = $('#'+fieldName);
                            $field.addClass('error');
                            $field.siblings('.error-message').text(error);
                        });
                    }
                }
            });
        });
    }

    function multiSelectPlaceholders () {
        var inputDef = $('.chosen-container-multi').children('.chosen-choices');

        inputDef.on('click', function() {
            var childCheck = $(this).siblings('.chosen-drop').children('.chosen-results');
            if (childCheck.children().length === 0) {
                childCheck.append('<li class="no-results">No options to select!</li>');
            } else if (childCheck.children('.active-result').length === 0 && childCheck.children('.no-results').length === 0) {
                childCheck.append('<li class="no-results">No more options to select!</li>');
            }
        });

        inputDef.children('.search-field').children('input').blur(function() {
            var childCheck = inputDef.siblings('.chosen-drop').children('.chosen-results');
            if (childCheck.children('.no-results').length > 0) {
                childCheck.children('.no-results').remove();
            }
        });
    }

    initializeSelectAddition();
    initializeSelectBatchField();
    initializeSpecialInputs();
    intializeAssociatorOptions();
    initializeComboListOptions();
    initializeDateOptions();
    initializeScheduleOptions();
    intializeGeolocatorOptions();
    intializeFileUploaderOptions();
    Kora.Records.Modal();
    initializeRecordValidation();
    multiSelectPlaceholders();
    Kora.Inputs.Number();
}
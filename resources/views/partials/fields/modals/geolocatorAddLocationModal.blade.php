<div class="modal modal-js modal-mask geolocator-add-location-modal-js">
    <div class="content">
        <div class="header">
            <span class="title title-js">Create Default Location</span>
            <a href="#" class="modal-toggle modal-toggle-js">
                <i class="icon icon-cancel"></i>
            </a>
        </div>
        <div class="body">
            <div class="form-group">
                {!! Form::label('locDesc', 'Location Name') !!}
                <span class="error-message"></span>
                <input type="text" class="text-input location-desc-js" placeholder="Enter the Location name here (optional)">
            </div>
            <div class="form-group mt-xl">
                {!! Form::label('locType', 'Location Type') !!}
                {!! Form::select('loc_type', ['LatLon' => 'Lat Long', 'Address' => 'Address'], 'LatLon',
                    ['class' => 'single-select location-type-js']) !!}
            </div>

            <section class="lat-lon-switch-js">
                <div class="form-group mt-xl half pr-m">
                    <div class="number-input-container number-input-container-js">
                        {!! Form::label('latVal', 'Latitude') !!}
                        <span class="error-message"></span>
                        <input type="number" class="text-input location-lat-js" value="0" min=-90 max=90 step=".000001">
                    </div>
                </div>
                <div class="form-group mt-xl half pr-l">
                    <div class="number-input-container number-input-container-js">
                        {!! Form::label('lonVal', 'Longitude') !!}
                        <span class="error-message"></span>
                        <input type="number" class="text-input location-lon-js" value="0" min=-180 max=180 step=".000001">
                    </div>
                </div>
            </section>

            <section class="address-switch-js hidden">
                <div class="form-group mt-xl">
                    <span class="error-message"></span>
                    {!! Form::label('addrVal', 'Address') !!}
                    <input type="text" class="text-input location-addr-js">
                </div>
            </section>

            <div class="form-group mt-xxxl">
                <a href="#" class="btn add-new-location-js">Create Location</a>
            </div>
        </div>
    </div>
</div>

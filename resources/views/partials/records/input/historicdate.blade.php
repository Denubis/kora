@php
    if($editRecord) {
        $histDate = $record->{$flid};
    } else {
        $histDate = $field['default'];
    }

    if(is_null($histDate)) {
        $histDate = [
            'month' => '',
            'day' => '',
            'year' => '',
            'circa' => 0,
            'era' => 'CE'
        ];
    }
@endphp
<div class="form-group date-input-form-group date-input-form-group-js mt-xxxl">
    <label>@if($field['required']==1)<span class="oval-icon"></span> @endif{{$field['name']}}</label>
    <input type="hidden" name={{$flid}} value="{{$flid}}">

    <div class="form-input-container">
        <div class="form-group">
            <label>Select Date</label>
            @php
                $preDisabled = ($histDate['era'] == 'BP' | $histDate['era'] == 'KYA BP');
                if($preDisabled)
                    $monthClasses = ['class' => 'single-select preset-clear-chosen-js', 'data-placeholder'=>"Select a Month", 'id' => 'month_'.$flid, 'disabled' => $preDisabled];
                else
                    $monthClasses = ['class' => 'single-select preset-clear-chosen-js', 'data-placeholder'=>"Select a Month", 'id' => 'month_'.$flid];
            @endphp

            <div class="date-inputs-container">
                {!! Form::select('month_'.$flid,['' => '',
                    '1' => '01 - '.date("F", mktime(0, 0, 0, 1, 10)), '2' => '02 - '.date("F", mktime(0, 0, 0, 2, 10)),
                    '3' => '03 - '.date("F", mktime(0, 0, 0, 3, 10)), '4' => '04 - '.date("F", mktime(0, 0, 0, 4, 10)),
                    '5' => '05 - '.date("F", mktime(0, 0, 0, 5, 10)), '6' => '06 - '.date("F", mktime(0, 0, 0, 6, 10)),
                    '7' => '07 - '.date("F", mktime(0, 0, 0, 7, 10)), '8' => '08 - '.date("F", mktime(0, 0, 0, 8, 10)),
                    '9' => '09 - '.date("F", mktime(0, 0, 0, 9, 10)), '10' => '10 - '.date("F", mktime(0, 0, 0, 10, 10)),
                    '11' => '11 - '.date("F", mktime(0, 0, 0, 11, 10)), '12' => '12 - '.date("F", mktime(0, 0, 0, 12, 10))],
                    $histDate['month'], $monthClasses) !!}


                <select id="day_{{$flid}}" name="day_{{$flid}}" class="single-select preset-clear-chosen-js" data-placeholder="Select a Day" {{ $preDisabled ? 'disabled' : '' }}>
                    <option value=""></option>
                    @php
                        $i = 1;
                        while ($i <= 31) {
                            if($i==$histDate['day'])
                                echo "<option value=" . $i . " selected>" . $i . "</option>";
                            else
                                echo "<option value=" . $i . ">" . $i . "</option>";
                            $i++;
                        }
                    @endphp
                </select>

                <select id="year_{{$flid}}" name="year_{{$flid}}" class="single-select preset-clear-chosen-js" data-placeholder="Select a Year">
                    <option value=""></option>
                    @php
                        $i = $field['options']['Start'];
                        $j = $field['options']['End'];
                        while ($i <= $j) {
                            if($i==$histDate['year'])
                                echo "<option value=" . $i . " selected>" . $i . "</option>";
                            else
                                echo "<option value=" . $i . ">" . $i . "</option>";
                            $i++;
                        }
                    @endphp
                </select>
            </div>
        </div>

        @if($field['options']['ShowCirca'])
            <div class="form-group mt-xl">
                <div class="check-box-half">
                    <input type="checkbox" value="1" id="preset" class="check-box-input" name="{{'circa_'.$flid}}" {{ ($histDate['circa'] ? 'checked' : '') }}>
                    <span class="check"></span>
                    <span class="placeholder">Mark this date as an approximate (Circa)?</span>
                </div>
            </div>
        @endif

        @if($field['options']['ShowEra'])
            <div class="form-group mt-xl">
                <label>Select Calendar/Date Notation</label>
                <div class="check-box-half mr-m">
                    <input type="checkbox" value="CE" class="check-box-input era-check-js era-check-{{$flid}}-js" name="era_{{$flid}}" {{ ($histDate['era'] == 'CE' ? 'checked' : '') }} flid="{{$flid}}">
                    <span class="check"></span>
                    <span class="placeholder">CE</span>
                </div>

                <div class="check-box-half mr-m">
                    <input type="checkbox" value="BCE" class="check-box-input era-check-js era-check-{{$flid}}-js" name="era_{{$flid}}" {{ ($histDate['era'] == 'BCE' ? 'checked' : '') }} flid="{{$flid}}">
                    <span class="check"></span>
                    <span class="placeholder">BCE</span>
                </div>

                <div class="check-box-half mr-m">
                    <input type="checkbox" value="BP" class="check-box-input era-check-js era-check-{{$flid}}-js" name="era_{{$flid}}" {{ ($histDate['era'] == 'BP' ? 'checked' : '') }} flid="{{$flid}}">
                    <span class="check"></span>
                    <span class="placeholder">BP</span>
                </div>

                <div class="check-box-half">
                    <input type="checkbox" value="KYA BP" class="check-box-input era-check-js era-check-{{$flid}}-js" name="era_{{$flid}}" {{ ($histDate['era'] == 'KYA BP' ? 'checked' : '') }} flid="{{$flid}}">
                    <span class="check"></span>
                    <span class="placeholder">KYA BP</span>
                </div>
            </div>
        @endif
    </div>
</div>

<?php namespace App\KoraFields;

use Illuminate\Http\Request;

class TextField extends BaseField {

    /*
    |--------------------------------------------------------------------------
    | Text Field
    |--------------------------------------------------------------------------
    |
    | This model represents the text field in Kora3
    |
    */

    /**
     * @var string - Views for the typed field options //TODO::NEWFIELD
     */
    const FIELD_OPTIONS_VIEW = "partials.fields.options.text";
    const FIELD_ADV_OPTIONS_VIEW = "partials.fields.advanced.text";
    const FIELD_ADV_INPUT_VIEW = "partials.records.advanced.text";
    const FIELD_INPUT_VIEW = "partials.records.input.text";
    const FIELD_DISPLAY_VIEW = "partials.records.display.text";

    /**
     * Get the field options view.
     *
     * @return string - The view
     */
    public function getFieldOptionsView() {
        return self::FIELD_OPTIONS_VIEW;
    }

    /**
     * Get the field options view for advanced field creation.
     *
     * @return string - The view
     */
    public function getAdvancedFieldOptionsView() {
        return self::FIELD_ADV_OPTIONS_VIEW;
    }

    /**
     * Get the field input view for record creation.
     *
     * @return string - The view
     */
    public function getFieldInputView() {
        return self::FIELD_INPUT_VIEW;
    }

    /**
     * Gets the default options string for a new field.
     *
     * @param  int $fid - Form ID
     * @param  string $slug - Name of database column based on field internal name
     * @param  array $options - Extra information we may need to set up about the field
     * @return array - The default options
     */
    public function addDatabaseColumn($fid, $slug, $options = null) {
        $table = new \CreateRecordsTable();
        $table->addTextColumn($fid, $slug);
    }

    /**
     * Gets the default options string for a new field.
     *
     * @param  Request $request
     * @return array - The default options
     */
    public function getDefaultOptions() {
        return ['Regex' => '', 'MultiLine' => 0];
    }

    /**
     * Update the options for a field
     *
     * @param  array $field - Field to update options
     * @param  Request $request
     * @return array - The updated field array
     */
    public function updateOptions($field, Request $request) {
        if($request->regex!='') {
            $regArray = str_split($request->regex);
            if($regArray[0]!=end($regArray))
                $request->regex = '/'.$request->regex.'/';
        } else {
            $request->regex = null;
        }

        $field['default'] = $request->default;
        $field['options']['Regex'] = $request->regex;
        $field['options']['MultiLine'] = isset($request->multi) && $request->multi ? 1 : 0;

        return $field;
    }

    /**
     * Formats data for record entry.
     *
     * @param  array $field - The field to represent record data
     * @param  string $value - Data to add
     * @param  Request $request
     */
    public function processRecordData($field, $value, $request) {
        return $value;
    }
}
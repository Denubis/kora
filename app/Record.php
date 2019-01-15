<?php namespace App;

use App\Http\Controllers\RecordController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Record extends Model {

    /*
    |--------------------------------------------------------------------------
    | Record
    |--------------------------------------------------------------------------
    |
    | This model represents the data for a Record
    |
    */

    /**
     * @var array - Attributes that can be mass assigned to model
     */
    protected $fillable = [
        'kid',
        'legacy_kid',
        'project_id',
        'form_id',
        'owner'
    ];

    public function __construct(array $attributes = array(), $fid = null){
        parent::__construct($attributes);
        $this->table = "records_$fid";
    }

    /**
     * Returns the form associated with a Record.
     *
     * @return BelongsTo
     */
    public function form() {
        return $this->belongsTo('App\Form', 'form_id');
    }

    /**
     * Returns the owner associated with a Record.
     *
     * @return HasOne
     */
    public function owner() {
        return $this->hasOne('App\User', 'owner');
    }

    /**
     * Deletes all data fields belonging to a record, then deletes self.
     */
    public function delete() {
        //Delete reverse associations for everyone's sake //TODO::CASTLE

        parent::delete();
    }

    /**
     * Determines if the record is a record preset.
     *
     * @return bool - Is a preset
     */
    public function isPreset() { //TODO::CASTLE
        return (RecordPreset::where('rid',$this->rid)->count()>0);
    }

    /**
     * Determines if a string is a KID pattern.
     * For reference, the KID pattern is PID-FID-RID, i.e. three sets of integers separated by hyphens.
     *
     * @param $string - Kora ID to validate
     * @return bool - Matches pattern
     */
    public static function isKIDPattern($string) {
        $pattern = "/^([0-9]+)-([0-9]+)-([0-9]+)/"; // Match exactly with KID pattern.
        return preg_match($pattern, $string) != false;
    }

    /**
     * Gets a list of records that associate to this record
     *
     * @return array - Records that associate it
     */
    public function getAssociatedRecords() { //TODO::CASTLE
        $assoc = DB::table(AssociatorField::SUPPORT_NAME)
            ->select("rid")
            ->distinct()
            ->where('record','=',$this->rid)->get();
        $records = array();
        foreach($assoc as $af) {
            $rid = $af->rid;
            $rec = RecordController::getRecord($rid);
            array_push($records,$rec);
        }

        return $records;
    }

    /**
     * Gets a preview value for the record when displaying in a reverse association.
     *
     * @return string - The preview value
     */
    public function getReversePreview() { //TODO::CASTLE
        $form = $this->form()->first();

        $firstPage = Page::where('fid','=',$form->fid)->where('sequence','=',0)->first();
        $firstField = Field::where('page_id','=',$firstPage->id)->where('sequence','=',0)->first();

        $value = AssociatorField::previewData($firstField->flid, $this->rid, $firstField->type);

        if(!is_null($value) && $value!='')
            return $value;
        else
            return 'No Preview Field Available';
    }
}


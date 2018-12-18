<?php namespace App\Http\Controllers;

use App\Field;
use App\Form;
use App\Record;
use App\TextField;
use Illuminate\Support\Facades\DB;
use App\Metadata;
use App\OptionPreset;
use App\RecordPreset;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\Request;

class ExportController extends Controller {

    /*
    |--------------------------------------------------------------------------
    | Export Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the export process of Kora 3 structure and data
    |
    */

    /**
     * @var string - Valid formats for export
     */
    const JSON = "JSON";
    const XML = "XML";
    const KORA = "KORA_OLD";
    const META = "META";

    /**
     * @var array - Array of those formats
     */
    const VALID_FORMATS = [ self::JSON, self::XML, self::KORA, self::META ];

    /**
     * Constructs controller and makes sure user is authenticated.
     */
    public function __construct() {
        $this->middleware('auth');
        $this->middleware('active');
    }

    /**
     * Gathers and exports a forms records.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @param  string $type - Type of export format
     * @return Redirect
     */
    public function exportRecords($pid, $fid, $type) {
        if(!FormController::validProjForm($pid,$fid))
            return redirect('projects/'.$pid);

        $form = FormController::getForm($fid);

        if(!\Auth::user()->isFormAdmin($form))
            return redirect('projects/'.$pid.'/forms/'.$fid);

        $rids = DB::table("records")->where("fid", "=", $fid)->select("rid")->get()->toArray();

        // The DB call returns an array of StdObj so we get the rids out of the objects.
        $rids = array_map( function($obj) {
            return $obj->rid;
        }, $rids);

        //most of these are included to not break JSON, revAssoc is the only one that matters to us for this so we can get
        // the reverse associations. The others are only relevant to the API
        $options = ["revAssoc" => true, "meta" => false, "fields" => 'ALL', "realnames" => false, "assoc" => false];
        $output = $this->exportWithRids($rids, $type, false, $options);

        if(file_exists($output)) { // File exists, so we download it.
            header("Content-Disposition: attachment; filename=\"" . basename($output) . "\"");
            header("Content-Type: application/octet-stream");
            header("Content-Length: " . filesize($output));

            readfile($output);
            exit;
        } else { // File does not exist, so some kind of error occurred, and we redirect.
            return redirect("projects/" . $pid . "/forms/" . $fid . "/records");
        }
    }

    /**
     * Export a subset of records.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @param  string $type - Type of export format
     * @param  Request $request
     * @return Redirect
     */
    public function exportSelectedRecords($pid, $fid, $type, Request $request) {
      if(!FormController::validProjForm($pid,$fid))
        return redirect('projects/'.$pid.'/forms/'.$fid.'/records');

      $form = FormController::getForm($fid);

      if(!\Auth::user()->isFormAdmin($form))
        return redirect('projects/'.$pid.'/forms/'.$fid.'/records');

      $rids = $request->rid;
      $rids = array_map('intval', explode(',', $rids));

      $options = ["revAssoc" => true, "meta" => false, "fields" => 'ALL', "realnames" => false, "assoc" => false];
      $output = $this->exportWithRids($rids, $type, false, $options);

      if(file_exists($output)) { // File exists, so we download it.
          header("Content-Disposition: attachment; filename=\"" . basename($output) . "\"");
          header("Content-Type: application/octet-stream");
          header("Content-Length: " . filesize($output));

          readfile($output);
          exit;
      } else { // File does not exist, so some kind of error occurred, and we redirect.
          flash()->overlay(trans("records_index.exporterror"), trans("controller_admin.whoops"));
          return redirect("projects/" . $pid . "/forms/" . $fid . "/records");
      }
    }

    /**
     * Returns status of record export to notify completion.
     *
     * @param  int $fid - Form ID of form that's being export
     * @return string - Json array of the status
     */
    public function checkRecordExport($fid) {
        return response()->json(["status"=>true,"message"=>"record_export_progress",
            "finished" => !! DB::table("download_trackers")->where("fid", "=", $fid)->count()],200);
    }

    /**
     * To speed things up, this function preps record data files into a zip beforehand.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     */
    public function prepRecordFiles($pid, $fid) {
        if(!FormController::validProjForm($pid, $fid))
            return redirect('projects/'.$pid)->with('k3_global_error', 'form_invalid');

        $form = FormController::getForm($fid);

        if(!(\Auth::user()->isFormAdmin($form)))
            return redirect('projects/'.$pid)->with('k3_global_error', 'not_form_admin');

        $path = storage_path('app/files/p'.$pid.'/f'.$fid);
        $zipPath = storage_path('app/tmpFiles/'.$form->name.'_preppedZIP_user'.\Auth::user()->id.'.zip');

        $fileSizeCount = 0.0;

        // Initialize archive object
        $zip = new \ZipArchive();
        $zip->open($zipPath, (\ZipArchive::CREATE | \ZipArchive::OVERWRITE));

        if(file_exists($path)) {
            ini_set('max_execution_time',0);
            ini_set('memory_limit', "6G");

            //add files
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if($fileSizeCount > 5)
                    return response()->json(["status"=>false,"message"=>"zip_too_big"],500);

                // Skip directories (they would be added automatically)
                if(!$file->isDir()) {
                    // Get real and relative path for current file
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($path) + 1);

                    // Add current file to archive
                    $zip->addFile($filePath, $relativePath);

                    $fileSizeCount += number_format(filesize($filePath) / 1073741824, 2);
                }
            }
        } else {
            return response()->json(["status"=>false,"message"=>"no_record_files"],500);
        }

        // Zip archive will be created only after closing object
        $zip->close();

        return 'Success';
    }

    /**
     * Exports the files associated with the form records being exported.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @return string - The html to download the file
     */
    public function exportRecordFiles($pid, $fid) {
        if(!FormController::validProjForm($pid, $fid))
            return redirect('projects/'.$pid)->with('k3_global_error', 'form_invalid');

        $form = FormController::getForm($fid);

        if(!(\Auth::user()->isFormAdmin($form)))
            return redirect('projects/'.$pid)->with('k3_global_error', 'not_form_admin');

        $path = storage_path('app/files/p'.$pid.'/f'.$fid);
        $zipPath = storage_path('app/tmpFiles/');

        ini_set('max_execution_time',0);
        ini_set('memory_limit', "6G");

        $fileSizeCount = 0.0;

        if(file_exists($zipPath.$form->name.'_preppedZIP_user'.\Auth::user()->id.'.zip')) {
            $subPath = $form->name.'_preppedZIP_user'.\Auth::user()->id.'.zip';
        } else {
            $time = Carbon::now();
            $subPath = $form->name . '_fileData_' . $time . '.zip';

            // Initialize archive object
            $zip = new \ZipArchive();
            $zip->open($zipPath . $subPath, \ZipArchive::CREATE);

            if(file_exists($path)) {
                //add files
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach($files as $name => $file) {
                    if($fileSizeCount > 5)
                        return redirect('projects/' . $pid . '/forms/' . $fid)->with('k3_global_error', 'zip_too_big');

                    // Skip directories (they would be added automatically)
                    if(!$file->isDir()) {
                        // Get real and relative path for current file
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($path) + 1);

                        // Add current file to archive
                        $zip->addFile($filePath, $relativePath);

                        $fileSizeCount += number_format(filesize($filePath) / 1073741824, 2);
                    }
                }
            } else {
                return redirect('projects/' . $pid . '/forms/' . $fid)->with('k3_global_error', 'no_record_files');
            }

            // Zip archive will be created only after closing object
            $zip->close();
        }

        header('Content-Disposition: attachment; filename="'.$subPath.'"');
        header('Content-Type: application/zip; ');

        readfile($zipPath.$subPath);
        exit;
    }

    /**
     * Exports a form and its structure.
     *
     * @param  int $pid - Project ID
     * @param  int $fid - Form ID
     * @param  bool $download - Download as a file or as an array for the project export
     * @return mixed - Export file or data array
     */
    public function exportForm($pid, $fid, $download=true) {
        if(!FormController::validProjForm($pid, $fid))
            return redirect('projects/'.$pid)->with('k3_global_error', 'form_invalid');

        $form = FormController::getForm($fid);

        if(!(\Auth::user()->isFormAdmin($form)))
            return redirect('projects/'.$pid)->with('k3_global_error', 'not_form_admin');


        $formArray = array();

        $formArray['name'] = $form->name;
        $formArray['slug'] = $form->slug;
        $formArray['desc'] = $form->description;
        $formArray['preset'] = $form->preset;
        $formArray['metadata'] = $form->public_metadata;

        //Page
        $pages = $form->pages()->get();
        $formArray['pages'] = array();
        foreach($pages as $page) {
            $p = array();
            $p['id'] = $page->id;
            $p['title'] = $page->title;
            $p['sequence'] = $page->sequence;

            array_push($formArray['pages'],$p);
        }

        //record presets
        $recPresets = RecordPreset::where('fid','=',$fid)->get();
        $formArray['recPresets'] = array();
        foreach($recPresets as $pre) {
            $rec = array();
            $rec['name'] = $pre->name;
            $rec['preset'] = $pre->preset;

            array_push($formArray['recPresets'],$rec);
        }

        $fields = Field::where('fid','=',$form->fid)->get();
        $formArray['fields'] = array();

        foreach($fields as $field) {
            $fieldArray = array();

            $fieldArray['flid'] = $field->flid;
            $fieldArray['page_id'] = $field->page_id;
            $fieldArray['sequence'] = $field->sequence;
            $fieldArray['type'] = $field->type;
            $fieldArray['name'] = $field->name;
            $fieldArray['slug'] = $field->slug;
            $fieldArray['desc'] = $field->desc;
            $fieldArray['required'] = $field->required;
            $fieldArray['searchable'] = $field->searchable;
            $fieldArray['advsearch'] = $field->advsearch;
            $fieldArray['extsearch'] = $field->extsearch;
            $fieldArray['viewable'] = $field->viewable;
            $fieldArray['viewresults'] = $field->viewresults;
            $fieldArray['extview'] = $field->extview;
            $fieldArray['default'] = $field->default;
            $fieldArray['options'] = $field->options;

            $meta = Metadata::where('flid','=',$field->flid)->get()->first();
            if(!is_null($meta))
                $fieldArray['metadata'] = $meta->name;
            else
                $fieldArray['metadata'] = '';

            array_push($formArray['fields'],$fieldArray);
        }

        if($download) {
            header('Content-Disposition: attachment; filename="' . $form->name . '_Layout_' . Carbon::now() . '.k3Form"');
            header('Content-Type: application/octet-stream; ');

            echo json_encode($formArray);
            exit;
        } else {
            return $formArray;
        }
    }

    /**
     * Exports a project and its structure.
     *
     * @param  int $pid - Project ID
     * @return string - html for the file
     */
    public function exportProject($pid) {
        if(!ProjectController::validProj($pid))
            return redirect('projects')->with('k3_global_error', 'project_invalid');

        $proj = ProjectController::getProject($pid);

        if(!\Auth::user()->isProjectAdmin($proj))
            return redirect('projects')->with('k3_global_error', 'not_project_admin');

        $projArray = array();

        $projArray['name'] = $proj->name;
        $projArray['slug'] = $proj->slug;
        $projArray['description'] = $proj->description;

        //preset stuff
        $optPresets = OptionPreset::where('pid','=',$pid)->get();
        $projArray['optPresets'] = array();
        foreach($optPresets as $pre) {
            $opt = array();
            $opt['type'] = $pre->type;
            $opt['name'] = $pre->name;
            $opt['preset'] = $pre->preset;
            $opt['shared'] = $pre->shared;

            array_push($projArray['optPresets'],$opt);
        }

        $forms = Form::where('pid','=',$pid)->get();
        $projArray['forms'] = array();
        foreach($forms as $form) {
            array_push($projArray['forms'],$this->exportForm($pid,$form->fid,false));
        }

        header('Content-Disposition: attachment; filename="' . $proj->name . '_Layout_' . Carbon::now() . '.k3Proj"');
        header('Content-Type: application/octet-stream; ');

        echo json_encode($projArray);
        exit;
    }

    /**
     * Builds out the record data for the given RIDs. TODO::Modularize?
     *
     * @param  array $rids - The RIDs to gather data for
     * @param  string $format - File format to export
     * @param  string $dataOnly - No file, just data!
     * @param  array $options - Filters from the API that apply to the search (note: JSON only)
     * @return string - The system path to the exported file
     */
    public function exportWithRids($rids, $format = self::JSON, $dataOnly = false, $options = null) {
        $format = strtoupper($format);

        if(!self::isValidFormat($format))
            return null;

        $chunks = array_chunk($rids, 500);

        switch($format) {
            case self::JSON:
                $records = array();

                //There exist in case of assoc, but may just be empty
                $assocRIDColl = array();
                $assocMaster = array();

                //Check to see if we should bother with options
                $useOpts = !is_null($options);

                //First option to check is the fields we want back, so lets pull out the slugs from options
                $slugOpts = null;
                if($useOpts && $options['fields'] != 'ALL')
                    $slugOpts = $options['fields'];

                foreach($chunks as $chunk) {
                    //Next we see if metadata is requested
                    if($useOpts && $options['meta']) {
                        $meta = self::getRecordMetadata($chunk);
                        self::imitateMerge($records, $meta);
                    }

                    //specifically for file exports, NOT API
                    if($useOpts && isset($options['revAssoc']) && $options['revAssoc']) {
                        $meta = self::getReverseAssociations($chunk);
                        self::imitateMerge($records, $meta);
                    }

                    if($useOpts && isset($options['data']) && !$options['data']) {
                        $kids = self::getKidsFromRids($chunk);
                        foreach($kids as $kid) {
                            if(!isset($records[$kid]))
                                $records[$kid] = [];
                        }
                        continue;
                    }

                    $datafields = self::getDataRows($chunk,$slugOpts);

                    foreach($datafields as $data){
                        $kid = $data->pid.'-'.$data->fid.'-'.$data->rid;

                        if(!isset($records[$kid]))
                            $records[$kid] = [];

                        $fieldIndex = $data->slug;
                        if($useOpts && $options['realnames'])
                            $fieldIndex = $data->name;

                        switch($data->type) {
                            case Field::_TEXT:
                                $records[$kid][$fieldIndex]['value'] = $data->value;
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_RICH_TEXT:
                                $records[$kid][$fieldIndex]['value'] = $data->value;
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_NUMBER:
                                $records[$kid][$fieldIndex]['value'] = (float)$data->value;
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_LIST:
                                $records[$kid][$fieldIndex]['value'] = $data->value;
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_MULTI_SELECT_LIST:
                                $records[$kid][$fieldIndex]['value'] = explode('[!]',$data->value);
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_GENERATED_LIST:
                                $records[$kid][$fieldIndex]['value'] = explode('[!]',$data->value);
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_COMBO_LIST:
                                $value = array();
                                $dataone = explode('[!data!]',$data->value);
                                $datatwo = explode('[!data!]',$data->val2);
                                $numberone = explode('[!data!]',$data->val3);
                                $numbertwo = explode('[!data!]',$data->val4);
                                $typeone = explode('[Type]', explode('[!Field1!][Type]', $data->val5)[1])[0];
                                $typetwo = explode('[Type]', explode('[!Field2!][Type]', $data->val5)[1])[0];
                                if($typeone=='Number')
                                    $cnt = sizeof($numberone);
                                else
                                    $cnt = sizeof($dataone);
                                $nameone = explode('[Name]', explode('[Type][Name]', $data->val5)[1])[0];
                                $nametwo = explode('[Name]', explode('[Type][Name]', $data->val5)[2])[0];

                                for($c=0;$c<$cnt;$c++) {
                                    $val = [];

                                    switch($typeone) {
                                        case Field::_MULTI_SELECT_LIST:
                                        case Field::_GENERATED_LIST:
                                            $valone = explode('[!]',$dataone[$c]);
                                            break;
                                        case Field::_NUMBER:
                                            $valone = $numberone[$c];
                                            break;
                                        default:
                                            $valone = $dataone[$c];
                                            break;
                                    }

                                    switch($typetwo) {
                                        case Field::_MULTI_SELECT_LIST:
                                        case Field::_GENERATED_LIST:
                                            $valtwo = explode('[!]',$datatwo[$c]);
                                            break;
                                        case Field::_NUMBER:
                                            $valtwo = $numbertwo[$c];
                                            break;
                                        default:
                                            $valtwo = $datatwo[$c];
                                            break;
                                    }

                                    $val[$nameone] = $valone;
                                    $val[$nametwo] = $valtwo;

                                    array_push($value, $val);
                                }
                                $records[$kid][$fieldIndex]['value'] = $value;
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_DATE:
                                $records[$kid][$fieldIndex]['value'] = [
                                    'circa' => $data->value,
                                    'month' => $data->val2,
                                    'day' => $data->val3,
                                    'year' => $data->val4,
                                    'era' => $data->val5
                                ];
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_SCHEDULE:
                                $value = array();
                                $begin = explode('[!]',$data->value);
                                $cnt = sizeof($begin);
                                $end = explode('[!]',$data->val2);
                                $allday = explode('[!]',$data->val3);
                                $desc = explode('[!]',$data->val4);
                                for($i=0;$i<$cnt;$i++) {
                                    if($allday[$i]==1) {
                                        $formatBegin = date("m/d/Y", strtotime($begin[$i]));
                                        $formatEnd = date("m/d/Y", strtotime($end[$i]));
                                    } else {
                                        $formatBegin = date("m/d/Y h:i A", strtotime($begin[$i]));
                                        $formatEnd = date("m/d/Y h:i A", strtotime($end[$i]));
                                    }
                                    $info = [
                                        'begin' => $formatBegin,
                                        'end' => $formatEnd,
                                        'allday' => $allday[$i],
                                        'desc' => $desc[$i]
                                    ];
                                    array_push($value,$info);
                                }
                                $records[$kid][$fieldIndex]['value'] = $value;
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_GEOLOCATOR:
                                $value = array();
                                $desc = explode('[!]',$data->value);
                                $cnt = sizeof($desc);
                                $address = explode('[!]',$data->val2);
                                $latlon = explode('[!latlon!]',$data->val3);
                                $utm = explode('[!utm!]',$data->val4);
                                for($i=0;$i<$cnt;$i++) {
                                    $ll = explode('[!]',$latlon[$i]);
                                    $u = explode('[!]',$utm[$i]);
                                    $info = [
                                        'desc' => $desc[$i],
                                        'lat' => $ll[0],
                                        'lon' => $ll[1],
                                        'zone' => $u[0],
                                        'east' => $u[1],
                                        'north' => $u[2],
                                        'address' => $address[$i],
                                    ];

                                    array_push($value,$info);
                                }
                                $records[$kid][$fieldIndex]['value'] = $value;
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_DOCUMENTS:
                                $url = url('app/files/p'.$data->pid.'/f'.$data->fid.'/r'.$data->rid.'/fl'.$data->flid) . '/';
                                $value = array();
                                $files = explode('[!]',$data->value);
                                foreach($files as $file) {
                                    $info = [
                                        'name' => explode('[Name]',$file)[1],
                                        'size' => floatval(explode('[Size]',$file)[1])/1000 . " mb",
                                        'type' => explode('[Type]',$file)[1],
                                        'url' => $url.explode('[Name]',$file)[1]
                                    ];
                                    array_push($value,$info);
                                }
                                $records[$kid][$fieldIndex]['value'] = $value;
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_GALLERY:
                                $url = url('app/files/p'.$data->pid.'/f'.$data->fid.'/r'.$data->rid.'/fl'.$data->flid) . '/';
                                $value = array();
                                $files = explode('[!]',$data->value);
                                $captions = (!is_null($data->val2) && $data->val2!='') ? explode('[!]',$data->val2) : null;
                                for($gi=0;$gi<sizeof($files);$gi++) {
                                    $info = [
                                        'name' => explode('[Name]',$files[$gi])[1],
                                        'size' => floatval(explode('[Size]',$files[$gi])[1])/1000 . " mb",
                                        'type' => explode('[Type]',$files[$gi])[1],
                                        'url' => $url.explode('[Name]',$files[$gi])[1]
                                    ];
                                    if(!is_null($captions))
                                        $info['caption'] = $captions[$gi];
                                    else
                                        $info['caption']='';
                                    array_push($value,$info);
                                }
                                $records[$kid][$fieldIndex]['value'] = $value;
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_PLAYLIST:
                                $url = url('app/files/p'.$data->pid.'/f'.$data->fid.'/r'.$data->rid.'/fl'.$data->flid) . '/';
                                $value = array();
                                $files = explode('[!]',$data->value);
                                foreach($files as $file) {
                                    $info = [
                                        'name' => explode('[Name]',$file)[1],
                                        'size' => floatval(explode('[Size]',$file)[1])/1000 . " mb",
                                        'type' => explode('[Type]',$file)[1],
                                        'url' => $url.explode('[Name]',$file)[1]
                                    ];
                                    array_push($value,$info);
                                }
                                $records[$kid][$fieldIndex]['value'] = $value;
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_VIDEO:
                                $url = url('app/files/p'.$data->pid.'/f'.$data->fid.'/r'.$data->rid.'/fl'.$data->flid) . '/';
                                $value = array();
                                $files = explode('[!]',$data->value);
                                foreach($files as $file) {
                                    $info = [
                                        'name' => explode('[Name]',$file)[1],
                                        'size' => floatval(explode('[Size]',$file)[1])/1000 . " mb",
                                        'type' => explode('[Type]',$file)[1],
                                        'url' => $url.explode('[Name]',$file)[1]
                                    ];
                                    array_push($value,$info);
                                }
                                $records[$kid][$fieldIndex]['value'] = $value;
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_3D_MODEL:
                                $url = url('app/files/p'.$data->pid.'/f'.$data->fid.'/r'.$data->rid.'/fl'.$data->flid) . '/';
                                $value = array();
                                $files = explode('[!]',$data->value);
                                foreach($files as $file) {
                                    $info = [
                                        'name' => explode('[Name]',$file)[1],
                                        'size' => floatval(explode('[Size]',$file)[1])/1000 . " mb",
                                        'type' => explode('[Type]',$file)[1],
                                        'url' => $url.explode('[Name]',$file)[1]
                                    ];
                                    array_push($value,$info);
                                }
                                $records[$kid][$fieldIndex]['value'] = $value;
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            case Field::_ASSOCIATOR:
                                if($useOpts && $options['assoc']) {
                                    //First we need to format these kids as rids
                                    $akids = array();
                                    $vals = explode(',',$data->value);
                                    foreach($vals as $akid) {
                                        if(Record::isKIDPattern($akid)) {
                                            $arid = explode('-',$akid)[2];
                                            array_push($assocRIDColl,$arid);
                                            array_push($akids, $akid);
                                        }
                                    }

                                    $ainfo = [
                                        'kid' => $kid,
                                        'slug' => $data->slug,
                                        'name' => $fieldIndex,
                                        'akids' => $akids
                                    ];
                                    array_push($assocMaster,$ainfo);
                                } else {
                                    $records[$kid][$fieldIndex]['value'] = explode(',',$data->value);
                                }
                                $records[$kid][$fieldIndex]['type'] = $data->type;
                                break;
                            default:
                                break;
                        }
                    }
                }

                //assoc stuff
                if($useOpts && $options['assoc']) {
                    //simplify the duplicates
                    $arids = array_unique($assocRIDColl);
                    $aOpts = ["revAssoc" => false, "meta" => false, "fields" => 'ALL', "realnames" => $options['realnames'], "assoc" => false];
                    $assocData = json_decode($this->exportWithRids($arids, $format, true, $aOpts),true);
                    foreach($assocMaster as $am) {
                        $value = array();
                        $kid = $am['kid'];
                        if($options['realnames'])
                            $slug = $am['name'];
                        else
                            $slug = $am['slug'];
                        foreach($am['akids'] as $akid) {
                            $value[$akid] = $assocData[$akid];
                        }
                        $records[$kid][$slug]['value'] = $value;
                    }
                }

                $records = json_encode($records);

                if($dataOnly) {
                    return $records;
                } else {
                    $dt = new \DateTime();
                    $format = $dt->format('Y_m_d_H_i_s');
                    $path = storage_path("app/exports/record_export_$format.json");

                    file_put_contents($path, $records);

                    return $path;
                }
                break;
            case self::KORA:
                $records = array();

                //Check to see if we should bother with options
                $useOpts = !is_null($options);

                //First option to check is the fields we want back, so lets pull out the slugs from options
                $slugOpts = null;
                if($useOpts && $options['fields'] != 'ALL')
                    $slugOpts = $options['fields'];

                foreach($chunks as $chunk) {
                    if($slugOpts=='KID') {
                        $kids = self::getKidsFromRids($chunk);
                        self::imitateMerge($records, $kids);
                        continue;
                    }

                    $meta = self::getRecordMetadataForOldKora($chunk);
                    self::imitateMerge($records, $meta);

                    $datafields = self::getDataRows($chunk,$slugOpts);
                    foreach($datafields as $data) {
                        $kid = $data->pid.'-'.$data->fid.'-'.$data->rid;
                        $slug = str_replace('_'.$data->pid.'_'.$data->fid.'_', '', $data->slug);
                        if(!$useOpts || !$options['under'])
                            $slug = str_replace('_', ' ', $slug); //Now that the tag is gone, remove space fillers

                        switch($data->type) {
                            case Field::_TEXT:
                                $records[$kid][$slug] = $data->value;
                                break;
                            case Field::_NUMBER:
                                $records[$kid][$slug] = $data->value;
                                break;
                            case Field::_RICH_TEXT:
                                $records[$kid][$slug] = $data->value;
                                break;
                            case Field::_LIST:
                                $records[$kid][$slug] = $data->value;
                                break;
                            case Field::_MULTI_SELECT_LIST:
                                $records[$kid][$slug] = explode('[!]',$data->value);
                                break;
                            case Field::_GENERATED_LIST:
                                $records[$kid][$slug] = explode('[!]',$data->value);
                                break;
                            case Field::_DATE:
                                $records[$kid][$slug] = [
                                    'prefix' => $data->value,
                                    'month' => $data->val2,
                                    'day' => $data->val3,
                                    'year' => $data->val4,
                                    'era' => $data->val5,
                                    'suffix' => ''
                                ];
                                break;
                            case Field::_SCHEDULE:
                                $value = array();
                                $begin = explode('[!]',$data->value);
                                foreach($begin as $date) {
                                    $harddate = explode(' ',$date)[0];
                                    array_push($value,$harddate);
                                }

                                $records[$kid][$slug] = $value;
                                break;
                            case Field::_DOCUMENTS:
                                $url = 'r'.$data->rid.'/fl'.$data->flid . '/';
                                $files = explode('[!]',$data->value);
                                $file = $files[0];
                                $info = [
                                    'originalName' => explode('[Name]',$file)[1],
                                    'size' => floatval(explode('[Size]',$file)[1])/1000 . " mb",
                                    'type' => explode('[Type]',$file)[1],
                                    'localName' => $url.explode('[Name]',$file)[1]
                                ];

                                $records[$kid][$slug] = $info;
                                break;
                            case Field::_GALLERY:
                                $url = 'r'.$data->rid.'/fl'.$data->flid . '/';
                                $files = explode('[!]',$data->value);
                                $file = $files[0];
                                $info = [
                                    'originalName' => explode('[Name]',$file)[1],
                                    'size' => floatval(explode('[Size]',$file)[1])/1000 . " mb",
                                    'type' => explode('[Type]',$file)[1],
                                    'localName' => $url.explode('[Name]',$file)[1]
                                ];

                                $records[$kid][$slug] = $info;
                                break;
                            case Field::_ASSOCIATOR:
                                $records[$kid][$slug] = explode(',',$data->value);
                                break;
                            default:
                                break;
                        }
                    }
                }

                $emptyValueSlugs = [];

                //Add those blank values
                foreach($records as $kid => $data) {
                    $pid = explode('-',$kid)[0];
                    $fid = explode('-',$kid)[1];

                    //See if we already fetched these slugs
                    if(!array_key_exists($fid,$emptyValueSlugs)) {
                        $slugArray = FormController::getForm($fid)->fields()->pluck('slug')->toArray();
                        for($i=0;$i<sizeof($slugArray);$i++) {
                            $slug = $slugArray[$i];
                            $slugArray[$i] = str_replace('_'.$pid.'_'.$fid.'_', '', $slug);
                            if(!$useOpts || !$options['under'])
                                $slugArray[$i] = str_replace('_', ' ', $slugArray[$i]); //Now that the tag is gone, remove space fillers
                        }
                    } else {
                        $slugArray = $emptyValueSlugs[$fid];
                    }

                    foreach($slugArray as $slug) {
                        if(!isset($data[$slug]))
                            $records[$kid][$slug] = '';
                    }
                }

                return json_encode($records);
                break;
            case self::XML:
                $records = '<?xml version="1.0" encoding="utf-8"?><Records>';
                $recordData = [];

                //Check to see if we should bother with options
                $useOpts = !is_null($options);

                foreach($chunks as $chunk) {
                    $datafields = self::getDataRows($chunk);

                    foreach($datafields as $data) {
                        $kid = $data->pid.'-'.$data->fid.'-'.$data->rid;

                        $fieldxml = '<'.$data->slug.' type="'.$data->type.'">';
                        switch($data->type) {
                            case Field::_TEXT:
                                $fieldxml .= htmlspecialchars($data->value, ENT_XML1, 'UTF-8');
                                break;
                            case Field::_RICH_TEXT:
                                $fieldxml .= htmlspecialchars($data->value, ENT_XML1, 'UTF-8');
                                break;
                            case Field::_NUMBER:
                                $fieldxml .= htmlspecialchars((float)$data->value, ENT_XML1, 'UTF-8');
                                break;
                            case Field::_LIST:
                                $fieldxml .= htmlspecialchars($data->value, ENT_XML1, 'UTF-8');
                                break;
                            case Field::_MULTI_SELECT_LIST:
                                $opts = explode('[!]',$data->value);
                                foreach($opts as $opt) {
                                    $fieldxml .= '<value>'.htmlspecialchars($opt, ENT_XML1, 'UTF-8').'</value>';
                                }
                                break;
                            case Field::_GENERATED_LIST:
                                $opts = explode('[!]',$data->value);
                                foreach($opts as $opt) {
                                    $fieldxml .= '<value>'.htmlspecialchars($opt, ENT_XML1, 'UTF-8').'</value>';
                                }
                                break;
                            case Field::_COMBO_LIST:
                                $dataone = explode('[!data!]',$data->value);
                                $datatwo = explode('[!data!]',$data->val2);
                                $numberone = explode('[!data!]',$data->val3);
                                $numbertwo = explode('[!data!]',$data->val4);
                                $typeone = explode('[Type]', explode('[!Field1!][Type]', $data->val5)[1])[0];
                                $typetwo = explode('[Type]', explode('[!Field2!][Type]', $data->val5)[1])[0];
                                if($typeone=='Number')
                                    $cnt = sizeof($numberone);
                                else
                                    $cnt = sizeof($dataone);
                                $nameone = explode('[Name]', explode('[Type][Name]', $data->val5)[1])[0];
                                $nametwo = explode('[Name]', explode('[Type][Name]', $data->val5)[2])[0];

                                for($c=0;$c<$cnt;$c++) {
                                    switch($typeone) {
                                        case Field::_MULTI_SELECT_LIST:
                                        case Field::_GENERATED_LIST:
                                            $valone = '';
                                            $vals = explode('[!]',$dataone[$c]);
                                            foreach($vals as $v) {
                                                $valone .= '<value>'.htmlspecialchars($v, ENT_XML1, 'UTF-8').'</value>';
                                            }
                                            break;
                                        case Field::_NUMBER:
                                            $valone = htmlspecialchars($numberone[$c], ENT_XML1, 'UTF-8');
                                            break;
                                        default:
                                            $valone = htmlspecialchars($dataone[$c], ENT_XML1, 'UTF-8');
                                            break;
                                    }

                                    switch($typetwo) {
                                        case Field::_MULTI_SELECT_LIST:
                                        case Field::_GENERATED_LIST:
                                            $valtwo = '';
                                            $vals = explode('[!]',$datatwo[$c]);
                                            foreach($vals as $v) {
                                                $valtwo .= '<value>'.htmlspecialchars($v, ENT_XML1, 'UTF-8').'</value>';
                                            }
                                            break;
                                        case Field::_NUMBER:
                                            $valtwo = htmlspecialchars($numbertwo[$c], ENT_XML1, 'UTF-8');
                                            break;
                                        default:
                                            $valtwo = htmlspecialchars($datatwo[$c], ENT_XML1, 'UTF-8');
                                            break;
                                    }

                                    $fieldxml .= '<Value><'.$nameone.'>'.$valone.'</'.$nameone.'><'.$nametwo.'>'.$valtwo.'</'.$nametwo.'></Value>';
                                }
                                break;
                            case Field::_DATE:
                                $fieldxml .= '<Circa>'.$data->value.'</Circa>';
                                $fieldxml .= '<Month>'.$data->val2.'</Month>';
                                $fieldxml .= '<Day>'.$data->val3.'</Day>';
                                $fieldxml .= '<Year>'.$data->val4.'</Year>';
                                $fieldxml .= '<Era>'.$data->val5.'</Era>';
                                break;
                            case Field::_SCHEDULE:
                                $begin = explode('[!]',$data->value);
                                $cnt = sizeof($begin);
                                $end = explode('[!]',$data->val2);
                                $allday = explode('[!]',$data->val3);
                                $desc = explode('[!]',$data->val4);
                                for($i=0;$i<$cnt;$i++) {
                                    if($allday[$i]==1) {
                                        $formatBegin = date("m/d/Y", strtotime($begin[$i]));
                                        $formatEnd = date("m/d/Y", strtotime($end[$i]));
                                    } else {
                                        $formatBegin = date("m/d/Y h:i A", strtotime($begin[$i]));
                                        $formatEnd = date("m/d/Y h:i A", strtotime($end[$i]));
                                    }
                                    $fieldxml .= '<Event>';
                                    $fieldxml .= '<Title>' . htmlspecialchars($desc[$i], ENT_XML1, 'UTF-8') . '</Title>';
                                    $fieldxml .= '<Begin>' . htmlspecialchars($formatBegin, ENT_XML1, 'UTF-8') . '</Begin>';
                                    $fieldxml .= '<End>' . htmlspecialchars($formatEnd, ENT_XML1, 'UTF-8') . '</End>';
                                    $fieldxml .= '<All_Day>' . htmlspecialchars($allday[$i], ENT_XML1, 'UTF-8') . '</All_Day>';
                                    $fieldxml .= '</Event>';
                                }
                                break;
                            case Field::_GEOLOCATOR:
                                $value = array();
                                $desc = explode('[!]',$data->value);
                                $cnt = sizeof($desc);
                                $address = explode('[!]',$data->val2);
                                $latlon = explode('[!latlon!]',$data->val3);
                                $utm = explode('[!utm!]',$data->val4);
                                for($i=0;$i<$cnt;$i++) {
                                    $ll = explode('[!]',$latlon[$i]);
                                    $u = explode('[!]',$utm[$i]);
                                    $fieldxml .= '<Location>';
                                    $fieldxml .= '<Desc>' .$desc[$i]. '</Desc>';
                                    $fieldxml .= '<Lat>' .$ll[0]. '</Lat>';
                                    $fieldxml .= '<Lon>' .$ll[1]. '</Lon>';
                                    $fieldxml .= '<Zone>' .$u[0]. '</Zone>';
                                    $fieldxml .= '<East>' .$u[1]. '</East>';
                                    $fieldxml .= '<North>' .$u[2]. '</North>';
                                    $fieldxml .= '<Address>' .$address[$i]. '</Address>';
                                    $fieldxml .= '</Location>';
                                }
                                break;
                            case Field::_DOCUMENTS:
                                $url = url('app/files/p'.$data->pid.'/f'.$data->fid.'/r'.$data->rid.'/fl'.$data->flid) . '/';
                                $files = explode('[!]',$data->value);
                                foreach($files as $file) {
                                    $fieldxml .= '<File>';
                                    $fieldxml .= '<Name>' . htmlspecialchars(explode('[Name]',$file)[1], ENT_XML1, 'UTF-8') . '</Name>';
                                    $fieldxml .= '<Size>' . floatval(explode('[Size]',$file)[1])/1000 . ' mb</Size>';
                                    $fieldxml .= '<Type>' . explode('[Type]',$file)[1] . '</Type>';
                                    $fieldxml .= '<Url>' . htmlspecialchars($url.explode('[Name]',$file)[1], ENT_XML1, 'UTF-8') . '</Url>';
                                    $fieldxml .= '</File>';
                                }
                                break;
                            case Field::_GALLERY:
                                $url = url('app/files/p'.$data->pid.'/f'.$data->fid.'/r'.$data->rid.'/fl'.$data->flid) . '/';
                                $files = explode('[!]',$data->value);
                                $captions = (!is_null($data->val2) && $data->val2!='') ? explode('[!]',$data->val2) : null;
                                for($gi=0;$gi<sizeof($files);$gi++) {
                                    $fieldxml .= '<File>';
                                    $fieldxml .= '<Name>' . htmlspecialchars(explode('[Name]',$files[$gi])[1], ENT_XML1, 'UTF-8') . '</Name>';
                                    if(!is_null($captions))
                                        $fieldxml .= '<Caption>' . htmlspecialchars($captions[$gi], ENT_XML1, 'UTF-8') . '</Caption>';
                                    else
                                        $fieldxml .= '<Caption></Caption>';
                                    $fieldxml .= '<Size>' . floatval(explode('[Size]',$files[$gi])[1])/1000 . ' mb</Size>';
                                    $fieldxml .= '<Type>' . explode('[Type]',$files[$gi])[1] . '</Type>';
                                    $fieldxml .= '<Url>' . htmlspecialchars($url.explode('[Name]',$files[$gi])[1], ENT_XML1, 'UTF-8') . '</Url>';
                                    $fieldxml .= '</File>';
                                }
                                break;
                            case Field::_PLAYLIST:
                                $url = url('app/files/p'.$data->pid.'/f'.$data->fid.'/r'.$data->rid.'/fl'.$data->flid) . '/';
                                $files = explode('[!]',$data->value);
                                foreach($files as $file) {
                                    $fieldxml .= '<File>';
                                    $fieldxml .= '<Name>' . htmlspecialchars(explode('[Name]',$file)[1], ENT_XML1, 'UTF-8') . '</Name>';
                                    $fieldxml .= '<Size>' . floatval(explode('[Size]',$file)[1])/1000 . ' mb</Size>';
                                    $fieldxml .= '<Type>' . explode('[Type]',$file)[1] . '</Type>';
                                    $fieldxml .= '<Url>' . htmlspecialchars($url.explode('[Name]',$file)[1], ENT_XML1, 'UTF-8') . '</Url>';
                                    $fieldxml .= '</File>';
                                }
                                break;
                            case Field::_VIDEO:
                                $url = url('app/files/p'.$data->pid.'/f'.$data->fid.'/r'.$data->rid.'/fl'.$data->flid) . '/';
                                $files = explode('[!]',$data->value);
                                foreach($files as $file) {
                                    $fieldxml .= '<File>';
                                    $fieldxml .= '<Name>' . htmlspecialchars(explode('[Name]',$file)[1], ENT_XML1, 'UTF-8') . '</Name>';
                                    $fieldxml .= '<Size>' . floatval(explode('[Size]',$file)[1])/1000 . ' mb</Size>';
                                    $fieldxml .= '<Type>' . explode('[Type]',$file)[1] . '</Type>';
                                    $fieldxml .= '<Url>' . htmlspecialchars($url.explode('[Name]',$file)[1], ENT_XML1, 'UTF-8') . '</Url>';
                                    $fieldxml .= '</File>';
                                }
                                break;
                            case Field::_3D_MODEL:
                                $url = url('app/files/p'.$data->pid.'/f'.$data->fid.'/r'.$data->rid.'/fl'.$data->flid) . '/';
                                $files = explode('[!]',$data->value);
                                foreach($files as $file) {
                                    $fieldxml .= '<File>';
                                    $fieldxml .= '<Name>' . htmlspecialchars(explode('[Name]',$file)[1], ENT_XML1, 'UTF-8') . '</Name>';
                                    $fieldxml .= '<Size>' . floatval(explode('[Size]',$file)[1])/1000 . ' mb</Size>';
                                    $fieldxml .= '<Type>' . explode('[Type]',$file)[1] . '</Type>';
                                    $fieldxml .= '<Url>' . htmlspecialchars($url.explode('[Name]',$file)[1], ENT_XML1, 'UTF-8') . '</Url>';
                                    $fieldxml .= '</File>';
                                }
                                break;
                            case Field::_ASSOCIATOR:
                                $aRecs = explode(',',$data->value);
                                $fieldxml .= '<Record>'.implode('</Record><Record>',$aRecs).'</Record>';
                                break;
                            default:
                                break;
                        }

                        $fieldxml .= '</'.$data->slug.'>';

                        if(isset($recordData[$kid]))
                            $recordData[$kid] .= $fieldxml;
                        else
                            $recordData[$kid] = $fieldxml;
                    }

                    //Next we see if metadata is requested
                    if($useOpts && $options['revAssoc']) {
                        $meta = self::getReverseAssociations($chunk);
                        foreach($meta as $mkid => $mt) {
                            if(isset($mt['reverseAssociations'])) {
                                $raXML = '<reverseAssociations>';
                                foreach ($mt['reverseAssociations'] as $flid => $rAssocs) {
                                    foreach($rAssocs as $rA) {
                                        $raXML .= "<Record flid='$flid'>$rA</Record>";
                                    }
                                }
                                $raXML .= '</reverseAssociations>';

                                if (isset($recordData[$kid]))
                                    $recordData[$mkid] .= $raXML;
                                else
                                    $recordData[$mkid] = $raXML;
                            }
                        }
                    }
                }

                //Now we have an array of kids to their field data
                //We need to loop back and add them to the xml
                foreach($recordData as $kid => $data) {
                    $records .= "<Record kid='$kid'>$data</Record>";
                }

                $records .= '</Records>';

                if($dataOnly) {
                    return $records;
                } else {
                    $dt = new \DateTime();
                    $format = $dt->format('Y_m_d_H_i_s');
                    $path = storage_path("app/exports/record_export_$format.xml");

                    file_put_contents($path, $records);

                    return $path;
                }
            case self::META:
                //Check to see if any records in form
                if(sizeof($rids)==0)
                    return "no_records";

                //We need one rid from the set to determine the project and form used in this metadata
                $tempRid = $rids[0];
                $tempKid = Record::where('rid','=',$tempRid)->first()->kid;
                $kidParts = explode('-',$tempKid);

                $resourceTitle = Form::where('fid','=',$kidParts[1])->first()->lod_resource;
                $metaUrl = url("projects/".$kidParts[0]."/forms/".$kidParts[1]."/metadata/public#");

                $records = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" ';
                $records .= 'xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#" ';
                $records .= "xmlns:$resourceTitle=\"$metaUrl\">";
                $recordData = [];

                foreach($chunks as $chunk) {
                    $datafields = self::getDataRows($chunk);

                    foreach($datafields as $data) {
                        $kid = $data->pid.'-'.$data->fid.'-'.$data->rid;
                        $metaObj = Metadata::where('flid','=',$data->flid)->first();
                        if(is_null($metaObj))
                            continue;

                        $metaFieldName = $metaObj->name;

                        if($data->type==Field::_ASSOCIATOR)
                            $fieldxml = "<".$resourceTitle.":".$metaFieldName.">";
                        else
                            $fieldxml = "<".$resourceTitle.":".$metaFieldName." rdf:parseType=\"Collection\">";

                        switch($data->type) {
                            case Field::_TEXT:
                                $fieldxml .= htmlspecialchars($data->value, ENT_XML1, 'UTF-8');
                                break;
                            case Field::_NUMBER:
                                $fieldxml .= htmlspecialchars((float)$data->value, ENT_XML1, 'UTF-8');
                                break;
                            case Field::_LIST:
                                $fieldxml .= htmlspecialchars($data->value, ENT_XML1, 'UTF-8');
                                break;
                            case Field::_MULTI_SELECT_LIST:
                                $fieldxml .= '<rdf:Seq>';
                                $opts = explode('[!]',$data->value);
                                foreach($opts as $opt) {
                                    $fieldxml .= '<rdf:li>'.htmlspecialchars($opt, ENT_XML1, 'UTF-8').'</rdf:li>';
                                }
                                $fieldxml .= '</rdf:Seq>';
                                break;
                            case Field::_GENERATED_LIST:
                                $fieldxml .= '<rdf:Seq>';
                                $opts = explode('[!]',$data->value);
                                foreach($opts as $opt) {
                                    $fieldxml .= '<rdf:li>'.htmlspecialchars($opt, ENT_XML1, 'UTF-8').'</rdf:li>';
                                }
                                $fieldxml .= '</rdf:Seq>';
                                break;
                            case Field::_DATE:
                                $info = "";
                                if($data->value==1)
                                    $info .= 'circa ';
                                if($data->val2!="")
                                    $info .= date("F", mktime(0, 0, 0, $data->val2, 10)).' ';
                                if($data->val3!="")
                                    $info .= $data->val3.' ';
                                if($data->val4!="")
                                    $info .= $data->val4.' ';
                                if($data->val5!="")
                                    $info .= $data->val5.' ';

                                $fieldxml .= htmlspecialchars(trim($info), ENT_XML1, 'UTF-8');
                                break;
                            case Field::_GEOLOCATOR:
                                $fieldxml .= '<rdf:Seq>';

                                $latlon = explode('[!latlon!]',$data->val3);
                                $desc = explode('[!]',$data->value);
                                $cnt = sizeof($desc);

                                for($i=0;$i<$cnt;$i++) {
                                    $ll = explode('[!]',$latlon[$i]);
                                    $lat = "<geo:lat>".$ll[0]."</geo:lat>";
                                    $long = "<geo:long>".$ll[1]."</geo:long>";
                                    $fieldxml .= "<geo:Point>".$lat.$long."</geo:Point>";
                                }
                                $fieldxml .= '</rdf:Seq>';
                                break;
                            case Field::_ASSOCIATOR:
                                $aRecs = explode(',',$data->value);

                                foreach($aRecs as $aRec) {
                                    $aKidParts = explode('-',$aRec);

                                    $aPrimary = Metadata::where('fid','=',$aKidParts[1])->where('primary','=',1)->first()->flid;
                                    $aResourceIndexValue = TextField::where('flid','=',$aPrimary)->where('rid','=',$aKidParts[2])->first()->text;

                                    $fieldxml .= "<rdf:Description rdf:about=\""
                                        .url("projects/".$aKidParts[0]."/forms/".$aKidParts[1]."/metadata/public/$aResourceIndexValue")."\" />";
                                }
                                break;
                            default:
                                break;
                        }

                        $fieldxml .= "</".$resourceTitle.":".$metaFieldName.">";

                        if(isset($recordData[$kid]))
                            $recordData[$kid] .= $fieldxml;
                        else
                            $recordData[$kid] = $fieldxml;
                    }
                }

                //Now we have an array of kids to their field data
                //We need to loop back and add them to the xml
                foreach($recordData as $kid => $data) {
                    $records .= "<rdf:Description ";

                    $parts = explode('-',$kid);
                    $primary = Metadata::where('fid','=',$parts[1])->where('primary','=',1)->first()->flid;
                    $resourceIndexValue = TextField::where('flid','=',$primary)->where('rid','=',$parts[2])->first()->text;

                    $records .= "rdf:about=\"".url("projects/".$parts[0]."/forms/".$parts[1]."/metadata/public/".$resourceIndexValue)."\">";
                    $records .= "$data</rdf:Description>";
                }

                $records .= '</rdf:RDF>';

                if($dataOnly) {
                    return $records;
                } else {
                    $dt = new \DateTime();
                    $format = $dt->format('Y_m_d_H_i_s');
                    $path = storage_path("app/exports/record_export_$format.rdf");

                    file_put_contents($path, $records);

                    return $path;
                }
            default:
                return '';
                break;
        }
    }

    /**
     * Get the data rows back for a set of records.
     *
     * @param  int $rids - Record IDs
     * @return array - Data for the records
     */
    public static function getDataRows($rids, $slugOpts=null) {
        $prefix = config('database.connections.mysql.prefix');
        $ridArray = implode(', ',$rids);
        $slugQL = '';
        if(!is_null($slugOpts)) {
            foreach($slugOpts as $slug) {
                $slugQL .= "'$slug',";
            }
            $slugQL = ' and fl.slug in ('.substr($slugQL, 0, -1).')';
        }

        DB::statement("SET SESSION group_concat_max_len = 12345;");

        return DB::select("SELECT tf.rid as `rid`, tf.text as `value`, NULL as `val2`, NULL as `val3`, NULL as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."text_fields as tf left join ".$prefix."fields as fl on tf.flid=fl.flid where tf.rid in ($ridArray)$slugQL 
union all

SELECT nf.rid as `rid`, nf.number as `value`, NULL as `val2`, NULL as `val3`, NULL as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."number_fields as nf left join ".$prefix."fields as fl on nf.flid=fl.flid where nf.rid in ($ridArray)$slugQL 
union all

SELECT rtf.rid as `rid`, rtf.rawtext as `value`, NULL as `val2`, NULL as `val3`, NULL as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."rich_text_fields as rtf left join ".$prefix."fields as fl on rtf.flid=fl.flid where rtf.rid in ($ridArray)$slugQL 
union all

SELECT lf.rid as `rid`, lf.option as `value`, NULL as `val2`, NULL as `val3`, NULL as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."list_fields as lf left join ".$prefix."fields as fl on lf.flid=fl.flid where lf.rid in ($ridArray)$slugQL 
union all

SELECT mslf.rid as `rid`, mslf.options as `value`, NULL as `val2`, NULL as `val3`, NULL as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."multi_select_list_fields as mslf left join ".$prefix."fields as fl on mslf.flid=fl.flid where mslf.rid in ($ridArray)$slugQL 
union all

SELECT glf.rid as `rid`, glf.options as `value`, NULL as `val2`, NULL as `val3`, NULL as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."generated_list_fields as glf left join ".$prefix."fields as fl on glf.flid=fl.flid where glf.rid in ($ridArray)$slugQL 
union all

SELECT clf.rid as `rid`, GROUP_CONCAT(if(clf.field_num=1, clf.data, null) ORDER BY clf.list_index ASC SEPARATOR '[!data!]' ) as `value`, 
GROUP_CONCAT(if(clf.field_num=2, clf.data, null) ORDER BY clf.list_index ASC SEPARATOR '[!data!]' ) as `val2`, 
GROUP_CONCAT(if(clf.field_num=1, clf.number, null) ORDER BY clf.list_index ASC SEPARATOR '[!data!]' ) as `val3`, 
GROUP_CONCAT(if(clf.field_num=2, clf.number, null) ORDER BY clf.list_index ASC SEPARATOR '[!data!]' ) as `val4`, fl.options as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."combo_support as clf left join ".$prefix."fields as fl on clf.flid=fl.flid where clf.rid in ($ridArray)$slugQL group by `rid`, `flid` 
union all

SELECT df.rid as `rid`, df.circa as `value`, df.month as `val2`,df.day as `val3`,df.year as `val4`,df.era as `val5`,fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."date_fields as df left join ".$prefix."fields as fl on df.flid=fl.flid where df.rid in ($ridArray)$slugQL 
union all

SELECT sf.rid as `rid`, GROUP_CONCAT(sf.begin SEPARATOR '[!]') as `value`, GROUP_CONCAT(sf.end SEPARATOR '[!]') as `val2`, GROUP_CONCAT(sf.allday SEPARATOR '[!]') as `val3`, 
GROUP_CONCAT(sf.desc SEPARATOR '[!]') as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."schedule_support as sf left join ".$prefix."fields as fl on sf.flid=fl.flid where sf.rid in ($ridArray)$slugQL group by `rid`, `flid` 
union all

SELECT docf.rid as `rid`, docf.documents as `value`, NULL as `val2`, NULL as `val3`, NULL as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."documents_fields as docf left join ".$prefix."fields as fl on docf.flid=fl.flid where docf.rid in ($ridArray)$slugQL 
union all

SELECT galf.rid as `rid`, galf.images as `value`, galf.captions as `val2`, NULL as `val3`, NULL as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."gallery_fields as galf left join ".$prefix."fields as fl on galf.flid=fl.flid where galf.rid in ($ridArray)$slugQL 
union all

SELECT pf.rid as `rid`, pf.audio as `value`, NULL as `val2`, NULL as `val3`, NULL as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."playlist_fields as pf left join ".$prefix."fields as fl on pf.flid=fl.flid where pf.rid in ($ridArray)$slugQL 
union all

SELECT vf.rid as `rid`, vf.video as `value`, NULL as `val2`, NULL as `val3`, NULL as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."video_fields as vf left join ".$prefix."fields as fl on vf.flid=fl.flid where vf.rid in ($ridArray)$slugQL 
union all

SELECT mf.rid as `rid`, mf.model as `value`, NULL as `val2`, NULL as `val3`, NULL as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."model_fields as mf left join ".$prefix."fields as fl on mf.flid=fl.flid where mf.rid in ($ridArray)$slugQL 
union all

SELECT gf.rid as `rid`, GROUP_CONCAT(gf.desc SEPARATOR '[!]') as `value`, GROUP_CONCAT(gf.address SEPARATOR '[!]') as `val2`, GROUP_CONCAT(CONCAT_WS('[!]', gf.lat, gf.lon) SEPARATOR '[!latlon!]') as `val3`, GROUP_CONCAT(CONCAT_WS('[!]', gf.zone, gf.easting, gf.northing) SEPARATOR '[!utm!]') as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."geolocator_support as gf left join ".$prefix."fields as fl on gf.flid=fl.flid where gf.rid in ($ridArray)$slugQL group by `rid`, `flid` 
union all

SELECT af.rid as `rid`, GROUP_CONCAT(aRec.kid SEPARATOR ',') as `value`, NULL as `val2`, NULL as `val3`, NULL as `val4`, NULL as `val5`, fl.slug, fl.type, fl.pid, fl.fid, fl.flid, fl.name 
FROM ".$prefix."associator_support as af left join ".$prefix."fields as fl on af.flid=fl.flid left join ".$prefix."records as aRec on af.record=aRec.rid where af.rid in ($ridArray)$slugQL group by `rid`, `flid` ORDER BY field(`rid`, $ridArray) ;");
    }

    /**
     * Get the metadeta back for a set of records.
     *
     * @param  int $rid - Record IDs
     * @return array - Metadata for the records
     */
    public static function getRecordMetadata($rids) {
        $prefix = config('database.connections.mysql.prefix');
        $meta = [];
        $kidPairs = [];
        $rid = implode(', ',$rids);

        //Doing this for pretty much the same reason as keyword search above
        $con = mysqli_connect(
            config('database.connections.mysql.host'),
            config('database.connections.mysql.username'),
            config('database.connections.mysql.password'),
            config('database.connections.mysql.database')
        );

        //We want to make sure we are doing things in utf8 for special characters
        if(!mysqli_set_charset($con, "utf8")) {
            printf("Error loading character set utf8: %s\n", mysqli_error($con));
            exit();
        }

        $select = "SELECT r.rid, r.kid, r.created_at, r.updated_at, u.username FROM ".$prefix."records as r LEFT JOIN ".$prefix."users as u on r.owner=u.id WHERE r.rid in ($rid) ORDER BY field(r.rid, $rid)";
        $metaResults = $con->query($select);

        while($row = $metaResults->fetch_assoc()) {
            $meta[$row["kid"]]["created"] = $row["created_at"];
            $meta[$row["kid"]]["updated"] = $row["updated_at"];
            $meta[$row["kid"]]["owner"] = $row["username"];
            $kidPairs[$row["rid"]] = $row["kid"];
        }

        $select = "SELECT aSupp.record as main, recs.kid as linker FROM ".$prefix."associator_support as aSupp LEFT JOIN ".$prefix."records as recs on aSupp.rid=recs.rid WHERE aSupp.record in ($rid)";
        $revResults = $con->query($select);

        while($row = $revResults->fetch_assoc()) {
            $meta[$kidPairs[$row["main"]]]["reverseAssociations"][] = $row["linker"];
        }

        mysqli_close($con);

        return $meta;
    }

    /**
     * Get the reverse associations back for a set of records.
     *
     * @param  int $rid - Record IDs
     * @return array - Reverse associations for the records
     */
    public static function getReverseAssociations($rids) {
        $prefix = config('database.connections.mysql.prefix');
        $meta = [];
        $rid = implode(', ',$rids);

        $part1 = DB::select("SELECT r.rid, r.kid FROM ".$prefix."records as r LEFT JOIN ".$prefix."users as u on r.owner=u.id WHERE r.rid in ($rid) ORDER BY field(r.rid, $rid)");
        foreach($part1 as $row) {
            $kidPairs[$row->rid] = $row->kid;
        }

        $results = DB::select("SELECT aSupp.record as main, aSupp.flid as flid, recs.kid as linker FROM ".$prefix."associator_support as aSupp LEFT JOIN ".$prefix."records as recs on aSupp.rid=recs.rid WHERE aSupp.record in ($rid)");

        foreach($results as $row) {
            $meta[$kidPairs[$row->main]]["reverseAssociations"][$row->flid][] = $row->linker;
        }

        return $meta;
    }

    /**
     * Get the metadeta back for a set of records from a legacy koraSearch.
     *
     * @param  int $rid - Record IDs
     * @return array - Metadata for the records
     */
    public static function getRecordMetadataForOldKora($rids) {
        $prefix = config('database.connections.mysql.prefix');
        $meta = array();
        $kidPairs = [];
        $rid = implode(', ',$rids);

        $part1 = DB::select("SELECT r.rid, r.kid, r.legacy_kid, r.pid, r.fid, r.updated_at, u.username FROM ".$prefix."records as r LEFT JOIN ".$prefix."users as u on r.owner=u.id WHERE r.rid in ($rid) ORDER BY field(r.rid, $rid)");
        foreach($part1 as $row) {
            $meta[$row->kid]["kid"] = $row->kid;
            $meta[$row->kid]["legacy_kid"] = $row->legacy_kid;
            $meta[$row->kid]["pid"] = $row->pid;
            $meta[$row->kid]["schemeID"] = $row->fid;
            $meta[$row->kid]["systimestamp"] = $row->updated_at;
            $meta[$row->kid]["recordowner"] = $row->username;
            $kidPairs[$row->rid] = $row->kid;
        }

        $part2 = DB::select("SELECT aSupp.record as main, recs.kid as linker FROM ".$prefix."associator_support as aSupp LEFT JOIN ".$prefix."records as recs on aSupp.rid=recs.rid WHERE aSupp.record in ($rid)");

        foreach($part2 as $row) {
            $meta[$kidPairs[$row->main]]["linkers"][] = $row->linker;
        }

        return $meta;
    }

    /**
     * Get the kids back for a set of records for a KID koraSearch.
     *
     * @param  int $rid - Record IDs
     * @return array - KIDs for the records
     */
    public static function getKidsFromRids($rids) {
        $returnRIDS = array();
        $ridString = implode(',',$rids);

        //Doing this for pretty much the same reason as keyword search above
        $con = mysqli_connect(
            config('database.connections.mysql.host'),
            config('database.connections.mysql.username'),
            config('database.connections.mysql.password'),
            config('database.connections.mysql.database')
        );

        //We want to make sure we are doing things in utf8 for special characters
        if(!mysqli_set_charset($con, "utf8")) {
            printf("Error loading character set utf8: %s\n", mysqli_error($con));
            exit();
        }

        if($ridString!="")
            $select = "SELECT `kid` from ".config('database.connections.mysql.prefix')."records WHERE `rid` IN ($ridString) ORDER BY FIELD(`rid`, $ridString)";
        else
            return $returnRIDS;

        $unclean = $con->query($select);

        while($row = $unclean->fetch_assoc()) {
            array_push($returnRIDS, $row['kid']);
        }

        mysqli_close($con);

        return $returnRIDS;
    }

    /**
     * Verifies the given format is an eligible format for exporting.
     *
     * @param  string $format - Format to compare
     * @return bool - Result of format being eligible
     */
    public static function isValidFormat($format) {
        return in_array(($format), self::VALID_FORMATS);
    }

    private function imitateMerge(&$array1, &$array2) {
        foreach($array2 as $i) {
            $array1[] = $i;
        }
    }
}
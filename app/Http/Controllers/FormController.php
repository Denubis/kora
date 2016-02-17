<?php namespace App\Http\Controllers;

use App\ComboListField;
use App\DateField;
use App\DocumentsField;
use App\Form;
use App\GalleryField;
use App\GeneratedListField;
use App\GeolocatorField;
use App\ListField;
use App\ModelField;
use App\MultiSelectListField;
use App\NumberField;
use App\PlaylistField;
use App\Record;
use App\RichTextField;
use App\ScheduleField;
use App\TextField;
use App\User;
use App\Field;
use App\Project;
use App\FormGroup;
use App\Http\Requests;
use App\VideoField;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Requests\FormRequest;
use App\Http\Controllers\Controller;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;


class FormController extends Controller {

    /**
     * User must be logged in to access views in this controller.
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('active');
    }

    
	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create($pid)
	{
        if(!ProjectController::validProj($pid)){
            return redirect('projects');
        }

        if(!FormController::checkPermissions($pid, 'create')){
            return redirect('projects/'.$pid.'/forms');
        }

        $project = ProjectController::getProject($pid);
        $users = User::lists('username', 'id');

        $presets = array();
        foreach(Form::where('preset', '=', 1, 'and', 'pid', '=', $pid)->get() as $form)
            $presets[] = ['fid' => $form->fid, 'name' => $form->name];

        return view('forms.create', compact('project', 'users', 'presets')); //pass in
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(FormRequest $request)
	{
        $form = Form::create($request->all());

        $form->layout = '<LAYOUT></LAYOUT>';
        $form->save();

        $adminGroup = FormController::makeAdminGroup($form, $request);
        $form->adminGID = $adminGroup->id;
        $form->save();

        if(isset($request['preset']))
            FormController::addPresets($form, $request['preset']);

        flash()->overlay(trans('controller_form.create'),trans('controller_form.goodjob'));

        return redirect('projects/'.$form->pid);
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($pid, $fid)
	{
        if(!FormController::validProjForm($pid,$fid)){
            return redirect('projects');
        }

        if(!FormController::checkPermissions($pid)){
            return redirect('/projects');
        }

        $form = FormController::getForm($fid);
        $proj = ProjectController::getProject($pid);
        $projName = $proj->name;

        return view('forms.show', compact('form','projName'));
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($pid, $fid)
    {
        if(!FormController::validProjForm($pid,$fid)){
            return redirect('projects');
        }

        if(!FormController::checkPermissions($pid, 'edit')){
            return redirect('/projects/'.$pid.'/forms');
        }

        $form = FormController::getForm($fid);
        $proj = ProjectController::getProject($pid);
        $projName = $proj->name;

        return view('forms.edit', compact('form','projName'));
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($pid, $fid, FormRequest $request)
	{
        if(!FormController::validProjForm($pid,$fid)){
            return redirect('projects');
        }

        $form = FormController::getForm($fid);

        if(!FormController::checkPermissions($pid, 'edit')){
            return redirect('/projects/'.$form->$pid.'/forms');
        }

        $form->update($request->all());

        flash()->overlay(trans('controller_form.update'),trans('controller_form.goodjob'));

        return redirect('projects/'.$form->pid);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($pid, $fid)
	{
        if(!FormController::validProjForm($pid,$fid)){
            return redirect('projects');
        }

        if(!FormController::checkPermissions($pid, 'delete')){
            return redirect('/projects/'.$pid.'/forms');
        }

        $form = FormController::getForm($fid);
        $form->delete();

        flash()->overlay(trans('controller_form.delete'),trans('controller_form.goodjob'));
	}

    public function exportRecords($pid, $fid){
        if(!FormController::validProjForm($pid,$fid)){
            return redirect('projects');
        }

        $form = FormController::getForm($fid);

        if(!\Auth::user()->isFormAdmin($form)){
            return redirect('projects/'.$pid.'/forms/'.$fid);
        }

        $xml='<Records>';

        $records = Record::where('fid', '=', $fid)->get();
        $fields = Field::where('fid', '=', $fid)->get();
        //dd($records);

        foreach($records as $record){
            $xml .= '<Record>';
            $xml .= '<kid>'.$record->kid.'</kid>';

            $xml .= '<Data>';

            foreach($fields as $field){
                $xml .= '<'.htmlentities($field->name).'>';

                if($field->type=='Text'){
                    $f = TextField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $value = $f->text;
                        $xml .= htmlentities($value);
                    }
                } else if($field->type=='Rich Text'){
                    $f = RichTextField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $value = $f->rawtext;
                        $xml .= htmlentities($value);
                    }
                } else if($field->type=='Number'){
                    $f = NumberField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $value = $f->number;
                        $xml .= htmlentities($value);
                    }
                } else if($field->type=='List'){
                    $f = ListField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $value = $f->option;
                        $xml .= htmlentities($value);
                    }
                } else if($field->type=='Multi-Select List'){
                    $f = MultiSelectListField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $options = explode('[!]', $f->options);
                        foreach($options as $opt){
                            $xml .= '<value>'.htmlentities($opt).'</value>';
                        }
                    }
                } else if($field->type=='Generated List'){
                    $f = GeneratedListField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $options = explode('[!]', $f->options);
                        foreach($options as $opt){
                            $xml .= '<value>'.htmlentities($opt).'</value>';
                        }
                    }
                } else if($field->type=='Combo List'){
                    $f = ComboListField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $typeone = ComboListField::getComboFieldType($field,'one');
                        $typetwo = ComboListField::getComboFieldType($field,'two');
                        $vals = explode('[!val!]',$f->options);
                        foreach($vals as $val){
                            $valone = explode('[!f1!]',$val)[1];
                            $valtwo = explode('[!f2!]',$val)[1];
                            $xml .= '<Value>';
                            $xml .= '<Field_One>';
                            if($typeone == 'Text' | $typeone == 'Number' | $typeone == 'List')
                                $xml .= htmlentities($valone);
                            else if($typeone == 'Multi-Select List' | $typeone == 'Generated List'){
                                $valone = explode('[!]',$valone);
                                foreach($valone as $vone){
                                    $xml .= '<value>'.htmlentities($vone).'</value>';
                                }
                            }
                            $xml .= '</Field_One>';
                            $xml .= '<Field_Two>';
                            if($typetwo == 'Text' | $typetwo == 'Number' | $typetwo == 'List')
                                $xml .= htmlentities($valtwo);
                            else if($typetwo == 'Multi-Select List' | $typetwo == 'Generated List'){
                                $valtwo = explode('[!]',$valtwo);
                                foreach($valtwo as $vtwo){
                                    $xml .= '<value>'.htmlentities($vtwo).'</value>';
                                }
                            }
                            $xml .= '</Field_Two>';
                            $xml .= '</Value>';
                        }
                    }
                } else if($field->type=='Date'){
                    $f = DateField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $value = '<Circa>'.htmlentities($f->circa).'</Circa>';
                        $value .= '<Month>'.htmlentities($f->month).'</Month>';
                        $value .= '<Day>'.htmlentities($f->day).'</Day>';
                        $value .= '<Year>'.htmlentities($f->year).'</Year>';
                        $value .= '<Era>'.htmlentities($f->era).'</Era>';
                        $xml .= $value;
                    }
                } else if($field->type=='Schedule'){
                    $f = ScheduleField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $value = '';
                        $events = explode('[!]',$f->events);
                        foreach($events as $event) {
                            $parts = explode(' ',$event);
                            if(sizeof($parts)==8) {
                                $value .= '<Event>';
                                $value .= '<Title>' . htmlentities(substr($parts[0], 0, -1)) . '</Title>';
                                $value .= '<Start>' . htmlentities($parts[1] . ' ' . $parts[2] . ' ' . $parts[3]) . '</Start>';
                                $value .= '<End>' . htmlentities($parts[5] . ' ' . $parts[6] . ' ' . $parts[7]) . '</End>';
                                $value .= '<All_Day>' . htmlentities(0) . '</All_Day>';
                                $value .= '</Event>';
                            }else{ //all day event
                                $value .= '<Event>';
                                $value .= '<Title>' . htmlentities(substr($parts[0], 0, -1)) . '</Title>';
                                $value .= '<Start>' . htmlentities($parts[1]) . '</Start>';
                                $value .= '<End>' . htmlentities($parts[3]) . '</End>';
                                $value .= '<All_Day>' . htmlentities(1) . '</All_Day>';
                                $value .= '</Event>';
                            }
                        }
                        $xml .= $value;
                    }
                } else if($field->type=='Documents'){
                    $f = DocumentsField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $files = explode('[!]',$f->documents);
                        foreach($files as $file) {
                            $xml .= '<File>';
                            $xml .= '<Name>' . htmlentities(explode('[Name]', $file)[1]) . '</Name>';
                            $xml .= '<Size>' . htmlentities(explode('[Size]', $file)[1]) . '</Size>';
                            $xml .= '<Type>' . htmlentities(explode('[Type]', $file)[1]) . '</Type>';
                            $xml .= '</File>';
                        }
                    }
                } else if($field->type=='Gallery'){
                    $f = GalleryField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $files = explode('[!]',$f->images);
                        foreach($files as $file) {
                            $xml .= '<File>';
                            $xml .= '<Name>' . htmlentities(explode('[Name]', $file)[1]) . '</Name>';
                            $xml .= '<Size>' . htmlentities(explode('[Size]', $file)[1]) . '</Size>';
                            $xml .= '<Type>' . htmlentities(explode('[Type]', $file)[1]) . '</Type>';
                            $xml .= '</File>';
                        }
                    }
                } else if($field->type=='Playlist'){
                    $f = PlaylistField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $files = explode('[!]',$f->audio);
                        foreach($files as $file) {
                            $xml .= '<File>';
                            $xml .= '<Name>' . htmlentities(explode('[Name]', $file)[1]) . '</Name>';
                            $xml .= '<Size>' . htmlentities(explode('[Size]', $file)[1]) . '</Size>';
                            $xml .= '<Type>' . htmlentities(explode('[Type]', $file)[1]) . '</Type>';
                            $xml .= '</File>';
                        }
                    }
                } else if($field->type=='Video'){
                    $f = VideoField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $files = explode('[!]',$f->video);
                        foreach($files as $file) {
                            $xml .= '<File>';
                            $xml .= '<Name>' . htmlentities(explode('[Name]', $file)[1]) . '</Name>';
                            $xml .= '<Size>' . htmlentities(explode('[Size]', $file)[1]) . '</Size>';
                            $xml .= '<Type>' . htmlentities(explode('[Type]', $file)[1]) . '</Type>';
                            $xml .= '</File>';
                        }
                    }
                } else if($field->type=='3D-Model'){
                    $f = ModelField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $value = $f->model;
                        $xml .= '<Name>'.htmlentities(explode('[Name]',$value)[1]).'</Name>';
                        $xml .= '<Size>'.htmlentities(explode('[Size]',$value)[1]).'</Size>';
                        $xml .= '<Type>'.htmlentities(explode('[Type]',$value)[1]).'</Type>';
                    }
                } else if($field->type=='Geolocator'){
                    $f = GeolocatorField::where('rid', '=', $record->rid)->where('flid', '=', $field->flid)->get()->first();
                    if(!is_null($f)) {
                        $locations = explode('[!]',$f->locations);
                        foreach($locations as $loc) {
                            $latlon = explode('[LatLon]', $loc)[1];
                            $utm = explode('[UTM]', $loc)[1];
                            $utm_coor = explode(':', $utm)[1];
                            $xml .= '<Location>';
                            $xml .= '<Desc>' . htmlentities(explode('[Desc]', $loc)[1]) . '</Desc>';
                            $xml .= '<Lat>' . htmlentities(explode(',', $latlon)[0]) . '</Lat>';
                            $xml .= '<Lon>' . htmlentities(explode(',', $latlon)[1]) . '</Lon>';
                            $xml .= '<Zone>' . htmlentities(explode(':', $utm)[0]) . '</Zone>';
                            $xml .= '<East>' . htmlentities(explode(',', $utm_coor)[0]) . '</East>';
                            $xml .= '<North>' . htmlentities(explode(',', $utm_coor)[1]) . '</North>';
                            $xml .= '<Address>' . htmlentities(explode('[Address]', $loc)[1]) . '</Address>';
                            $xml .= '</Location>';
                        }
                    }
                }

                $xml .= '</'.htmlentities($field->name).'>';
            }

            $xml .= '</Data>';

            $xml .= '</Record>';
        }

        $xml .= '</Records>';

        header("Content-Disposition: attachment; filename=".$form->name.'_recordData_'.Carbon::now().'.xml');
        header("Content-Type: application/octet-stream; ");

        echo $xml;
    }

    public function exportRecordFiles($pid, $fid){
        if(!FormController::validProjForm($pid,$fid)){
            return redirect('projects');
        }

        $form = FormController::getForm($fid);

        if(!\Auth::user()->isFormAdmin($form)){
            return redirect('projects/'.$pid.'/forms/'.$fid);
        }

        $path = env('BASE_PATH').'storage/app/files/p'.$pid.'/f'.$fid;
        $zipPath = env('BASE_PATH').'storage/app/tmpFiles/';

        // Initialize archive object
        $zip = new \ZipArchive();
        $time = Carbon::now();
        $zip->open($zipPath.$form->name.'_fileData_'.$time.'.zip', \ZipArchive::CREATE);

        //add files
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file)
        {
            // Skip directories (they would be added automatically)
            if (!$file->isDir())
            {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($path) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }

        // Zip archive will be created only after closing object
        $zip->close();

        header("Content-Disposition: attachment; filename=".$form->name.'_fileData_'.$time.'.zip');
        header("Content-Type: application/zip; ");

        readfile($zipPath.$form->name.'_fileData_'.$time.'.zip');
    }

    public function addNode($pid,$fid, Request $request){
        if(!FormController::validProjForm($pid,$fid)){
            return redirect('projects');
        }

        $form = FormController::getForm($fid);
        $name = $request->name;

        if(is_null($request->nodeTitle)) {
            $layout = explode('</LAYOUT>', $form->layout)[0];

            $layout .= "<NODE title='" . $name . "'></NODE></LAYOUT>";
        }else{
            $newNode = "<NODE title='" . $name . "'></NODE>";
            $containerNode = "<NODE title='" . $request->nodeTitle . "'>";
            $parts = explode($containerNode,$form->layout);

            $layout = $parts[0].$containerNode.$newNode.$parts[1];
        }

        $form->layout = $layout;
        $form->save();

        flash()->overlay(trans('controller_form.createnode'),trans('controller_form.goodjob'));

        return redirect('projects/'.$form->pid.'/forms/'.$form->fid);
    }

    public function deleteNode($pid,$fid,$title, Request $request){
        if(!FormController::validProjForm($pid,$fid)){
            return redirect('projects');
        }

        $form = FormController::getForm($fid);

        $layout = FormController::xmlToArray($form->layout);

        $nodeStart=0;
        for($i=0;$i<sizeof($layout);$i++){
            if($layout[$i]['tag']=='NODE' && $layout[$i]['type']=='open' && $layout[$i]['attributes']['TITLE']==$title){
                $nodeStart = $i;
                break;
            }
        }

        for($j=$nodeStart+1;$j<sizeof($layout);$j++){
            if(isset($layout[$j]) && $layout[$j]['tag']=='NODE' && $layout[$j]['type']=='close' && $layout[$j]['level']==$layout[$nodeStart]['level']){
                $nodeEnd = $j;
                break;
            }
        }

        $newLayout = array();

        for($k=0;$k<sizeof($layout);$k++){
            if($k!=$i && $k!=$j){
                array_push($newLayout,$layout[$k]);
            }
        }

        $fNav = new FieldNavController();
        $form->layout = $fNav->valsToXML($newLayout);
        $form->save();

        flash()->overlay(trans('controller_form.deletenode'),trans('controller_form.goodjob'));

        return redirect('projects/'.$form->pid.'/forms/'.$form->fid);
    }

    /**
     * Sets a form as a preset.
     *
     * @param $pid
     * @param $fid
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function preset($pid, $fid, Request $request)
    {
        if(!FormController::validProjForm($pid,$fid)){
            return redirect('projects');
        }

        $form = FormController::getForm($fid);
        if($request['preset'])
            $form->preset = 1;
        else
            $form->preset = 0;
        $form->save();
    }


    /**
     * Get form object for use in controller.
     *
     * @param $fid
     * @return mixed
     */
    public static function getForm($fid)
    {
        $form = Form::where('fid','=',$fid)->first();
        if(is_null($form)){
            $form = Form::where('slug','=',$fid)->first();
        }

        return $form;
    }

    /**
     * Validate that a form belongs to the project in use.
     *
     * @param $pid
     * @param $fid
     * @return bool
     */
    public static function validProjForm($pid, $fid)
    {
        $form = FormController::getForm($fid);
        $proj = ProjectController::getProject($pid);

        if(is_null($form) || is_null($proj))
            return false;
        else if($proj->pid==$form->pid)
            return true;
        else
            return false;
    }

    public static function xmlToArray($layout){
        $xml = xml_parser_create();
        xml_parse_into_struct($xml,$layout, $vals, $index);

        for($i=0;$i<sizeof($vals);$i++){
            if($vals[$i]['tag']=='NODE' && $vals[$i]['type']=='complete'){
                $j = $i;
                $first = true;
                for($k=sizeof($vals)-1;$k>$j;$k--){
                    if($k==$j+1 && $first){
                        //push k to end of array
                        array_push($vals,$vals[$k]);
                        //gather variables
                        $lvl = $vals[$j]['level'];
                        $title = $vals[$j]['attributes']['TITLE'];
                        //add open to j
                        $open = ['tag'=>'NODE', 'type'=>'open', 'level'=>$lvl, 'attributes'=>['TITLE'=>$title]];
                        $vals[$j] = $open;
                        //add close to k
                        $close = ['tag'=>'NODE', 'type'=>'close', 'level'=>$lvl];
                        $vals[$k] = $close;
                        //break
                        break;
                    }else if ($k==$j+1){
                        //move k to k+1
                        $vals[$k+1] = $vals[$k];
                        //gather variables
                        $lvl = $vals[$j]['level'];
                        $title = $vals[$j]['attributes']['TITLE'];
                        //add open to j
                        $open = ['tag'=>'NODE', 'type'=>'open', 'level'=>$lvl, 'attributes'=>['TITLE'=>$title]];
                        $vals[$j] = $open;
                        //add close to k
                        $close = ['tag'=>'NODE', 'type'=>'close', 'level'=>$lvl];
                        $vals[$k] = $close;
                        //break
                        break;
                    }else if ($first){
                        //push k to end of array
                        array_push($vals,$vals[$k]);
                        //first = false
                        $first = false;
                    }else{
                        //move k to k+1
                        $vals[$k+1] = $vals[$k];
                    }
                }
            }
        }

        return $vals;
    }

    /**
     * Checks if a user has a certain permission.
     * If no permission is provided checkPermissions simply decides if they are in any project group.
     * This acts as the "can read" permission level.
     *
     * @param $pid
     * @param string $permission
     * @return bool
     */
    public static function checkPermissions($pid, $permission='')
    {
        switch ($permission) {
            case 'create':
                if(!(\Auth::user()->canCreateForms(ProjectController::getProject($pid))))
                {
                    flash()->overlay(trans('controller_form.createper'), trans('controller_form.whoops'));
                    return false;
                }
                return true;
            case 'edit':
                if(!(\Auth::user()->canEditForms(ProjectController::getProject($pid))))
                {
                    flash()->overlay(trans('controller_form.editper'), trans('controller_form.whoops'));
                    return false;
                }
                return true;
            case 'delete':
                if(!(\Auth::user()->canDeleteForms(ProjectController::getProject($pid))))
                {
                    flash()->overlay(trans('controller_form.deleteper'), trans('controller_form.whoops'));
                    return false;
                }
                return true;
            default: //"Read Only"
                if(!(\Auth::user()->inAProjectGroup(ProjectController::getProject($pid))))
                {
                    flash()->overlay(trans('controller_form.viewper'), trans('controller_form.whoops'));
                    return false;
                }
                return true;
        }
    }
    /**
     * Creates the form's admin Group.
     *
     * @param $project
     * @param $request
     * @return FormGroup
     */
    private function makeAdminGroup(Form $form, Request $request)
    {
        $groupName = $form->name;
        $groupName .= ' Admin Group';

        $adminGroup = new FormGroup();
        $adminGroup->name = $groupName;
        $adminGroup->fid = $form->fid;
        $adminGroup->save();

        $formProject = $form->project()->first();
        $projectAdminGroup = $formProject->adminGroup()->first();

        $projectAdmins = $projectAdminGroup->users()->get();
        $idArray = [];

        //Add all current project admins to the form's admin group.
        foreach($projectAdmins as $projectAdmin)
            $idArray[] .= $projectAdmin->id;

        if (!is_null($request['admins']))
            $idArray = array_unique(array_merge($request['admins'], $idArray));

        if (!empty($idArray))
            $adminGroup->users()->attach($idArray);

        $adminGroup->create = 1;
        $adminGroup->edit = 1;
        $adminGroup->delete = 1;
        $adminGroup->ingest = 1;
        $adminGroup->modify = 1;
        $adminGroup->destroy = 1;

        $adminGroup->save();

        return $adminGroup;
    }

    /**
     * Creates the form from a preset form.
     *
     * @param Form $form
     * @param $fid
     */
    private function addPresets(Form $form, $fid)
    {
        $preset = Form::where('fid', '=', $fid)->first();

        $field_assoc = array();

        $form->layout = $preset->layout;

        //Duplicate fields
        foreach($preset->fields()->get() as $field)
        {
            $new = new Field();
            $new->pid = $form->pid;
            $new->fid = $form->fid;
            $new->order = $field->order;
            $new->type = $field->type;
            $new->name = $field->name;
            $new->slug = $field->slug.'_'.$form->slug;
            $new->desc = $field->desc;
            $new->required = $field->required;
            $new->default = $field->default;
            $new->options = $field->options;
            $new->save();

            $field_assoc[$field->flid] = $new->flid;
        }

        $xmlArray = FormController::xmlToArray($form->layout);
        for($i=0; $i<sizeof($xmlArray); $i++)
        {
            if($xmlArray[$i]['tag'] == 'ID')
            {
                $temp = $field_assoc[$xmlArray[$i]['value']];
                $xmlArray[$i]['value'] = $temp;
            }
        }

        $x = new FieldNavController();
        $xmlString = $x->valsToXML($xmlArray);
        $form->layout = $xmlString;
        $form->save();
    }
}

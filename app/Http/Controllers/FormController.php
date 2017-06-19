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
use App\Page;
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
        $users = User::lists('username', 'id')->all();

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

        $form->save();

        if(!isset($request['preset'])) //Since the preset is copying the target form, no need to make a default page
            PageController::makePageOnForm($form->fid,$form->slug." Default Page");

        $adminGroup = FormController::makeAdminGroup($form, $request);
        FormController::makeDefaultGroup($form);
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
            return redirect('projects/'.$pid);
        }

        if(!FormController::checkPermissions($pid)){
            return redirect('/projects');
        }

        $form = FormController::getForm($fid);
        $proj = ProjectController::getProject($pid);
        $projName = $proj->name;

        $pageLayout = PageController::getFormLayout($fid);

        return view('forms.show', compact('form','projName','pageLayout'));
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
            return redirect('projects/'.$pid);
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
            return redirect('projects/'.$pid);
        }

        $form = FormController::getForm($fid);

        if(!FormController::checkPermissions($pid, 'edit')){
            return redirect('/projects/'.$form->$pid.'/forms');
        }

        $form->update($request->all());

        FormGroupController::updateMainGroupNames($form);

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
            return redirect('projects/'.$pid);
        }

        if(!FormController::checkPermissions($pid, 'delete')){
            return redirect('/projects/'.$pid.'/forms');
        }

        $form = FormController::getForm($fid);
        $form->delete();

        flash()->overlay(trans('controller_form.delete'),trans('controller_form.goodjob'));
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
            return redirect('projects/'.$pid);
        }

        $form = FormController::getForm($fid);
        if($request['preset'])
            $form->preset = 1;
        else
            $form->preset = 0;
        $form->save();
    }

    public function importFormView($pid){
        if(!ProjectController::validProj($pid)){
            return redirect('projects');
        }

        if(!FormController::checkPermissions($pid, 'ingest')) {
            return redirect('projects/'.$pid);
        }

        $proj = ProjectController::getProject($pid);

        return view('forms.import',compact('proj','pid'));
    }

    public function importFormViewK2($pid){
        if(!ProjectController::validProj($pid)){
            return redirect('projects');
        }

        if(!FormController::checkPermissions($pid, 'ingest')) {
            return redirect('projects/'.$pid);
        }

        $proj = ProjectController::getProject($pid);

        return view('forms.importk2',compact('proj','pid'));
    }

    /**
     * Get form object for use in controller.
     *
     * @param $fid
     * @return Form | null.
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
     * Creates the form's admin Group.
     *
     * @param $project
     * @param $request
     * @return FormGroup
     */
    private function makeDefaultGroup(Form $form)
    {
        $groupName = $form->name;
        $groupName .= ' Default Group';

        $defaultGroup = new FormGroup();
        $defaultGroup->name = $groupName;
        $defaultGroup->fid = $form->fid;
        $defaultGroup->save();

        $defaultGroup->create = 0;
        $defaultGroup->edit = 0;
        $defaultGroup->delete = 0;
        $defaultGroup->ingest = 0;
        $defaultGroup->modify = 0;
        $defaultGroup->destroy = 0;

        $defaultGroup->save();
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
        $pageConvert = array();

        foreach($preset->pages()->get() as $page){
            $newP = new Page();
            $newP->parent_type = $page->parent_type;
            $newP->fid = $form->fid;
            $newP->title = $page->title;
            $newP->sequence = $page->sequence;
            $newP->save();

            $pageConvert[$page->id] = $newP->id;
        }

        //Duplicate fields
        foreach($preset->fields()->get() as $field)
        {
            $new = new Field();
            $new->pid = $form->pid;
            $new->fid = $form->fid;
            $new->page_id = $pageConvert[$field->page_id];
            $new->sequence = $field->sequence;
            $new->type = $field->type;
            $new->name = $field->name;
            $new->slug = $field->slug.'_'.$form->slug;
            $new->desc = $field->desc;
            $new->required = $field->required;
            $new->searchable = $field->searchable;
            $new->extsearch = $field->extsearch;
            $new->viewable = $field->viewable;
            $new->viewresults = $field->viewresults;
            $new->extview = $field->extview;
            $new->default = $field->default;
            $new->options = $field->options;
            $new->save();

            $field_assoc[$field->flid] = $new->flid;
        }

        $form->save();
    }
}

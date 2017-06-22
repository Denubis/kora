<?php namespace App\Http\Controllers\Auth;

use App\Form;
use App\Project;
use App\Record;
use App\User;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class UserController extends Controller {

    /*
    |--------------------------------------------------------------------------
    | User Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles ...
    |
    */

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth', ['except' => ['activate', 'activator', 'activateshow']]);
        $this->middleware('active', ['except' => ['activate', 'activator', 'activateshow']]);
    }

    /**
     * Show the application welcome screen to the user.
     *
     *
     *
     * @return Response
     */
    public function index()
    {
        $languages_available = Config::get('app.locales_supported');

        $user = Auth::user();

        $profile = $user->profile;

        if($user->admin){
            $admin = 1;
            $records = Record::where('owner', '=', $user->id)->orderBy('updated_at', 'desc')->take(30)->get();
            return view('user/profile',compact('languages_available', 'admin', 'records', 'profile'));
        }
        else{
            $admin = 0;
            $projects = self::buildProjectsArray($user);
            $forms = self::buildFormsArray($user);
            $records = Record::where('owner', '=', $user->id)->orderBy('updated_at', 'desc')->get();

            return view('user/profile',compact('languages_available', 'admin', 'projects', 'forms', 'records', 'profile'));
        }
    }

    public function changepicture(Request $request){
        $file = $request->file('profile');
        $pDir = env('BASE_PATH') . 'storage/app/profiles/'.\Auth::user()->id.'/';
        $pURL = env('STORAGE_URL') . 'profiles/'.\Auth::user()->id.'/';

        //remove old pic
        $oldFile = $pDir.\Auth::user()->profile;
        if(file_exists($oldFile))
            unlink($oldFile);

        //set new pic to db
        $newFilename = $file->getClientOriginalName();
        \Auth::user()->profile = $newFilename;
        \Auth::user()->save();

        //move photo and return new path
        $file->move($pDir,$newFilename);

        return $pURL.$newFilename;
    }

    /**
     * @param Request $request
     * @return Response
     */

    public function changeprofile(Request $request){
        $user = Auth::user();
        $type = $request->input("type");

        if($type == "lang"){
            $lang = $request->input("field");

            if(empty($lang)){
                flash()->overlay(trans('controller_auth_user.selectlan'),trans('controller_auth_user.whoops'));
                //return redirect('user/profile');
            }
            else{
                $user->language = $lang;
                $user->save();
                flash()->overlay(trans('controller_auth_user.lanupdate'),trans('controller_auth_user.success'));
               // return redirect('user/profile');
            }
        }
        elseif($type == "dash"){
            $dash = $request->input("field");

            if($dash!="0" && $dash!="1"){
                flash()->overlay(trans('controller_auth_user.selectdash'),trans('controller_auth_user.whoops'));
                //return redirect('user/profile');
            }
            else{
                $user->dash = $dash;
                $user->save();
                flash()->overlay(trans('controller_auth_user.dashupdate'),trans('controller_auth_user.success'));
                // return redirect('user/profile');
            }
        }
        elseif($type == "name"){
            $realname = $request->input("field");

            if(empty($realname)){
                flash()->overlay(trans('controller_auth_user.entername'),trans('controller_auth_user.whoops'));
                //return redirect('user/profile');
            }
            else{
                $user->name = $realname;
                $user->save();
                flash()->overlay(trans('controller_auth_user.nameupdate'),trans('controller_auth_user.success'));
                //return redirect('user/profile');
            }
        }
        elseif($type == "org"){
            $organization = $request->input("field");

            if(empty($organization)){
                flash()->overlay(trans('controller_auth_user.enterorg'),trans('controller_auth_user.whoops'));
                //return redirect('user/profile');
            }
            else{
                $user->organization = $organization;
                $user->save();
                flash()->overlay(trans('controller_auth_user.orgupdate'),trans('controller_auth_user.success'));
               // return redirect('user/profile');
            }

        }
        else{

        }

    }
    public function changepw(Request $request)
    {
        $user = Auth::user();
        $new_pass = $request->new_password;
        $confirm = $request->confirm;

        if (empty($new_pass) && empty($confirm)){
            flash()->overlay(trans('controller_auth_user.bothpass'), trans('controller_auth_user.whoops'));
            return redirect('user/profile');
        }

        elseif(strlen($new_pass) < 6){
            flash()->overlay(trans('controller_auth_user.lessthan'), trans('controller_auth_user.whoops'));
            return redirect('user/profile');
        }

        elseif($new_pass != $confirm){
            flash()->overlay(trans('controller_auth_user.nomatch'), trans('controller_auth_user.whoops'));
            return redirect('user/profile');
        }

        else{
            $user->password = bcrypt($new_pass);
            $user->save();

            flash()->overlay(trans('controller_auth_user.passupdate'), trans('controller_auth_user.success'));
            return redirect('user/profile');
        }
    }

    /**
     * @return Response
     */
    public function activateshow()
    {
        return view('auth.activate');
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function activator(Request $request)
    {
        $user = User::where('username', '=', $request->user)->first();
        if($user==null){
            flash()->overlay(trans('controller_auth_user.nouser'), trans('controller_auth_user.whoops'));
            return redirect('auth/activate');
        }

        $token = trim($request->token);

        if ($user->regtoken == $token && !empty($user->regtoken) && !($user->active ==1)){
            $user->active = 1;
            $user->save();
            flash()->overlay(trans('controller_auth_user.activated'), trans('controller_auth_user.success'));

            \Auth::login($user);

            return redirect('/');
        }
        else{
            flash()->overlay(trans('controller_auth_user.badtokenuser'), trans('controller_auth_user.whoops'));
            return redirect('auth/activate');
        }
    }

    /**
     * Activates the user with a link that is emailed to them.
     *
     * @param token
     * @return Response
     */
    public function activate($token)
    {
        if(!is_null(\Auth::user())){
            \Auth::logout(\Auth::user()->id);
        }

        $user = User::where('regtoken', '=', $token)->first();

        \Auth::login($user);

        if ($token != $user->regtoken)
        {
            flash()->overlay(trans('controller_auth_user.badtoken'), trans('controller_auth_user.whoops'));
            return redirect('/');
        }
        else
        {
            $user->active = 1;
            $user->save();

            flash()->overlay(trans('controller_auth_user.acttwo'), trans('controller_auth_user.success'));
            return redirect('/');
        }
    }

    public static function buildProjectsArray(User $user)
    {
        $all_projects = Project::all();
        $projects = array();
        $i=0;
        foreach($all_projects as $project)
        {
            if($user->inAProjectGroup($project))
            {
                $permissions = '';
                $projects[$i]['pid'] = $project->pid;
                $projects[$i]['name'] = $project->name;

                if($user->isProjectAdmin($project))
                    $projects[$i]['permissions'] = 'Admin';
                else
                {
                    if($user->canCreateForms($project))
                        $permissions .= 'Create Forms | ';
                    if($user->canEditForms($project))
                        $permissions .= 'Edit Forms | ';
                    if($user->canDeleteForms($project))
                        $permissions .= 'Delete Forms | ';

                    if($permissions == '')
                        $permissions .= 'Read Only';
                    $projects[$i]['permissions'] = rtrim($permissions, '| ');
                }
            }
            $i++;
        }
        return $projects;
    }

    public static function buildFormsArray(User $user)
    {
        $i=0;
        $all_forms = Form::all();
        $forms = array();
        foreach($all_forms as $form)
        {
            if($user->inAFormGroup($form))
            {
                $permissions = '';
                $forms[$i]['fid'] = $form->fid;
                $forms[$i]['pid'] = $form->pid;
                $forms[$i]['name'] = $form->name;

                if($user->isFormAdmin($form))
                    $forms[$i]['permissions'] = 'Admin';
                else
                {
                    if($user->canCreateFields($form))
                        $permissions .= 'Create Fields | ';
                    if($user->canEditFields($form))
                        $permissions .= 'Edit Fields | ';
                    if($user->canDeleteFields($form))
                        $permissions .= 'Delete Fields | ';
                    if($user->canIngestRecords($form))
                        $permissions .= 'Create Records | ';
                    if($user->canModifyRecords($form))
                        $permissions .= 'Edit Records | ';
                    if ($user->canDestroyRecords($form))
                        $permissions .= 'Delete Records | ';
                    if($permissions == '')
                        $permissions .= 'Read Only ';
                    $forms[$i]['permissions'] = rtrim($permissions, '| ');
                }
            }
            $i++;
        }
        return $forms;
    }
}